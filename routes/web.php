<?php

use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ScanController::class, 'index'])->name('scan.index');

// Scanning
Route::post('/scan', [ScanController::class, 'scan'])->name('scan.process');
Route::post('/save', [ScanController::class, 'save'])->name('scan.save');
Route::post('/export-preview', [ScanController::class, 'exportPreview'])->name('scan.export-preview');

// Records
Route::get('/records',                       [ScanController::class, 'records'])->name('scan.records');
Route::get('/records/{scoreSheet}',          [ScanController::class, 'show'])->name('scan.show');
Route::get('/records/{scoreSheet}/export',   [ScanController::class, 'export'])->name('scan.export');
Route::delete('/records/{scoreSheet}',       [ScanController::class, 'destroy'])->name('scan.destroy');

// System check — visit /check to verify Tesseract is installed
Route::get('/check', function () {
    $checks = [];

    // Tesseract
    exec('tesseract --version 2>&1', $tOut, $tCode);
    $checks['tesseract'] = [
        'ok'      => $tCode === 0,
        'version' => $tOut[0] ?? 'not found',
        'fix'     => 'sudo apt-get install -y tesseract-ocr tesseract-ocr-eng',
    ];

    // pdftoppm
    exec('which pdftoppm 2>&1', $pOut, $pCode);
    $checks['pdftoppm'] = [
        'ok'      => $pCode === 0,
        'version' => $pCode === 0 ? ($pOut[0] ?? 'found') : 'not found',
        'fix'     => 'sudo apt-get install -y poppler-utils',
    ];

    // ImageMagick
    exec('convert --version 2>&1', $iOut, $iCode);
    $checks['imagemagick'] = [
        'ok'      => $iCode === 0,
        'version' => $iOut[0] ?? 'not found',
        'fix'     => 'sudo apt-get install -y imagemagick',
    ];

    $allOk = collect($checks)->every(fn($c) => $c['ok']);

    return response()->json([
        'status' => $allOk ? 'ALL OK — ready to scan!' : 'Some dependencies missing',
        'checks' => $checks,
    ], $allOk ? 200 : 500);
});


 

 
Route::get('/debug-gemini-key', function () {
    // 1) Clear any stale cached config so we're reading the live .env value,
    //    not a value baked in by a prior `php artisan config:cache`.
    if (file_exists(base_path('bootstrap/cache/config.php'))) {
        echo "⚠️ bootstrap/cache/config.php EXISTS — you have a cached config. ";
        echo "Run `php artisan config:clear` in the project root, then reload this page.<br><br>";
    } else {
        echo "✅ No cached config file found — .env is being read live.<br><br>";
    }
 
    $key = config('services.gemini.api_key');
 
    // 2) Inspect the raw bytes of the key for hidden whitespace/newlines/quotes
    //    that would silently corrupt the query string.
    echo "Key length: " . strlen($key) . " characters<br>";
    echo "First 6 chars: " . htmlspecialchars(substr($key, 0, 6)) . "<br>";
    echo "Last 4 chars: " . htmlspecialchars(substr($key, -4)) . "<br>";
    echo "Hex dump of first 10 bytes: " . bin2hex(substr($key, 0, 10)) . "<br>";
    echo "Hex dump of last 10 bytes: " . bin2hex(substr($key, -10)) . "<br>";
    echo "Contains whitespace? " . (preg_match('/\s/', $key) ? 'YES ⚠️' : 'no') . "<br>";
    echo "Contains quote chars? " . (preg_match('/[\'"]/', $key) ? 'YES ⚠️' : 'no') . "<br><br>";
 
    // 3) Fire a minimal raw curl request directly to Gemini — completely
    //    bypassing Laravel's Http facade, Guzzle config, and any middleware —
    //    to prove whether the key itself is the problem or something in
    //    Laravel's outgoing request pipeline is injecting/breaking headers.
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=' . $key;
 
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'contents' => [['parts' => [['text' => 'Say the word OK and nothing else.']]]],
        ]),
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
 
    echo "Raw curl HTTP status: <b>{$status}</b><br>";
    if ($curlErr) {
        echo "curl error: {$curlErr}<br>";
    }
    echo "<pre>" . htmlspecialchars($body) . "</pre>";
 
    return '';
});