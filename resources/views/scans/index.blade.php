@extends('layouts.app')
@section('title', 'Scan Score Sheet')

@section('content')

{{-- STEP INDICATOR --}}
<div class="steps">
    <div class="step active" id="step1">
        <div class="step-num">1</div><span>Upload File</span>
    </div>
    <div class="step-line" id="line1"></div>
    <div class="step" id="step2">
        <div class="step-num">2</div><span>Review & Edit</span>
    </div>
    <div class="step-line" id="line2"></div>
    <div class="step" id="step3">
        <div class="step-num">3</div><span>Save to Database</span>
    </div>
</div>

{{-- ════════════════════════════════════════════════════════════════════════
     STEP 1 — UPLOAD
     ════════════════════════════════════════════════════════════════════════ --}}
<div id="uploadSection">
    <div class="row g-4">

        {{-- Upload card --}}
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-cloud-upload text-primary me-2"></i>Upload Score Sheet
                </div>
                <div class="card-body p-4">

                    {{-- Scan type toggle --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold" style="font-size:.82rem">Document Type</label>
                        <div class="scan-toggle d-flex gap-2 flex-wrap">
                            <input type="radio" class="btn-check" name="scanType" id="typePdf" value="pdf" checked>
                            <label class="btn btn-outline-primary btn-sm px-3" for="typePdf">
                                <i class="bi bi-file-earmark-pdf me-1"></i>Softcopy PDF
                            </label>
                            <input type="radio" class="btn-check" name="scanType" id="typeImage" value="image">
                            <label class="btn btn-outline-primary btn-sm px-3" for="typeImage">
                                <i class="bi bi-camera me-1"></i>Hardcopy Photo / Scan
                            </label>
                        </div>
                        <p class="text-muted mt-2 mb-0" id="typeHint" style="font-size:.78rem">
                            <i class="bi bi-info-circle"></i>
                            Upload a PDF file — text will be extracted automatically
                        </p>
                    </div>

                    {{-- Drop zone --}}
                    <div class="upload-zone" id="dropZone"
                         onclick="document.getElementById('fileInput').click()">
                        <input type="file" id="fileInput" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        <i class="bi bi-file-earmark-arrow-up mb-3 d-block" id="dropIcon"
                           style="font-size:2.5rem;color:#a0aec0"></i>
                        <p class="fw-semibold mb-1" id="dropTitle">Click or drag & drop your file here</p>
                        <p class="text-muted mb-0" style="font-size:.8rem">
                            Supports PDF, JPG, PNG, WEBP &nbsp;·&nbsp; Max 20 MB
                        </p>
                    </div>

                    {{-- File selected preview --}}
                    <div id="filePreview" class="mt-3 d-none">
                        <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3 border">
                            <i class="bi bi-file-earmark-check fs-3 text-success"></i>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="fw-semibold text-truncate" id="fileName" style="font-size:.85rem"></div>
                                <div class="text-muted" id="fileSize" style="font-size:.75rem"></div>
                            </div>
                            <button class="btn btn-sm btn-outline-danger" onclick="clearFile()">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Camera (image mode only) --}}
                    <div id="cameraSection" class="mt-3 d-none">
                        <button class="btn btn-outline-secondary btn-sm w-100" onclick="openCamera()">
                            <i class="bi bi-camera me-1"></i>Or take a photo with your camera
                        </button>
                        <video id="cameraVideo" class="w-100 rounded mt-2 d-none"
                               autoplay playsinline></video>
                        <button class="btn btn-primary btn-sm mt-2 w-100 d-none"
                                id="captureBtn" onclick="capturePhoto()">
                            <i class="bi bi-circle-fill me-1"></i>Capture Photo
                        </button>
                        <canvas id="cameraCanvas" style="display:none"></canvas>
                    </div>

                    <button class="btn btn-primary w-100 mt-4" id="scanBtn"
                            onclick="startScan()" disabled>
                        <i class="bi bi-search me-2"></i>Run OCR &amp; Extract Data
                    </button>
                </div>
            </div>
        </div>

        {{-- Tips + recent scans --}}
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header">
                    <i class="bi bi-lightbulb text-warning me-2"></i>Tips for Best Results
                </div>
                <div class="card-body" style="font-size:.82rem">
                    <p class="fw-semibold mb-2">📄 PDF (Softcopy):</p>
                    <ul class="ps-3 text-muted mb-3">
                        <li>Original digital PDFs extract fastest and most accurately</li>
                        <li>Scanned PDFs also work — OCR will be applied</li>
                        <li>Multi-page PDFs are fully supported</li>
                    </ul>
                    <p class="fw-semibold mb-2">📷 Hardcopy / Printed Sheet:</p>
                    <ul class="ps-3 text-muted mb-3">
                        <li>Lay flat on a bright, evenly-lit surface</li>
                        <li>Avoid shadows across the table area</li>
                        <li>Hold camera straight above — no angle</li>
                        <li>Capture the full sheet in frame without cropping</li>
                    </ul>
                    <div class="alert alert-success py-2 px-3 mb-0" style="font-size:.78rem">
                        <i class="bi bi-shield-check me-1"></i>
                        <strong>Tesseract-first:</strong> OCR runs locally on your server for free.
                        Only if it can't confidently read a scan (e.g. handwriting) is the image sent
                        to Google's Gemini API as a fallback — only when configured.
                    </div>
                </div>
            </div>

            {{-- OCR engine status badge --}}
            <div class="card mb-3" style="border-left:4px solid #057a55">
                <div class="card-body py-2 px-3 d-flex align-items-center gap-3">
                    <i class="bi bi-cpu text-success fs-4"></i>
                    <div>
                        <div class="fw-semibold" style="font-size:.82rem">Tesseract OCR + AI Vision Fallback</div>
                        <div class="text-muted" style="font-size:.75rem">
                            Tesseract is free &amp; local · Gemini fallback handles handwriting ·
                            <a href="/check" target="_blank" class="text-decoration-none">Check status</a>
                        </div>
                    </div>
                </div>
            </div>

            @if($recentSheets->count())
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-clock-history me-2"></i>Recent Scans</span>
                    <a href="{{ route('scan.records') }}"
                       class="btn btn-sm btn-outline-primary" style="font-size:.72rem">View All</a>
                </div>
                <div class="card-body p-0">
                    @foreach($recentSheets->take(5) as $sheet)
                    <a href="{{ route('scan.show', $sheet) }}"
                       class="d-flex align-items-center gap-3 px-3 py-2 border-bottom
                              text-decoration-none text-dark recent-row">
                        <i class="bi bi-file-earmark-text text-muted"></i>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="text-truncate fw-medium" style="font-size:.8rem">
                                {{ $sheet->school_name ?? 'Unknown School' }}
                            </div>
                            <div class="text-muted" style="font-size:.72rem">
                                {{ $sheet->subject ?? 'No subject' }} · {{ $sheet->entries->count() }} entries
                            </div>
                        </div>
                        <span class="badge {{ $sheet->scan_type === 'pdf' ? 'badge-pdf' : 'badge-image' }}"
                              style="font-size:.68rem">
                            {{ strtoupper($sheet->scan_type) }}
                        </span>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════════════════════════════════
     STEP 2 — PREVIEW & EDIT
     ════════════════════════════════════════════════════════════════════════ --}}
<div id="previewSection" style="display:none">

    <div class="d-flex align-items-start justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h5 class="mb-0 fw-semibold">Review Extracted Data</h5>
            <p class="text-muted mb-0" style="font-size:.82rem">
                OCR has read your document. Edit any field directly in the table before saving.
            </p>
        </div>
        <button class="btn btn-outline-secondary btn-sm" onclick="backToUpload()">
            <i class="bi bi-arrow-left me-1"></i>Scan Another
        </button>
    </div>

    {{-- OCR warning banner (shown when few rows found, or backend sends a notice) --}}
    <div id="ocrWarning" class="alert alert-warning d-none mb-3" style="font-size:.82rem">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <span id="ocrWarningText">
            <strong>Only a few rows were detected.</strong>
            This can happen with low-quality photos or unusual layouts.
            Please add any missing rows manually using the <em>"+ Add Row"</em> button below.
        </span>
    </div>

    {{-- Sheet meta --}}
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-info-circle me-2"></i>Sheet Information</div>
        <div class="card-body">
            <div class="meta-grid">
                <div>
                    <label class="form-label">School Name</label>
                    <input class="form-control" id="meta_school_name" placeholder="School name">
                </div>
                <div>
                    <label class="form-label">Zone</label>
                    <input class="form-control" id="meta_zone" placeholder="Zone">
                </div>
                <div>
                    <label class="form-label">REF No.</label>
                    <input class="form-control" id="meta_ref_no" placeholder="Ref number">
                </div>
                <div>
                    <label class="form-label">Subject</label>
                    <input class="form-control" id="meta_subject" placeholder="Subject">
                </div>
                <div>
                    <label class="form-label">Exam Year</label>
                    <input class="form-control" id="meta_exam_year" placeholder="e.g. 2025">
                </div>
            </div>
        </div>
    </div>

    {{-- Entries table --}}
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span>
                <i class="bi bi-table me-2"></i>Candidate Scores
                <span class="badge bg-primary ms-1" id="rowCountBadge">0</span>
            </span>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" onclick="addRow()">
                    <i class="bi bi-plus-circle me-1"></i>Add Row
                </button>
                <button class="btn btn-sm btn-outline-success" onclick="exportToExcel()">
                    <i class="bi bi-file-earmark-excel me-1"></i>Export to Excel
                </button>
                <button class="btn btn-sm btn-success" onclick="saveData()">
                    <i class="bi bi-cloud-check me-1"></i>Save to Database
                </button>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="preview-table-wrap">
                <table class="preview-table" id="previewTable">
                    <thead>
                        <tr>
                            <th style="width:40px">#</th>
                            <th>Candidate Name</th>
                            <th style="text-align:center">P1</th>
                            <th style="text-align:center">P2</th>
                            <th style="text-align:center">P3</th>
                            <th style="text-align:center">P4</th>
                            <th style="text-align:center">Average</th>
                            <th style="text-align:center">Grade</th>
                            <th style="width:36px"></th>
                        </tr>
                    </thead>
                    <tbody id="previewTbody"></tbody>
                </table>
            </div>
        </div>

        <div class="card-footer bg-transparent d-flex justify-content-between
                    align-items-center py-2 px-3">
            <small class="text-muted">
                <i class="bi bi-pencil me-1"></i>Click any cell to edit
            </small>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-success px-3" onclick="exportToExcel()">
                    <i class="bi bi-file-earmark-excel me-2"></i>Export to Excel
                </button>
                <button class="btn btn-success px-4" onclick="saveData()">
                    <i class="bi bi-cloud-check me-2"></i>Save All to Database
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ── STATE ──────────────────────────────────────────────────────────────────
let selectedFile = null;
let selectedType = 'pdf';
let sourceFile   = '';
let cameraStream = null;

// ── SCAN TYPE TOGGLE ───────────────────────────────────────────────────────
document.querySelectorAll('input[name="scanType"]').forEach(radio => {
    radio.addEventListener('change', function () {
        selectedType = this.value;
        const isPdf  = selectedType === 'pdf';

        document.getElementById('typeHint').innerHTML = isPdf
            ? '<i class="bi bi-info-circle"></i> Upload a PDF — text will be extracted automatically'
            : '<i class="bi bi-info-circle"></i> Upload a photo of the printed score sheet, or use camera';

        document.getElementById('fileInput').accept = isPdf
            ? '.pdf'
            : '.jpg,.jpeg,.png,.webp';

        document.getElementById('cameraSection').classList.toggle('d-none', isPdf);
        document.getElementById('dropIcon').className =
            (isPdf ? 'bi bi-file-earmark-pdf' : 'bi bi-camera') +
            ' mb-3 d-block';

        clearFile();
    });
});

// ── DRAG & DROP ────────────────────────────────────────────────────────────
const dropZone = document.getElementById('dropZone');
dropZone.addEventListener('dragover', e => {
    e.preventDefault();
    dropZone.classList.add('drag-over');
});
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]);
});
document.getElementById('fileInput').addEventListener('change', e => {
    if (e.target.files[0]) handleFile(e.target.files[0]);
});

function handleFile(file) {
    selectedFile = file;
    sourceFile   = file.name;
    document.getElementById('fileName').textContent  = file.name;
    document.getElementById('fileSize').textContent  =
        (file.size / 1024 / 1024).toFixed(2) + ' MB';
    document.getElementById('filePreview').classList.remove('d-none');
    document.getElementById('scanBtn').disabled = false;
    document.getElementById('dropTitle').textContent = 'File selected ✓';
}

function clearFile() {
    selectedFile = null;
    document.getElementById('fileInput').value = '';
    document.getElementById('filePreview').classList.add('d-none');
    document.getElementById('scanBtn').disabled = true;
    document.getElementById('dropTitle').textContent = 'Click or drag & drop your file here';
}

// ── CAMERA ─────────────────────────────────────────────────────────────────
async function openCamera() {
    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' }
        });
        const video = document.getElementById('cameraVideo');
        video.srcObject = cameraStream;
        video.classList.remove('d-none');
        document.getElementById('captureBtn').classList.remove('d-none');
    } catch (err) {
        Swal.fire('Camera Error', err.message, 'error');
    }
}

function capturePhoto() {
    const video  = document.getElementById('cameraVideo');
    const canvas = document.getElementById('cameraCanvas');
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    canvas.toBlob(blob => {
        selectedFile = new File([blob], 'camera_capture.jpg', { type: 'image/jpeg' });
        sourceFile   = 'camera_capture.jpg';
        handleFile(selectedFile);
        if (cameraStream) cameraStream.getTracks().forEach(t => t.stop());
        document.getElementById('cameraVideo').classList.add('d-none');
        document.getElementById('captureBtn').classList.add('d-none');
    }, 'image/jpeg', 0.95);
}

// ── SCAN ───────────────────────────────────────────────────────────────────
async function startScan() {
    if (!selectedFile) return;

    const isPdf = selectedType === 'pdf';

    if (isPdf) {
        // ── PDF: send to server, smalot/pdfparser extracts text ──────────
        showLoading('Reading PDF…', 'Extracting text from your PDF file');

        const formData = new FormData();
        formData.append('file',      selectedFile);
        formData.append('scan_type', selectedType);

        try {
            const res    = await fetch('{{ route("scan.process") }}', {
                method:  'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body:    formData,
            });
            const result = await res.json();
            hideLoading();

            if (!result.success) {
                Swal.fire({
                    title: 'Extraction Failed',
                    html:  '<pre style="font-size:.75rem;text-align:left;white-space:pre-wrap">' +
                           escHtml(result.message || 'Unknown error') + '</pre>',
                    icon: 'error',
                });
                return;
            }

            populatePreview(result.data);
            showPreviewSection();

        } catch (err) {
            hideLoading();
            Swal.fire('Network Error', err.message, 'error');
        }

    } else {
        // ── IMAGE: send straight to server. Server tries Tesseract OCR
        // first (free/local), and automatically falls back to AI vision for
        // handwriting / low-quality scans when Tesseract finds too little ──
        showLoading('Reading your photo…', 'Running OCR — this can take 10–20 seconds');

        const formData = new FormData();
        formData.append('file',      selectedFile);
        formData.append('scan_type', selectedType);

        try {
            const res    = await fetch('{{ route("scan.process") }}', {
                method:  'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body:    formData,
            });
            const result = await res.json();
            hideLoading();

            if (!result.success) {
                Swal.fire({
                    title: 'Reading Failed',
                    html:  '<pre style="font-size:.75rem;text-align:left;white-space:pre-wrap">' +
                           escHtml(result.message || 'Unknown error') + '</pre>',
                    icon:  'error',
                });
                return;
            }

            populatePreview(result.data);
            showPreviewSection();

        } catch (err) {
            hideLoading();
            Swal.fire('Network Error', err.message, 'error');
        }
    }
}

// ── POPULATE PREVIEW ───────────────────────────────────────────────────────
function populatePreview(data) {
    const meta = data.sheet_meta || {};
    document.getElementById('meta_school_name').value = meta.school_name || '';
    document.getElementById('meta_zone').value        = meta.zone        || '';
    document.getElementById('meta_ref_no').value      = meta.ref_no      || '';
    document.getElementById('meta_subject').value     = meta.subject     || '';
    document.getElementById('meta_exam_year').value   = meta.exam_year   || '';

    const tbody = document.getElementById('previewTbody');
    tbody.innerHTML = '';
    (data.entries || []).forEach((e, i) => appendRow(e, i));
    updateRowCount();

    // Warn if very few rows detected (possibly poor scan quality), or show
    // a specific backend notice (e.g. "this PDF has no text layer") when present
    const count = (data.entries || []).length;
    const warningBanner = document.getElementById('ocrWarning');
    const warningText   = document.getElementById('ocrWarningText');

    if (data.notice) {
        warningText.innerHTML = '<strong>Heads up:</strong> ' + escHtml(data.notice);
        warningBanner.classList.remove('d-none');
    } else {
        warningText.innerHTML =
            '<strong>Only a few rows were detected.</strong> ' +
            'This can happen with low-quality photos or unusual layouts. ' +
            'Please add any missing rows manually using the <em>"+ Add Row"</em> button below.';
        warningBanner.classList.toggle('d-none', count >= 3);
    }
}

function appendRow(entry = {}, index = null) {
    const tbody  = document.getElementById('previewTbody');
    const rowNum = index !== null ? index + 1 : tbody.rows.length + 1;
    const tr     = document.createElement('tr');

    tr.innerHTML = `
        <td style="color:#9ca3af;font-size:.75rem;padding:.4rem .75rem">${rowNum}</td>
        <td><input class="name-input" type="text"
                   value="${esc(entry.candidate_name || '')}"
                   placeholder="Candidate name"></td>
        <td><input class="score-input" type="number" step="0.01"
                   value="${entry.p1 ?? ''}" placeholder="–"></td>
        <td><input class="score-input" type="number" step="0.01"
                   value="${entry.p2 ?? ''}" placeholder="–"></td>
        <td><input class="score-input" type="number" step="0.01"
                   value="${entry.p3 ?? ''}" placeholder="–"></td>
        <td><input class="score-input" type="number" step="0.01"
                   value="${entry.p4 ?? ''}" placeholder="–"></td>
        <td><input class="score-input" type="number" step="0.01"
                   value="${entry.average ?? ''}" placeholder="–"></td>
        <td><input class="grade-input" type="text"
                   value="${esc(entry.grade || '')}" placeholder="–"></td>
        <td><button class="del-btn" onclick="deleteRow(this)" title="Remove">
                <i class="bi bi-trash3"></i></button></td>
    `;
    tbody.appendChild(tr);
    updateRowCount();
}

function addRow() {
    appendRow({});
    document.getElementById('previewTbody')
        .lastElementChild?.querySelector('input')?.focus();
}

function deleteRow(btn) {
    btn.closest('tr').remove();
    renumberRows();
    updateRowCount();
}

function renumberRows() {
    document.querySelectorAll('#previewTbody tr').forEach((tr, i) => {
        tr.cells[0].textContent = i + 1;
    });
}

function updateRowCount() {
    document.getElementById('rowCountBadge').textContent =
        document.getElementById('previewTbody').rows.length;
}

// ── COLLECT & SAVE ─────────────────────────────────────────────────────────
function collectTableData() {
    return Array.from(document.querySelectorAll('#previewTbody tr')).map((tr, i) => {
        const inp = tr.querySelectorAll('input');
        return {
            serial_no:      i + 1,
            candidate_name: inp[0].value.trim(),
            p1:             inp[1].value !== '' ? parseFloat(inp[1].value) : null,
            p2:             inp[2].value !== '' ? parseFloat(inp[2].value) : null,
            p3:             inp[3].value !== '' ? parseFloat(inp[3].value) : null,
            p4:             inp[4].value !== '' ? parseFloat(inp[4].value) : null,
            average:        inp[5].value !== '' ? parseFloat(inp[5].value) : null,
            grade:          inp[6].value.trim() || null,
        };
    });
}

async function saveData() {
    const entries = collectTableData().filter(e => e.candidate_name);

    if (!entries.length) {
        Swal.fire('Empty', 'Add at least one candidate row before saving.', 'warning');
        return;
    }

    const confirm = await Swal.fire({
        title:             'Save Score Sheet?',
        html:              `Insert <strong>${entries.length} candidate record(s)</strong> into the database?`,
        icon:              'question',
        showCancelButton:  true,
        confirmButtonText: 'Yes, Save',
        confirmButtonColor:'#1a56db',
        cancelButtonText:  'Review Again',
    });
    if (!confirm.isConfirmed) return;

    showLoading('Saving to database…', 'Writing ' + entries.length + ' records');

    try {
        const res = await fetch('{{ route("scan.save") }}', {
            method:  'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept':       'application/json',
            },
            body: JSON.stringify({
                school_name: document.getElementById('meta_school_name').value,
                zone:        document.getElementById('meta_zone').value,
                ref_no:      document.getElementById('meta_ref_no').value,
                subject:     document.getElementById('meta_subject').value,
                exam_year:   document.getElementById('meta_exam_year').value,
                source_file: sourceFile,
                scan_type:   selectedType,
                entries,
            }),
        });
        const result = await res.json();
        hideLoading();

        if (result.success) {
            const action = await Swal.fire({
                title:             '✅ Saved Successfully!',
                html:              `<strong>${result.saved_rows}</strong> records saved.<br>
                                    <small class="text-muted">Sheet ID: #${result.sheet_id}</small>`,
                icon:              'success',
                confirmButtonText: 'View Records',
                showCancelButton:  true,
                cancelButtonText:  'Scan Another',
                confirmButtonColor:'#057a55',
            });
            if (action.isConfirmed) {
                window.location.href = '{{ route("scan.records") }}';
            } else {
                backToUpload();
            }
        } else {
            Swal.fire('Save Failed', result.message, 'error');
        }

    } catch (err) {
        hideLoading();
        Swal.fire('Error', err.message, 'error');
    }
}

// ── EXPORT TO EXCEL ────────────────────────────────────────────────────────
async function exportToExcel() {
    const entries = collectTableData().filter(e => e.candidate_name);

    if (!entries.length) {
        Swal.fire('Empty', 'Add at least one candidate row before exporting.', 'warning');
        return;
    }

    showLoading('Building Excel file…', 'Preparing your download');

    try {
        const res = await fetch('{{ route("scan.export-preview") }}', {
            method:  'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/json',
                'Accept':       'application/json',
            },
            body: JSON.stringify({
                school_name: document.getElementById('meta_school_name').value,
                subject:     document.getElementById('meta_subject').value,
                entries,
            }),
        });

        hideLoading();

        if (!res.ok) {
            const result = await res.json().catch(() => ({}));
            Swal.fire('Export Failed', result.message || 'Could not generate the Excel file.', 'error');
            return;
        }

        const blob = await res.blob();
        const disposition = res.headers.get('Content-Disposition') || '';
        const match = disposition.match(/filename="?([^"]+)"?/);
        const filename = match ? match[1] : 'score_sheet.xlsx';

        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();

    } catch (err) {
        hideLoading();
        Swal.fire('Error', err.message, 'error');
    }
}

// ── UI TRANSITIONS ─────────────────────────────────────────────────────────
function showPreviewSection() {
    document.getElementById('uploadSection').style.display  = 'none';
    document.getElementById('previewSection').style.display = 'block';
    setStep(2);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function backToUpload() {
    document.getElementById('uploadSection').style.display  = 'block';
    document.getElementById('previewSection').style.display = 'none';
    clearFile();
    setStep(1);
}

function setStep(n) {
    [1, 2, 3].forEach(i => {
        const el = document.getElementById('step' + i);
        el.classList.remove('active', 'done');
        if (i < n)  el.classList.add('done');
        if (i === n) el.classList.add('active');
    });
    [1, 2].forEach(i => {
        document.getElementById('line' + i)
                .classList.toggle('done', i < n);
    });
}

// ── UTILS ──────────────────────────────────────────────────────────────────
function esc(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
@endpush