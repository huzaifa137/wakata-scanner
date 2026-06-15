<?php

namespace App\Http\Controllers;

use App\Models\ScoreEntry;
use App\Models\ScoreSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                // Image: OCR was already done in the browser (Tesseract.js)
                // The extracted text arrives as a POST field, not re-processed here
                $rawText = $request->input('ocr_text', '');
                $extracted = $this->parseOcrText($rawText);
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

        if (empty(trim($rawText))) {
            throw new \RuntimeException(
                'This PDF appears to be a scanned image (no embedded text). ' .
                'Please take a photo of it and upload as an image instead.'
            );
        }

        return $this->parseOcrText($rawText);
    }

    /**
     * Image upload → preprocess → Tesseract → parse
     */
    private function extractFromImage(string $imgPath): array
    {
        $this->ensureTesseract();
        $processed = $this->preprocessImage($imgPath);
        $text = $this->runTesseract($processed);
        if ($processed !== $imgPath)
            @unlink($processed);
        return $this->parseOcrText($text);
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
        $pastHeader = false;

        foreach ($lines as $line) {
            if (!$pastHeader && preg_match('/S\s*[\/\\\\]\s*N.*NAME.*P\s*[12]/i', $line)) {
                $pastHeader = true;
                continue;
            }
            if (!$pastHeader)
                continue;
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

        // Relaxed fallback
        if (
            preg_match(
                '/^(\d{1,3})[.\)]\s+(.{4,50}?)\s+((?:\d+\.?\d*\s*){3,})$/',
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
