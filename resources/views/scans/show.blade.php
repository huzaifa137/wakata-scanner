@extends('layouts.app')
@section('title', 'Score Sheet #' . $scoreSheet->id)

@section('content')

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="{{ route('scan.records') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <div>
        <h5 class="mb-0 fw-semibold">{{ $scoreSheet->school_name ?? 'Score Sheet' }} — #{{ $scoreSheet->id }}</h5>
        <p class="text-muted mb-0" style="font-size:.8rem">
            Scanned {{ $scoreSheet->created_at->format('d M Y, H:i') }} ·
            <span class="badge {{ $scoreSheet->scan_type === 'pdf' ? 'badge-pdf' : 'badge-image' }}">{{ strtoupper($scoreSheet->scan_type) }}</span>
        </p>
    </div>
    <div class="ms-auto d-flex gap-2">
        <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-printer me-1"></i> Print
        </button>
        <a href="{{ route('scan.export', $scoreSheet) }}" class="btn btn-sm btn-outline-success">
            <i class="bi bi-file-earmark-excel me-1"></i> Export to Excel
        </a>
    </div>
</div>

{{-- Sheet meta --}}
<div class="row g-3 mb-4">
    @php $meta = [
        ['label'=>'School Name', 'value'=>$scoreSheet->school_name,  'icon'=>'building'],
        ['label'=>'Zone',        'value'=>$scoreSheet->zone,         'icon'=>'map'],
        ['label'=>'REF No.',     'value'=>$scoreSheet->ref_no,       'icon'=>'hash'],
        ['label'=>'Subject',     'value'=>$scoreSheet->subject,      'icon'=>'book'],
        ['label'=>'Exam Year',   'value'=>$scoreSheet->exam_year,    'icon'=>'calendar3'],
        ['label'=>'Source File', 'value'=>$scoreSheet->source_file,  'icon'=>'file-earmark'],
    ]; @endphp
    @foreach($meta as $m)
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card text-center py-2 px-1">
            <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.07em;color:#9ca3af" class="mb-1">{{ $m['label'] }}</div>
            <div style="font-size:.82rem;font-weight:600;color:#1f2937">{{ $m['value'] ?? '—' }}</div>
        </div>
    </div>
    @endforeach
</div>

{{-- Entries table --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span>
            <i class="bi bi-table me-2"></i>Candidate Entries
            <span class="badge bg-primary ms-1">{{ $scoreSheet->entries->count() }}</span>
        </span>
    </div>
    <div class="card-body p-0">
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:.82rem" id="entriesTable">
                <thead>
                    <tr style="background:#f8fafc">
                        @foreach(['#','Candidate Name','P1','P2','P3','P4','Average','Grade'] as $col)
                        <th style="padding:.6rem .85rem;font-weight:600;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;border-bottom:1px solid #e5e7eb;white-space:nowrap;text-align:{{ in_array($col,['P1','P2','P3','P4','Average','Grade']) ? 'center' : 'left' }}">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($scoreSheet->entries->sortBy('serial_no') as $entry)
                    <tr>
                        <td style="padding:.55rem .85rem;border-bottom:1px solid #f0f2f5;color:#9ca3af;font-size:.75rem">{{ $entry->serial_no }}</td>
                        <td style="padding:.55rem .85rem;border-bottom:1px solid #f0f2f5;font-weight:500">{{ $entry->candidate_name }}</td>
                        @foreach(['p1','p2','p3','p4','average'] as $col)
                        <td style="padding:.55rem .85rem;border-bottom:1px solid #f0f2f5;text-align:center">
                            {{ $entry->$col !== null ? number_format($entry->$col, 1) : '—' }}
                        </td>
                        @endforeach
                        <td style="padding:.55rem .85rem;border-bottom:1px solid #f0f2f5;text-align:center">
                            @if($entry->grade)
                                <span class="badge bg-primary">{{ $entry->grade }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<style>
@media print {
    .sidebar, .topbar, .btn, .ms-auto { display: none !important; }
    .main-wrap { margin-left: 0 !important; }
}
</style>
@endpush
