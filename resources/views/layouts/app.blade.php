<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'WAKATA Score Scanner')</title>

    {{-- Bootstrap 5 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Bootstrap Icons --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    {{-- SweetAlert2 --}}
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    {{-- Google Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary:     #1a56db;
            --primary-dark:#1e429f;
            --success:     #057a55;
            --danger:      #c81e1e;
            --warning:     #c27803;
            --sidebar-bg:  #1e2433;
            --sidebar-txt: #a8b4cc;
            --sidebar-act: #ffffff;
            --bg:          #f4f6fb;
            --card-shadow: 0 1px 3px rgba(0,0,0,.08), 0 4px 12px rgba(0,0,0,.06);
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: #1f2937;
            min-height: 100vh;
            display: flex;
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 240px;
            min-height: 100vh;
            background: var(--sidebar-bg);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0;
            z-index: 100;
        }
        .sidebar-brand {
            padding: 1.4rem 1.5rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .sidebar-brand h6 {
            font-size: .65rem;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #6b7a99;
            margin-bottom: .25rem;
        }
        .sidebar-brand strong {
            font-size: 1rem;
            color: #fff;
            display: block;
        }
        .sidebar-brand span {
            font-size: .75rem;
            color: #6b7a99;
        }
        .sidebar nav { padding: 1rem 0; flex: 1; }
        .nav-label {
            font-size: .65rem;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #4a5568;
            padding: .5rem 1.5rem .25rem;
            font-weight: 600;
        }
        .sidebar a {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: .55rem 1.5rem;
            color: var(--sidebar-txt);
            text-decoration: none;
            font-size: .875rem;
            font-weight: 500;
            border-left: 3px solid transparent;
            transition: all .15s;
        }
        .sidebar a:hover, .sidebar a.active {
            color: var(--sidebar-act);
            background: rgba(255,255,255,.06);
            border-left-color: var(--primary);
        }
        .sidebar a i { font-size: 1rem; width: 1.1rem; }

        /* ── MAIN CONTENT ── */
        .main-wrap {
            margin-left: 240px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .topbar {
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
            padding: .75rem 1.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .topbar h1 {
            font-size: 1.05rem;
            font-weight: 600;
            margin: 0;
        }
        .content-area { padding: 1.75rem; }

        /* ── CARDS ── */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
        }
        .card-header {
            background: transparent;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 1.25rem;
            font-weight: 600;
            font-size: .9rem;
        }

        /* ── UPLOAD ZONE ── */
        .upload-zone {
            border: 2px dashed #c8d0de;
            border-radius: 12px;
            padding: 3rem 2rem;
            text-align: center;
            background: #f9fafc;
            cursor: pointer;
            transition: all .2s;
        }
        .upload-zone:hover, .upload-zone.drag-over {
            border-color: var(--primary);
            background: #eff5ff;
        }
        .upload-zone i { font-size: 2.5rem; color: #a0aec0; }
        .upload-zone.drag-over i { color: var(--primary); }
        .upload-zone input[type=file] { display: none; }

        /* ── SCAN TYPE TOGGLE ── */
        .scan-toggle .btn-check:checked + .btn {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        /* ── STEP INDICATOR ── */
        .steps { display: flex; align-items: center; gap: 0; margin-bottom: 1.75rem; }
        .step {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .8rem;
            font-weight: 600;
            color: #9ca3af;
        }
        .step.active { color: var(--primary); }
        .step.done   { color: var(--success); }
        .step-num {
            width: 26px; height: 26px;
            border-radius: 50%;
            background: #e5e7eb;
            display: flex; align-items: center; justify-content: center;
            font-size: .75rem; font-weight: 700;
        }
        .step.active .step-num { background: var(--primary); color: #fff; }
        .step.done   .step-num { background: var(--success); color: #fff; }
        .step-line { flex: 1; height: 2px; background: #e5e7eb; min-width: 30px; }
        .step-line.done { background: var(--success); }

        /* ── PREVIEW TABLE ── */
        #previewSection { display: none; }
        .preview-table-wrap {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
        }
        .preview-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .8rem;
        }
        .preview-table thead th {
            background: #f1f5f9;
            padding: .55rem .6rem;
            text-align: left;
            font-weight: 600;
            font-size: .72rem;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #6b7280;
            border-bottom: 1px solid #e5e7eb;
            white-space: nowrap;
        }
        .preview-table tbody td {
            padding: .35rem .5rem;
            border-bottom: 1px solid #f0f2f5;
            vertical-align: middle;
        }
        .preview-table tbody tr:last-child td { border-bottom: none; }
        .preview-table tbody tr:hover td { background: #fafbfe; }
        .preview-table input {
            border: 1px solid transparent;
            background: transparent;
            padding: .2rem .35rem;
            border-radius: 5px;
            font-size: .8rem;
            width: 100%;
            font-family: inherit;
            color: inherit;
            transition: all .15s;
        }
        .preview-table input:focus {
            outline: none;
            border-color: var(--primary);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(26,86,219,.12);
        }
        .preview-table .name-input { min-width: 180px; }
        .preview-table .score-input { width: 60px; text-align: center; }
        .preview-table .grade-input { width: 45px; text-align: center; }
        .preview-table .del-btn {
            background: none; border: none; color: #e53e3e;
            padding: .15rem .3rem; cursor: pointer; border-radius: 4px;
            transition: all .15s;
        }
        .preview-table .del-btn:hover { background: #fff5f5; }

        /* ── META FIELDS ── */
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: .75rem;
        }
        .meta-grid .form-label { font-size: .75rem; font-weight: 600; color: #6b7280; margin-bottom: .2rem; }
        .meta-grid .form-control {
            font-size: .82rem;
            padding: .4rem .65rem;
            border-radius: 8px;
            border-color: #d1d5db;
        }

        /* ── STATUS BADGES ── */
        .badge-pdf   { background: #dbeafe; color: #1e40af; }
        .badge-image { background: #d1fae5; color: #065f46; }

        /* ── LOADING OVERLAY ── */
        #loadingOverlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(15, 23, 42, .55);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        #loadingOverlay.show { display: flex; }
        .loading-card {
            background: #fff;
            border-radius: 16px;
            padding: 2.5rem 3rem;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,.25);
        }
        .loading-card .spinner { width: 48px; height: 48px; margin: 0 auto 1rem; }
        .loading-card p { font-weight: 600; margin-bottom: .25rem; }
        .loading-card small { color: #6b7280; font-size: .8rem; }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform .25s; }
            .sidebar.open { transform: translateX(0); }
            .main-wrap { margin-left: 0; }
            .upload-zone { padding: 2rem 1rem; }
        }
    </style>
    @stack('styles')
</head>
<body>

{{-- SIDEBAR --}}
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <h6>WAKATA Examinations</h6>
        <strong>Score Scanner</strong>
        <span>UCE Mock System</span>
    </div>
    <nav>
        <div class="nav-label">Main</div>
        <a href="{{ route('scan.index') }}" class="{{ request()->routeIs('scan.index') ? 'active' : '' }}">
            <i class="bi bi-upc-scan"></i> Scan Score Sheet
        </a>
        <a href="{{ route('scan.records') }}" class="{{ request()->routeIs('scan.records') ? 'active' : '' }}">
            <i class="bi bi-table"></i> View Records
        </a>
        <div class="nav-label mt-3">Help</div>
        <a href="#">
            <i class="bi bi-question-circle"></i> How to Scan
        </a>
    </nav>
</aside>

{{-- MAIN --}}
<div class="main-wrap">
    <div class="topbar">
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-light d-md-none" onclick="document.getElementById('sidebar').classList.toggle('open')">
                <i class="bi bi-list"></i>
            </button>
            <h1>@yield('title', 'Score Scanner')</h1>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-light text-muted border" style="font-size:.7rem">
                <i class="bi bi-calendar3"></i> {{ now()->format('M d, Y') }}
            </span>
        </div>
    </div>

    <div class="content-area">
        @yield('content')
    </div>
</div>

{{-- LOADING OVERLAY --}}
<div id="loadingOverlay">
    <div class="loading-card">
        <div class="spinner">
            <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="24" cy="24" r="20" stroke="#e5e7eb" stroke-width="4"/>
                <path d="M24 4a20 20 0 0 1 20 20" stroke="#1a56db" stroke-width="4" stroke-linecap="round">
                    <animateTransform attributeName="transform" type="rotate" from="0 24 24" to="360 24 24" dur=".9s" repeatCount="indefinite"/>
                </path>
            </svg>
        </div>
        <p id="loadingTitle">Scanning document…</p>
        <small id="loadingSubtitle">Claude AI is extracting score data from your file</small>
    </div>
</div>

{{-- JS LIBS --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // Global helpers
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    function showLoading(title = 'Scanning document…', subtitle = 'Claude AI is extracting score data from your file') {
        document.getElementById('loadingTitle').textContent = title;
        document.getElementById('loadingSubtitle').textContent = subtitle;
        document.getElementById('loadingOverlay').classList.add('show');
    }
    function hideLoading() {
        document.getElementById('loadingOverlay').classList.remove('show');
    }

    async function apiPost(url, data) {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: data,
        });
        return res.json();
    }
</script>

@stack('scripts')
</body>
</html>
