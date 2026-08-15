<?php

namespace App\Http\Controllers;

use App\Models\ScoreEntry;
use App\Models\ScoreSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ScanController extends Controller
{
    // ═══════════════════════════════════════════════════════════════════════
    // PUBLIC ROUTES
    // ═══════════════════════════════════════════════════════════════════════

    public function index()
    {
        $recentSheets = ScoreSheet::with('entries')->latest()->take(10)->get();
        return view('scans.index', compact('recentSheets'));
    }

    // -----------------------------------------------------------------------
    // SCAN — receive file, run Tesseract OCR, return structured JSON
    // -----------------------------------------------------------------------
    public function scan(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp|max:20480',
            'scan_type' => 'required|in:pdf,image',
        ]);

        Storage::disk('local')->makeDirectory('scans/temp');
        
        $file = $request->file('file');
        $scanType = $request->input('scan_type');
        $extension = strtolower($file->getClientOriginalExtension());

        $storedPath = $file->store('scans/temp', 'local');
        $fullPath = Storage::disk('local')->path($storedPath);
        

        try {
            if ($extension === 'pdf') {
                $extracted = $this->extractFromPdf($fullPath);
            } else {
                // Image: server-side Tesseract first, with automatic Gemini
                // vision fallback for handwriting / low-quality scans
                $extracted = $this->extractFromImage($fullPath);
            }

            Storage::disk('local')->delete($storedPath);

            return response()->json([
                'success' => true,
                'data' => $extracted,
                'source_file' => $file->getClientOriginalName(),
                'scan_type' => $scanType,
            ]);

        } catch (\Throwable $e) {
            Log::error('Scan failed: ' . $e->getMessage());
            Storage::disk('local')->delete($storedPath);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // -----------------------------------------------------------------------
    // SAVE — bulk insert reviewed/edited rows to DB
    // -----------------------------------------------------------------------
    public function save(Request $request)
    {
        $request->validate([
            'school_name' => 'nullable|string|max:255',
            'zone' => 'nullable|string|max:100',
            'ref_no' => 'nullable|string|max:50',
            'subject' => 'nullable|string|max:255',
            'exam_year' => 'nullable|string|max:10',
            'source_file' => 'nullable|string|max:255',
            'scan_type' => 'nullable|in:pdf,image',
            'entries' => 'required|array|min:1',
            'entries.*.candidate_name' => 'required|string|max:255',
            'entries.*.p1' => 'nullable|numeric',
            'entries.*.p2' => 'nullable|numeric',
            'entries.*.p3' => 'nullable|numeric',
            'entries.*.p4' => 'nullable|numeric',
            'entries.*.average' => 'nullable|numeric',
            'entries.*.grade' => 'nullable|string|max:10',
        ]);

        DB::beginTransaction();
        try {
            $sheet = ScoreSheet::create([
                'school_name' => $request->input('school_name'),
                'zone' => $request->input('zone'),
                'ref_no' => $request->input('ref_no'),
                'subject' => $request->input('subject'),
                'exam_year' => $request->input('exam_year'),
                'source_file' => $request->input('source_file'),
                'scan_type' => $request->input('scan_type', 'pdf'),
            ]);

            foreach ($request->input('entries') as $index => $entry) {
                if (empty(trim($entry['candidate_name'] ?? ''))) {
                    continue;
                }
                ScoreEntry::create([
                    'score_sheet_id' => $sheet->id,
                    'serial_no' => $entry['serial_no'] ?? ($index + 1),
                    'candidate_name' => trim($entry['candidate_name']),
                    'p1' => $entry['p1'] ?? null,
                    'p2' => $entry['p2'] ?? null,
                    'p3' => $entry['p3'] ?? null,
                    'p4' => $entry['p4'] ?? null,
                    'average' => $entry['average'] ?? null,
                    'grade' => $entry['grade'] ?? null,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'sheet_id' => $sheet->id,
                'saved_rows' => $sheet->entries()->count(),
                'message' => 'Score sheet saved successfully!',
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Save failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function show(ScoreSheet $scoreSheet)
    {
        $scoreSheet->load('entries');
        return view('scans.show', compact('scoreSheet'));
    }

    public function records(Request $request)
    {
        $query = ScoreSheet::withCount('entries')->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('school_name', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('zone', 'like', "%{$search}%")
                    ->orWhere('ref_no', 'like', "%{$search}%");
            });
        }

        $sheets = $query->paginate(20)->withQueryString();
        return view('scans.records', compact('sheets'));
    }

    public function destroy(ScoreSheet $scoreSheet)
    {
        $scoreSheet->delete();
        return response()->json(['success' => true, 'message' => 'Record deleted.']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // OCR PIPELINE
    // ═══════════════════════════════════════════════════════════════════════

    // -----------------------------------------------------------------------
// PDF extraction — smalot/pdfparser (pure PHP, no system deps)
// -----------------------------------------------------------------------
    private function extractFromPdf(string $pdfPath): array
    {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($pdfPath);
        $rawText = $pdf->getText();

        if ($this->hasSubstantiveText($rawText)) {
            return $this->parseOcrText($rawText);
        }

        // No real text layer — this PDF is a photo/scan wrapped in a PDF
        // container (common with scanner apps like CamScanner, which embed
        // only a watermark string such as "CamScanner" as the "text").
        // Rasterize each page and run it through the same Tesseract → AI
        // vision fallback pipeline used for photo uploads.
        $pages = $this->rasterizePdfPages($pdfPath);

        if (empty($pages)) {
            throw new \RuntimeException(
                'No readable text could be found in this PDF, and it could not be converted to an '
                . 'image for OCR either (the server is missing the pdftoppm/poppler-utils tool). '
                . 'Please re-save it as a JPG/PNG and upload using "Hardcopy Photo / Scan" mode instead.'
            );
        }

        $combined = ['sheet_meta' => $this->extractMeta([]), 'entries' => []];
        $usedAiVision = false;

        foreach ($pages as $pagePath) {
            $pageResult = $this->extractFromImage($pagePath);
            @unlink($pagePath);

            if (!empty($pageResult['notice'])) {
                $usedAiVision = true;
            }
            if (empty($combined['sheet_meta']['school_name']) && !empty($pageResult['sheet_meta']['school_name'])) {
                $combined['sheet_meta'] = $pageResult['sheet_meta'];
            }
            foreach ($pageResult['entries'] as $entry) {
                $combined['entries'][] = $entry;
            }
        }

        if ($usedAiVision) {
            $combined['notice'] = 'This PDF had no embedded text layer, so the rows below were read '
                . 'using AI vision instead of standard OCR. Please double‑check them carefully against '
                . 'the original document.';
        } elseif (empty($combined['entries'])) {
            $combined['notice'] = 'This PDF has no embedded text — it looks like a photo or scan saved '
                . 'as a PDF rather than a typed document, and automatic OCR/AI vision could not '
                . 'confidently read any rows from it either. Please add the candidate rows manually below.';
        }

        return $combined;
    }

    /**
     * Returns true if $text has enough real content to be worth parsing.
     * Filters out PDFs whose only "text layer" is a scanner-app watermark
     * (e.g. CamScanner free exports embed just the word "CamScanner").
     */
    private function hasSubstantiveText(string $text): bool
    {
        $clean = trim(preg_replace('/\bCamScanner\b/i', '', $text));
        return strlen($clean) >= 30 && preg_match('/\d/', $clean) === 1;
    }

    /**
     * Rasterizes every page of a PDF to a PNG via poppler's pdftoppm.
     * Returns an empty array if pdftoppm isn't installed on the server.
     */
    private function rasterizePdfPages(string $pdfPath): array
    {
        exec('which pdftoppm 2>&1', $out, $code);
        if ($code !== 0) {
            return [];
        }

        $prefix = sys_get_temp_dir() . '/wakata_pdfimg_' . uniqid();
        exec(sprintf(
            'pdftoppm -r 300 -png %s %s 2>&1',
            escapeshellarg($pdfPath),
            escapeshellarg($prefix)
        ), $out, $code);

        $pages = glob($prefix . '*.png');
        sort($pages);
        return $pages;
    }

    /**
     * Image upload → preprocess → Tesseract (free, fast, local).
     * If that finds too few rows — the telltale sign of handwriting, which
     * Tesseract cannot read — automatically fall back to Gemini's vision API
     * (free tier, see GEMINI_API_KEY in .env) for a proper read.
     */
    private function extractFromImage(string $imgPath): array
    {
        $tesseractResult = ['sheet_meta' => $this->extractMeta([]), 'entries' => []];

        try {
            $this->ensureTesseract();
            $processed = $this->preprocessImage($imgPath);
            $text = $this->runTesseract($processed);
            if ($processed !== $imgPath) {
                @unlink($processed);
            }
            $tesseractResult = $this->parseOcrText($text);
        } catch (\Throwable $e) {
            Log::warning('Tesseract OCR unavailable/failed, relying on AI vision fallback: ' . $e->getMessage());
        }

        if (count($tesseractResult['entries']) >= 3) {
            return $tesseractResult;
        }

        $gemini = $this->extractWithGeminiVision($imgPath);

        if ($gemini !== null && count($gemini['entries']) > count($tesseractResult['entries'])) {
            $gemini['notice'] = count($tesseractResult['entries']) === 0
                ? 'Standard OCR could not confidently read this image (common with handwriting), so it '
                . 'was automatically re-processed using AI vision instead. Please double‑check the rows '
                . 'below carefully.'
                : 'Standard OCR only found a few rows on this image, so it was automatically '
                . 're-processed using AI vision for a more complete read. Please double‑check the rows '
                . 'below carefully.';
            return $gemini;
        }

        return $tesseractResult;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // GEMINI VISION FALLBACK  (free tier — for handwritten / low-quality scans)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Sends an image to Google's Gemini API (free tier) and asks it to read
     * the score sheet directly into our exact JSON schema. Returns null if
     * no API key is configured, or if the request fails for any reason —
     * callers treat that the same as "no improvement over Tesseract."
     */
    private function extractWithGeminiVision(string $imagePath): ?array
    {
        $apiKey = config('services.gemini.api_key');
        if (empty($apiKey)) {
            return null;
        }

        $mimeType = match (strtolower(pathinfo($imagePath, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        $imageData = base64_encode(file_get_contents($imagePath));

        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'school_name' => ['type' => 'STRING', 'nullable' => true],
                'zone' => ['type' => 'STRING', 'nullable' => true],
                'ref_no' => ['type' => 'STRING', 'nullable' => true],
                'subject' => ['type' => 'STRING', 'nullable' => true],
                'exam_year' => ['type' => 'STRING', 'nullable' => true],
                'entries' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'serial_no' => ['type' => 'INTEGER'],
                            'candidate_name' => ['type' => 'STRING'],
                            'p1' => ['type' => 'NUMBER', 'nullable' => true],
                            'p2' => ['type' => 'NUMBER', 'nullable' => true],
                            'p3' => ['type' => 'NUMBER', 'nullable' => true],
                            'p4' => ['type' => 'NUMBER', 'nullable' => true],
                            'average' => ['type' => 'NUMBER', 'nullable' => true],
                            'grade' => ['type' => 'STRING', 'nullable' => true],
                        ],
                        'required' => ['serial_no', 'candidate_name'],
                    ],
                ],
            ],
            'required' => ['entries'],
        ];

        $prompt = 'You are reading a UCE mock exam score sheet (printed or handwritten) for a Ugandan '
            . 'school. Read every row of the candidate table carefully, including handwritten entries. '
            . 'Rules: "S/N" or "S/H" is the serial number column. Read each candidate\'s full name '
            . 'exactly as written, in uppercase. Each P1, P2, P3, P4 column holds a numeric score, only '
            . 'if that column actually exists on the sheet — if a column such as AVERAGE or GRADE does '
            . 'not appear on the sheet at all, leave it null for every row rather than guessing a value. '
            . 'If a digit is ambiguous, use the most visually likely digit rather than skipping the row. '
            . 'Do not skip any row in the table, even if the handwriting is messy. Also extract header '
            . 'info if present: school name, zone, REF number, subject, exam year. Return only the '
            . 'structured data described by the schema — do not invent rows or scores that are not on '
            . 'the sheet.';

        try {
            $response = Http::timeout(60)
                ->retry(3, 1000, function ($exception, $request) {
                    // Only retry on transient server-side errors (503/429), not on
                    // auth or bad-request errors which won't fix themselves.
                    return $exception instanceof \Illuminate\Http\Client\RequestException
                        && in_array($exception->response->status(), [429, 503]);
                }, throw: false)
                ->post(
                    'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key='
                    . $apiKey,
                    [
                        'contents' => [[
                            'parts' => [
                                ['text' => $prompt],
                                ['inline_data' => ['mime_type' => $mimeType, 'data' => $imageData]],
                            ],
                        ]],
                        'generationConfig' => [
                            'responseMimeType' => 'application/json',
                            'responseSchema' => $schema,
                            'temperature' => 0,
                        ],
                    ]
                );

            if (!$response->successful()) {
                Log::warning('Gemini vision request failed: ' . $response->body());
                return null;
            }

            $text = $response->json('candidates.0.content.parts.0.text');
            if (!$text) {
                return null;
            }

            $decoded = json_decode($text, true);
            if (!is_array($decoded) || !isset($decoded['entries'])) {
                return null;
            }

            return [
                'sheet_meta' => [
                    'school_name' => $decoded['school_name'] ?? null,
                    'zone' => $decoded['zone'] ?? null,
                    'ref_no' => $decoded['ref_no'] ?? null,
                    'subject' => $decoded['subject'] ?? null,
                    'exam_year' => $decoded['exam_year'] ?? null,
                ],
                'entries' => array_map(function ($e) {
                    return [
                        'serial_no' => (int) ($e['serial_no'] ?? 0),
                        'candidate_name' => strtoupper(trim($e['candidate_name'] ?? '')),
                        'p1' => isset($e['p1']) ? (float) $e['p1'] : null,
                        'p2' => isset($e['p2']) ? (float) $e['p2'] : null,
                        'p3' => isset($e['p3']) ? (float) $e['p3'] : null,
                        'p4' => isset($e['p4']) ? (float) $e['p4'] : null,
                        'average' => isset($e['average']) ? (float) $e['average'] : null,
                        'grade' => $e['grade'] ?? null,
                    ];
                }, $decoded['entries']),
            ];
        } catch (\Throwable $e) {
            Log::warning('Gemini vision call failed: ' . $e->getMessage());
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // IMAGE PREPROCESSING  (ImageMagick)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Apply ImageMagick chain tuned for printed score-sheet tables:
     *  • Convert to grayscale
     *  • Normalize histogram (handles dim/overexposed photos)
     *  • Adaptive threshold (handles uneven lighting on hardcopy scans)
     *  • Sharpen edges
     *  • Upscale 2× (Tesseract performs best on ~300 DPI equivalent)
     *  • Deskew up to 40°
     */
    private function preprocessImage(string $srcPath): string
    {
        $outPath = sys_get_temp_dir() . '/wakata_pp_' . uniqid() . '.png';

        $cmd = sprintf(
            'convert %s '
            . '-colorspace Gray '
            . '-normalize '
            . '-resize 200%% '
            . '-sharpen 0x1.5 '
            . '-level 15%%,85%% '
            . '-deskew 40%% '
            . '%s 2>&1',
            escapeshellarg($srcPath),
            escapeshellarg($outPath)
        );

        exec($cmd, $output, $code);

        if ($code !== 0 || !file_exists($outPath)) {
            // If ImageMagick fails (unlikely), fall back to original
            Log::warning('ImageMagick preprocessing failed, using original. Output: ' . implode(' ', $output));
            return $srcPath;
        }

        return $outPath;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TESSERACT OCR
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Run Tesseract with settings tuned for tabular data:
     *  --oem 1  = LSTM neural net engine (most accurate)
     *  --psm 4  = Assume single column of variable-size text
     *             (works well for full-page score sheets)
     */
    private function runTesseract(string $imagePath): string
    {
        $outBase = sys_get_temp_dir() . '/wakata_ocr_' . uniqid();

        $cmd = sprintf(
            'tesseract %s %s --oem 1 --psm 4 -l eng 2>&1',
            escapeshellarg($imagePath),
            escapeshellarg($outBase)
        );

        exec($cmd, $output, $code);

        $txtFile = $outBase . '.txt';
        if (!file_exists($txtFile)) {
            throw new \RuntimeException(
                'Tesseract OCR failed (exit ' . $code . '). ' . implode(' ', $output)
            );
        }

        $text = file_get_contents($txtFile);
        @unlink($txtFile);
        return $text;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEXT PARSER  — converts raw OCR text → structured array
    // ═══════════════════════════════════════════════════════════════════════

    // ═══════════════════════════════════════════════════════════════════════
// TEXT PARSER — same for both PDF text and Tesseract.js image text
// ═══════════════════════════════════════════════════════════════════════

    private function parseOcrText(string $rawText): array
    {
        $lines = preg_split('/\r?\n/', $rawText);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, fn($l) => $l !== '');
        $lines = array_values($lines);

        return [
            'sheet_meta' => $this->extractMeta($lines),
            'entries' => $this->extractEntries($lines),
        ];
    }

    // -----------------------------------------------------------------------
    // Meta extraction
    // -----------------------------------------------------------------------
    private function extractMeta(array $lines): array
    {
        $meta = ['school_name' => null, 'zone' => null, 'ref_no' => null, 'subject' => null, 'exam_year' => null];
        $fullText = implode(' ', $lines);

        if (preg_match('/NAME\s+OF\s+SCHOOL\s*:\s*(.+?)\s+ZONE\s*:/i', $fullText, $m))
            $meta['school_name'] = trim($m[1]);

        if (preg_match('/ZONE\s*:\s*([A-Za-z0-9\s\-]+?)\s+REF/i', $fullText, $m))
            $meta['zone'] = trim($m[1]);

        if (preg_match('/REF\s*No\.?\s*(\d+)/i', $fullText, $m))
            $meta['ref_no'] = trim($m[1]);

        if (preg_match('/SUBJECT\s*[:\-_]*\s*([A-Za-z][A-Za-z\s]{2,40})/i', $fullText, $m)) {
            $subj = trim($m[1]);
            if (!preg_match('/^(S\/N|NAME|CANDIDATE|P1|GRADE|_)/i', $subj))
                $meta['subject'] = $subj;
        }

        if (preg_match('/\b(20\d{2})\b/', implode(' ', array_slice($lines, 0, 8)), $m))
            $meta['exam_year'] = $m[1];

        return $meta;
    }

    // -----------------------------------------------------------------------
    // Entry row extraction
    // -----------------------------------------------------------------------
    private function extractEntries(array $lines): array
    {
        $entries = [];

        foreach ($lines as $line) {
            if (preg_match('/^[\|\-\s_=]+$/', $line))
                continue;

            $entry = $this->parseCandidateLine($line);
            if ($entry)
                $entries[] = $entry;
        }

        // De-duplicate and sort
        $seen = [];
        $unique = [];
        foreach ($entries as $e) {
            if (!isset($seen[$e['serial_no']])) {
                $seen[$e['serial_no']] = true;
                $unique[] = $e;
            }
        }
        usort($unique, fn($a, $b) => $a['serial_no'] <=> $b['serial_no']);

        return $unique;
    }

    private function parseCandidateLine(string $line): ?array
    {
        // Strict: "1. ABDALLAH ALI WEGULO 12 14 89 45 14 78"
        if (
            preg_match(
                '/^(\d{1,3})[.\)]\s+([A-Z][A-Z\s\'\-\.]{3,60})\s+([\d][\d\s\.]{0,80})$/',
                $line,
                $m
            )
        ) {
            $entry = $this->buildEntry((int) $m[1], trim($m[2]), $m[3]);

            if ($entry['p1'] !== null) {
                return $entry;
            }
        }

        // Relaxed fallback — at least one trailing score, so sheets with
        // fewer columns (e.g. only P1, or P1-P2) still get picked up
        if (
            preg_match(
                '/^(\d{1,3})[.\)]\s+(.{4,50}?)\s+((?:\d+\.?\d*\s*){1,})$/',
                $line,
                $m
            )
        ) {
            $name = strtoupper(
                trim(
                    preg_replace('/[^A-Za-z\s\'\-\.]/', '', $m[2])
                )
            );

            if (strlen($name) >= 4) {
                return $this->buildEntry((int) $m[1], $name, $m[3]);
            }
        }

        return null;
    }


    private function buildEntry(int $serial, string $name, string $numString): array
    {
        $tokens = preg_split('/\s+/', trim($numString));
        $scores = [];
        $grade = null;

        foreach ($tokens as $token) {
            if (is_numeric($token))
                $scores[] = (float) $token;
            elseif (preg_match('/^[A-Fa-f][1-9]$/', $token))
                $grade = strtoupper($token);
        }

        if ($grade === null && count($scores) >= 6) {
            $grade = (string) (int) array_splice($scores, 5, 1)[0];
        }

        return [
            'serial_no' => $serial,
            'candidate_name' => $name,
            'p1' => $scores[0] ?? null,
            'p2' => $scores[1] ?? null,
            'p3' => $scores[2] ?? null,
            'p4' => $scores[3] ?? null,
            'average' => $scores[4] ?? null,
            'grade' => $grade,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // GUARDS
    // ═══════════════════════════════════════════════════════════════════════

    private function ensureTesseract(): void
    {
        exec('which tesseract 2>&1', $out, $code);
        if ($code !== 0) {
            throw new \RuntimeException(
                'Tesseract OCR is not installed or not in PATH. ' .
                'Run: sudo apt-get install -y tesseract-ocr tesseract-ocr-eng'
            );
        }
    }
}