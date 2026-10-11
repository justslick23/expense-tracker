
@extends('layouts.shell')

@section('content')
<div class="container py-4">

    <div class="d-flex flex-wrap justify-content-between
                align-items-center gap-3 mb-4">

        <div>
            <h3 class="fw-bold mb-1">Receipts</h3>
            <p class="text-body-secondary mb-0">
                Manage uploaded, scanned and confirmed receipts.
            </p>
        </div>

        <a href="{{ route('receipts.create') }}"
           class="btn btn-primary">
            <i class="fas fa-camera me-2"></i>
            Scan Receipt
        </a>
    </div>

    @if($receipts->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">

                <i class="fas fa-receipt fa-3x text-body-secondary mb-3"></i>

                <h5 class="fw-bold">No Receipts Yet</h5>

                <p class="text-body-secondary">
                    Upload your first receipt and let Gemini
                    extract the transaction details.
                </p>

                <a href="{{ route('receipts.create') }}"
                   class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>
                    Add Receipt
                </a>

            </div>
        </div>
    @else
        <div class="row g-3">

            @foreach($receipts as $receipt)
                @php
                    $colors = [
                        'uploaded' => 'secondary',
                        'processing' => 'primary',
                        'processed' => 'warning',
                        'failed' => 'danger',
                        'confirmed' => 'success',
                    ];

                    $color = $colors[$receipt->status] ?? 'secondary';
                @endphp

                <div class="col-md-6 col-xl-4">
                    <div class="card border-0 shadow-sm h-100">

                        <a href="{{ route('receipts.show', $receipt) }}"
                           class="text-decoration-none">

                            <div class="text-center p-3"
                                 style="background: var(--bs-tertiary-bg);">

                                <img
                                    src="{{ route('receipts.image', $receipt) }}"
                                    alt="Receipt preview"
                                    class="img-fluid rounded"
                                    loading="lazy"
                                    style="
                                        height: 180px;
                                        width: 100%;
                                        object-fit: contain;
                                    "
                                >
                            </div>
                        </a>

                        <div class="card-body">

                            <div class="d-flex align-items-center
                                        justify-content-between gap-2 mb-3">

                                <span class="badge text-bg-{{ $color }}">
                                    {{ ucfirst($receipt->status) }}
                                </span>

                                <small class="text-body-secondary">
                                    {{ $receipt->created_at->format('d M Y') }}
                                </small>
                            </div>

                            <h6 class="fw-bold mb-2">
                                {{ $receipt->merchant ?: 'Unscanned Receipt' }}
                            </h6>

                            <div class="text-body-secondary small mb-3">
                                @if($receipt->transaction_date)
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    {{ $receipt->transaction_date->format('d M Y') }}
                                @else
                                    Awaiting transaction date
                                @endif
                            </div>

                            @if($receipt->total_amount !== null)
                                <div class="fs-5 fw-bold mb-3">
                                    {{ $receipt->currency }}
                                    {{ \App\Support\Money::format(
                                        $receipt->total_amount
                                    ) }}
                                </div>
                            @endif

                            @if($receipt->status === 'confirmed')
                                <div class="small text-success mb-3">
                                    <i class="fas fa-check-circle me-1"></i>
                                    {{ $receipt->expenses_count }}
                                    expense allocation(s)
                                </div>
                            @endif

                            <div class="d-grid gap-2">

                                @if($receipt->status === 'processed')
                                    <a
                                        href="{{ route('receipts.review', $receipt) }}"
                                        class="btn btn-primary btn-sm"
                                    >
                                        <i class="fas fa-clipboard-check me-1"></i>
                                        Review & Confirm
                                    </a>
                                @else
                                    <a
                                        href="{{ route('receipts.show', $receipt) }}"
                                        class="btn btn-outline-secondary btn-sm"
                                    >
                                        <i class="fas fa-eye me-1"></i>
                                        View Receipt
                                    </a>
                                @endif

                            </div>

                        </div>
                    </div>
                </div>
            @endforeach

        </div>

        <div class="mt-4">
            {{ $receipts->links() }}
        </div>
    @endif

</div>
@endsection
