<?php

use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ScanController::class, 'index'])->name('scan.index');

// Scanning
Route::post('/scan', [ScanController::class, 'scan'])->name('scan.process');
Route::post('/save', [ScanController::class, 'save'])->name('scan.save');

// Records
Route::get('/records',                [ScanController::class, 'records'])->name('scan.records');
Route::get('/records/{scoreSheet}',   [ScanController::class, 'show'])->name('scan.show');
Route::delete('/records/{scoreSheet}',[ScanController::class, 'destroy'])->name('scan.destroy');

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
