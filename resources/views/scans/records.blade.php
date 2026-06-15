@extends('layouts.app')
@section('title', 'Saved Records')

@section('content')

<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h5 class="mb-0 fw-semibold">All Score Sheets</h5>
        <p class="text-muted mb-0" style="font-size:.82rem">All scanned and saved score sheets</p>
    </div>
    <a href="{{ route('scan.index') }}" class="btn btn-primary btn-sm">
        <i class="bi bi-upc-scan me-1"></i> Scan New Sheet
    </a>
</div>

{{-- Search --}}
<div class="card mb-4">
    <div class="card-body py-2 px-3">
        <form method="GET" class="d-flex gap-2 align-items-center">
            <i class="bi bi-search text-muted"></i>
            <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm border-0 shadow-none" placeholder="Search by school, subject, zone, ref no…" style="font-size:.85rem">
            <button class="btn btn-sm btn-primary px-3">Search</button>
            @if(request('search'))
                <a href="{{ route('scan.records') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        @if($sheets->count())
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:.82rem">
                <thead>
                    <tr style="background:#f8fafc">
                        <th style="padding:.65rem 1rem;font-weight:600;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;border-bottom:1px solid #e5e7eb">ID</th>
                        <th style="padding:.65rem 1rem;font-weight:600;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;border-bottom:1px solid #e5e7eb">School</th>
                        <th style="padding:.65rem 1rem;font-weight:600;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;border-bottom:1px solid #e5e7eb">Subject</th>
                        <th style="padding:.65rem 1rem;font-weight:600;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;border-bottom:1px solid #e5e7eb">Zone</th>
                        <th style="padding:.65rem 1rem;font-weight:600;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;border-bottom:1px solid #e5e7eb">REF No.</th>
                        <th style="padding:.65rem 1rem;font-weight:600;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;border-bottom:1px solid #e5e7eb">Entries</th>
                        <th style="padding:.65rem 1rem;font-weight:600;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;border-bottom:1px solid #e5e7eb">Type</th>
                        <th style="padding:.65rem 1rem;font-weight:600;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;border-bottom:1px solid #e5e7eb">Date</th>
                        <th style="padding:.65rem 1rem;font-weight:600;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;border-bottom:1px solid #e5e7eb">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sheets as $sheet)
                    <tr class="record-row" data-id="{{ $sheet->id }}">
                        <td style="padding:.6rem 1rem;border-bottom:1px solid #f0f2f5;color:#9ca3af">#{{ $sheet->id }}</td>
                        <td style="padding:.6rem 1rem;border-bottom:1px solid #f0f2f5;font-weight:500">{{ $sheet->school_name ?? '—' }}</td>
                        <td style="padding:.6rem 1rem;border-bottom:1px solid #f0f2f5">{{ $sheet->subject ?? '—' }}</td>
                        <td style="padding:.6rem 1rem;border-bottom:1px solid #f0f2f5">{{ $sheet->zone ?? '—' }}</td>
                        <td style="padding:.6rem 1rem;border-bottom:1px solid #f0f2f5">{{ $sheet->ref_no ?? '—' }}</td>
                        <td style="padding:.6rem 1rem;border-bottom:1px solid #f0f2f5">
                            <span class="badge bg-primary">{{ $sheet->entries_count }}</span>
                        </td>
                        <td style="padding:.6rem 1rem;border-bottom:1px solid #f0f2f5">
                            <span class="badge {{ $sheet->scan_type === 'pdf' ? 'badge-pdf' : 'badge-image' }}" style="font-size:.7rem">
                                <i class="bi bi-{{ $sheet->scan_type === 'pdf' ? 'file-earmark-pdf' : 'camera' }} me-1"></i>
                                {{ strtoupper($sheet->scan_type) }}
                            </span>
                        </td>
                        <td style="padding:.6rem 1rem;border-bottom:1px solid #f0f2f5;color:#9ca3af;font-size:.75rem">
                            {{ $sheet->created_at->format('d M Y') }}
                        </td>
                        <td style="padding:.6rem 1rem;border-bottom:1px solid #f0f2f5">
                            <div class="d-flex gap-1">
                                <a href="{{ route('scan.show', $sheet) }}" class="btn btn-sm btn-outline-primary" style="font-size:.72rem;padding:.2rem .5rem">
                                    <i class="bi bi-eye"></i> View
                                </a>
                                <button onclick="deleteSheet({{ $sheet->id }})" class="btn btn-sm btn-outline-danger" style="font-size:.72rem;padding:.2rem .5rem">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-3 py-2">
            {{ $sheets->links() }}
        </div>
        @else
        <div class="text-center py-5">
            <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
            <p class="fw-semibold text-muted mb-1">No records yet</p>
            <p class="text-muted" style="font-size:.82rem">Scan your first score sheet to get started</p>
            <a href="{{ route('scan.index') }}" class="btn btn-primary btn-sm mt-2">
                <i class="bi bi-upc-scan me-1"></i> Scan Now
            </a>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
async function deleteSheet(id) {
    const confirm = await Swal.fire({
        title: 'Delete this record?',
        text:  'This will permanently remove the score sheet and all its entries.',
        icon:  'warning',
        showCancelButton:  true,
        confirmButtonText: 'Yes, Delete',
        confirmButtonColor:'#c81e1e',
    });
    if (!confirm.isConfirmed) return;

    const res = await fetch(`/records/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
    });
    const result = await res.json();
    if (result.success) {
        document.querySelector(`[data-id="${id}"]`)?.remove();
        Swal.fire('Deleted', 'Record removed.', 'success');
    }
}
</script>
@endpush
