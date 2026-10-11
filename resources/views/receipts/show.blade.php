
@extends('layouts.shell')

@section('content')
@php
    $categoryNames = auth()->user()
        ->categories()
        ->where('type', 'expense')
        ->pluck('name', 'id');
@endphp
<div class="container py-4">

    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between
                align-items-center gap-3 mb-4">

        <div>
            <h3 class="fw-bold mb-1">Receipt Details</h3>
            <p class="text-body-secondary mb-0">
                Uploaded
                {{ $receipt->created_at->format('d M Y, H:i') }}
            </p>
        </div>

        <a href="{{ route('receipts.create') }}"
           class="btn btn-outline-secondary">
            <i class="fas fa-plus me-2"></i>
            New Receipt
        </a>
    </div>

    {{-- Messages --}}
    @if(session('success'))
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i>
            {{ $errors->first() }}
        </div>
    @endif

    <div class="row g-4">

        {{-- Receipt Image --}}
        <div class="col-lg-6">

            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">

                    <div class="d-flex justify-content-between
                                align-items-center mb-3">
                        <h5 class="fw-bold mb-0">
                            Original Receipt
                        </h5>

                        <span class="badge text-bg-secondary">
                            {{ strtoupper(
                                pathinfo(
                                    $receipt->original_filename,
                                    PATHINFO_EXTENSION
                                )
                            ) }}
                        </span>
                    </div>

                    <div class="text-center rounded-3 p-2"
                         style="background: var(--bs-tertiary-bg);">

                        <img
                            src="{{ route('receipts.image', $receipt) }}"
                            alt="Original receipt image"
                            class="img-fluid rounded"
                            style="
                                max-height: 800px;
                                width: 100%;
                                object-fit: contain;
                            "
                        >
                    </div>

                    <p class="text-body-secondary small mt-3 mb-0 text-break">
                        {{ $receipt->original_filename }}
                    </p>

                </div>
            </div>

        </div>

        {{-- Analysis --}}
        <div class="col-lg-6">

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">

                    <div class="d-flex justify-content-between
                                align-items-center mb-3">

                        <h5 class="fw-bold mb-0">
                            Receipt Information
                        </h5>

                        @php
                            $statusColors = [
                                'uploaded' => 'secondary',
                                'processing' => 'primary',
                                'processed' => 'success',
                                'failed' => 'danger',
                                'confirmed' => 'success',
                            ];
                        @endphp

                        <span class="badge text-bg-{{
                            $statusColors[$receipt->status] ?? 'secondary'
                        }}">
                            {{ ucfirst($receipt->status) }}
                        </span>

                    </div>

                    @if(in_array($receipt->status, ['processed', 'confirmed']))

                        {{-- Merchant --}}
                        <div class="mb-3">
                            <div class="text-body-secondary small">
                                Merchant
                            </div>

                            <div class="fw-semibold fs-5">
                                {{ $receipt->merchant ?? 'Not detected' }}
                            </div>
                        </div>

                        {{-- Date --}}
                        <div class="mb-3">
                            <div class="text-body-secondary small">
                                Transaction Date
                            </div>

                            <div class="fw-semibold">
                                {{ $receipt->transaction_date
                                    ? $receipt->transaction_date->format('d M Y')
                                    : 'Not detected' }}
                            </div>
                        </div>

                        {{-- Receipt Total --}}
                        <div class="rounded-3 p-3 mb-3"
                             style="background: var(--bs-tertiary-bg);">

                            <div class="text-body-secondary small mb-1">
                                Total Paid
                            </div>

                            <div class="fs-3 fw-bold">
                                @if($receipt->total_amount !== null)
                                    {{ $receipt->currency }}
                                    {{ \App\Support\Money::format(
                                        $receipt->total_amount
                                    ) }}
                                @else
                                    Not detected
                                @endif
                            </div>
                        </div>

                        <div class="alert alert-info small mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            These details were extracted by Gemini.
                            Check them against the original receipt.
                            No expense has been created yet.
                        </div>

                    @elseif($receipt->status === 'processing')

                        <div class="alert alert-info mb-0">
                            Gemini is processing your receipt.
                            Refresh this page shortly.
                        </div>

                    @else

                        <div class="alert alert-info mb-0">
                            Your receipt is ready for AI scanning.
                            Gemini will identify products, discounts
                            and transaction details.
                        </div>

                    @endif

                    {{-- Gemini Scan Button --}}
                    @if(
                        $receipt->expense_id === null &&
                        !in_array($receipt->status, [
                            'confirmed',
                            'processing'
                        ])
                    )
                        <form
                            action="{{ route('receipts.scan', $receipt) }}"
                            method="POST"
                            class="mt-3"
                            id="geminiScanForm"
                        >
                            @csrf

                            <button
                                type="submit"
                                id="geminiScanButton"
                                class="btn btn-primary w-100 py-3 fw-semibold"
                            >
                                <i class="fas fa-magic me-2"></i>

                                {{ $receipt->status === 'processed'
                                    ? 'Scan Again with Gemini'
                                    : 'Scan with Gemini' }}
                            </button>
                        </form>

                        
<div class="card border-0 shadow-sm mt-4">
    <div class="card-body p-4">

        <h5 class="fw-bold mb-2">
            Confirm Expense Allocations
        </h5>

        <p class="text-body-secondary small">
            Once confirmed, the reviewed amounts will be
            added to your financial period, budget calculations
            and expense reports.
        </p>

        <div class="alert alert-warning small">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Save your reviewed changes first.

            Every receipt item and discount must have
            a valid category, and the allocated total
            must match the receipt total.
        </div>

        <form
            action="{{ route('receipts.confirm', $receipt) }}"
            method="POST"
            id="confirmReceiptForm"
        >
            @csrf

            <button
                type="submit"
                class="btn btn-success w-100 py-3 fw-semibold"
                id="confirmReceiptButton"
            >
                <i class="fas fa-check-circle me-2"></i>
                Confirm & Save Expenses
            </button>
        </form>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('confirmReceiptForm');
    const button = document.getElementById('confirmReceiptButton');

    if (!form || !button) {
        return;
    }

    form.addEventListener('submit', function (event) {
        const approved = window.confirm(
            'Confirm this receipt and create the expense allocations?'
        );

        if (!approved) {
            event.preventDefault();
            return;
        }

        button.disabled = true;

        button.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2"></span>' +
            'Saving Expenses...';
    });
});
</script>

                    @endif
                    @if($receipt->status === 'processed')
                    <a
                        href="{{ route('receipts.review', $receipt) }}"
                        class="btn btn-success w-100 py-3 mt-3 fw-semibold"
                    >
                        <i class="fas fa-clipboard-check me-2"></i>
                        Review & Allocate Categories
                    </a>
                @endif

                @if($receipt->status === 'confirmed')
    <div class="alert alert-success mt-3 mb-0">
        <i class="fas fa-check-circle me-2"></i>

        Receipt confirmed successfully.

        <div class="small mt-2">
            {{ $receipt->expenses()->count() }}
            expense allocation(s) created.
        </div>
    </div>
@endif
                </div>
            </div>

            {{-- Purchased Items --}}
            @if(
                in_array($receipt->status, ['processed', 'confirmed']) &&
                is_array($receipt->items) &&
                count($receipt->items) > 0
            )

                @php
                    $receiptItems = $receipt->items;

                    $productCount = collect($receiptItems)
                        ->where('entry_type', 'product')
                        ->count();

                    $discountCount = collect($receiptItems)
                        ->where('entry_type', 'discount')
                        ->count();

                    $signedLineTotal = 0;
                    $hasUnknownAmounts = false;

                    foreach ($receiptItems as $entry) {
                        $value = $entry['total'] ?? null;

                        if (
                            $value === null ||
                            !preg_match(
                                '/^-?\d+\.\d{2}$/',
                                (string) $value
                            )
                        ) {
                            $hasUnknownAmounts = true;
                            continue;
                        }

                        $negative = str_starts_with($value, '-');
                        $unsigned = ltrim($value, '-');

                        [$whole, $cents] = explode('.', $unsigned);

                        $minor = ((int) $whole * 100) + (int) $cents;

                        $signedLineTotal += $negative ? -$minor : $minor;
                    }

                    $difference = $receipt->total_amount !== null
                        ? $receipt->total_amount - $signedLineTotal
                        : null;
                @endphp

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">

                        <div class="d-flex justify-content-between
                                    align-items-center mb-3">

                            <div>
                                <h5 class="fw-bold mb-1">
                                    Receipt Items
                                </h5>

                                <p class="text-body-secondary small mb-0">
                                    {{ $productCount }} products,
                                    {{ $discountCount }} discounts
                                </p>
                            </div>

                        </div>

                        <div class="list-group list-group-flush">

                            @foreach($receiptItems as $index => $item)

                                @php
                                    $type = $item['entry_type'] ?? 'other';

                                    $amount = $item['total'] ?? null;

                                    $isNegative = $amount !== null &&
                                        str_starts_with(
                                            (string) $amount,
                                            '-'
                                        );

                                    $confidence = $item['confidence']
                                        ?? 'low';

                                    $relatedIndex = $item['related_item_index']
                                        ?? null;

                                    $relatedProduct = (
                                        $relatedIndex !== null &&
                                        isset($receiptItems[$relatedIndex]) &&
                                        ($receiptItems[$relatedIndex]['entry_type'] ?? '')
                                            === 'product'
                                    )
                                        ? $receiptItems[$relatedIndex]
                                        : null;

                                    $badgeColor = match ($type) {
                                        'product' => 'primary',
                                        'discount' => 'success',
                                        'refund' => 'warning',
                                        'adjustment' => 'secondary',
                                        default => 'secondary',
                                    };
                                @endphp

                                <div class="list-group-item
                                            bg-transparent px-0 py-3">

                                    <div class="d-flex justify-content-between
                                                align-items-start gap-3">

                                        <div class="flex-grow-1">

                                            <div class="fw-semibold">
                                                {{ $item['name'] ??
                                                    'Unidentified item' }}
                                            </div>

                                            <div class="small
                                                        text-body-secondary mt-1">
                                                Original:
                                                {{ $item['raw_name'] ?? '-' }}
                                            </div>

                                        </div>

                                        <div class="fw-bold text-nowrap
                                            {{ $isNegative ? 'text-success' : '' }}">
                                            @if($amount !== null)
                                                {{ $isNegative
                                                    ? '-M ' . substr($amount, 1)
                                                    : 'M ' . $amount }}
                                            @else
                                                —
                                            @endif
                                        </div>

                                    </div>

                                    <div class="d-flex flex-wrap
                                                gap-2 mt-3">

                                        <span class="badge text-bg-{{
                                            $badgeColor
                                        }}">
                                            {{ ucfirst($type) }}
                                        </span>

                                        @php
                                        $suggestedCategoryId =
                                            $item['suggested_category_id'] ?? null;
                                    
                                        $suggestedCategoryName =
                                            $categoryNames->get($suggestedCategoryId);
                                    @endphp
                                    
                                    @if($suggestedCategoryName)
                                        <span class="badge border text-body">
                                            <i class="fas fa-tag me-1"></i>
                                            {{ $suggestedCategoryName }}
                                        </span>
                                    @else
                                        <span class="badge text-bg-warning">
                                            <i class="fas fa-question-circle me-1"></i>
                                            Category needs review
                                        </span>
                                    @endif

                                        @if(!empty($item['quantity']))
                                            <span class="badge border text-body">
                                                Qty: {{ $item['quantity'] }}
                                            </span>
                                        @endif

                                        @if($confidence === 'low')
                                            <span class="badge text-bg-warning">
                                                Check Description
                                            </span>
                                        @elseif($confidence === 'medium')
                                            <span class="badge
                                                         text-bg-secondary">
                                                Review Suggested Name
                                            </span>
                                        @endif

                                    </div>

                                    @if($relatedProduct)
                                        <div class="small text-body-secondary mt-2">
                                            <i class="fas fa-link me-1"></i>
                                            Related to:
                                            {{ $relatedProduct['name'] ??
                                                'Purchased product' }}
                                        </div>
                                    @elseif($type === 'discount')
                                        <div class="small text-body-secondary mt-2">
                                            General or unassigned discount
                                        </div>
                                    @endif

                                </div>

                            @endforeach

                        </div>

                        {{-- Line Total Reconciliation --}}
                        <div class="rounded-3 p-3 mt-3"
                             style="background: var(--bs-tertiary-bg);">

                            <h6 class="fw-bold mb-3">
                                Receipt Reconciliation
                            </h6>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-body-secondary">
                                    Extracted line amounts
                                </span>

                                <span class="fw-semibold">
                                    M {{ number_format(
                                        $signedLineTotal / 100,
                                        2
                                    ) }}
                                </span>
                            </div>

                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-body-secondary">
                                    Receipt total
                                </span>

                                <span class="fw-semibold">
                                    @if($receipt->total_amount !== null)
                                        M {{ number_format(
                                            $receipt->total_amount / 100,
                                            2
                                        ) }}
                                    @else
                                        Not detected
                                    @endif
                                </span>
                            </div>

                            @if($difference !== null)

                                <hr class="my-2">

                                <div class="d-flex justify-content-between">
                                    <span class="fw-semibold">
                                        Difference
                                    </span>

                                    <span class="fw-bold
                                        {{ $difference === 0
                                            ? 'text-success'
                                            : 'text-warning' }}">
                                        M {{ number_format(
                                            $difference / 100,
                                            2
                                        ) }}
                                    </span>
                                </div>

                                @if($difference !== 0 || $hasUnknownAmounts)
                                    <p class="text-body-secondary small mt-2 mb-0">
                                        Review the receipt carefully.
                                        Some lines may be missing, unclear,
                                        or may represent tax or adjustments.
                                    </p>
                                @else
                                    <p class="text-success small mt-2 mb-0">
                                        Extracted amounts match the
                                        receipt total.
                                    </p>
                                @endif

                            @else
                                <p class="text-body-secondary small mt-2 mb-0">
                                    The final receipt total was not detected.
                                </p>
                            @endif

                        </div>

                        <div class="alert alert-info small mt-3 mb-0">
                            Category suggestions and discount associations
                            are not yet final. You'll approve them during
                            expense confirmation.
                        </div>

                    </div>
                </div>

            @endif

        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const scanForm = document.getElementById('geminiScanForm');
    const scanButton = document.getElementById('geminiScanButton');

    if (scanForm && scanButton) {
        scanForm.addEventListener('submit', function () {
            scanButton.disabled = true;

            scanButton.innerHTML =
                '<span class="spinner-border spinner-border-sm me-2"></span>' +
                'Analysing Receipt...';
        });
    }

});
</script>
@endsection
