@extends('layouts.shell')

@section('content')

@php

    $items = $receipt->items ?? [];

@endphp

<div class="container py-4">

    <div class="d-flex flex-wrap justify-content-between

                align-items-center gap-3 mb-4">

        <div>

            <a href="{{ route('receipts.show', $receipt) }}"

               class="text-decoration-none small">

                <i class="fas fa-arrow-left me-1"></i>

                Back to Receipt

            </a>

            <h3 class="fw-bold mb-1 mt-2">

                Review Receipt

            </h3>

            <p class="text-body-secondary mb-0">

                Verify Gemini's extracted details before creating expenses.

            </p>

        </div>

        <span class="badge text-bg-warning">

            Awaiting Confirmation

        </span>

    </div>

    @if(session('success'))

        <div class="alert alert-success">

            {{ session('success') }}

        </div>

    @endif

    @if($errors->any())

        <div class="alert alert-danger">

            <strong>Please correct the following:</strong>

            <ul class="mb-0 mt-2">

                @foreach($errors->all() as $error)

                    <li>{{ $error }}</li>

                @endforeach

            </ul>

        </div>

    @endif

    <div class="row g-4">

        {{-- Original Receipt --}}

        <div class="col-lg-5">

            <div class="card border-0 shadow-sm">

                <div class="card-body p-3">

                    <h5 class="fw-bold mb-3">

                        Original Receipt

                    </h5>

                    <div class="rounded-3 p-2 text-center"

                         style="background: var(--bs-tertiary-bg);">

                        <img

                            src="{{ route('receipts.image', $receipt) }}"

                            alt="Original receipt"

                            class="img-fluid rounded"

                            style="width: 100%; max-height: 750px; object-fit: contain;"

                        >

                    </div>

                </div>

            </div>

        </div>

        {{-- Editable Receipt --}}

        <div class="col-lg-7">

            <form

                method="POST"

                action="{{ route('receipts.review.update', $receipt) }}"

                id="receiptReviewForm"

            >

                @csrf

                @method('PUT')

                {{-- Transaction Information --}}

                <div class="card border-0 shadow-sm mb-4">

                    <div class="card-body p-4">

                        <h5 class="fw-bold mb-4">

                            Transaction Information

                        </h5>

                        <div class="mb-3">

                            <label for="merchant" class="form-label">

                                Merchant

                            </label>

                            <input

                                type="text"

                                name="merchant"

                                id="merchant"

                                class="form-control"

                                value="{{ old('merchant', $receipt->merchant) }}"

                                required

                            >

                        </div>

                        <div class="row g-3">

                            <div class="col-sm-6">

                                <label for="transaction_date"

                                       class="form-label">

                                    Transaction Date

                                </label>

                                <input

                                    type="date"

                                    name="transaction_date"

                                    id="transaction_date"

                                    class="form-control"

                                    value="{{ old(

                                        'transaction_date',

                                        $receipt->transaction_date?->format('Y-m-d')

                                    ) }}"

                                    required

                                >

                            </div>

                            <div class="col-sm-6">

                                <label for="total_amount"

                                       class="form-label">

                                    Receipt Total (M)

                                </label>

                                <input

                                    type="text"

                                    inputmode="decimal"

                                    name="total_amount"

                                    id="total_amount"

                                    class="form-control"

                                    value="{{ old(

                                        'total_amount',

                                        $receipt->total_amount !== null

                                            ? number_format(

                                                $receipt->total_amount / 100,

                                                2,

                                                '.',

                                                ''

                                            )

                                            : ''

                                    ) }}"

                                    placeholder="0.00"

                                    required

                                >

                            </div>

                        </div>

                    </div>

                </div>

                {{-- Receipt Items --}}

                <div class="card border-0 shadow-sm mb-4">

                    <div class="card-body p-4">

                        <h5 class="fw-bold mb-2">

                            Purchased Items & Discounts

                        </h5>

                        <p class="text-body-secondary small mb-4">

                            Edit each description, amount and category.

                            Negative discount amounts reduce the allocation.

                        </p>

                        @foreach($items as $index => $item)

                            @php

                                $type = $item['entry_type'] ?? 'other';

                                $selectedCategory = old(

                                    "items.$index.category_id",

                                    $item['category_id']

                                        ?? $item['suggested_category_id']

                                        ?? ''

                                );

                                $value = old(

                                    "items.$index.total",

                                    $item['total'] ?? ''

                                );

                                $relatedIndex = $item['related_item_index']

                                    ?? null;

                                $relatedName = (

                                    $relatedIndex !== null &&

                                    isset($items[$relatedIndex])

                                )

                                    ? ($items[$relatedIndex]['name'] ?? null)

                                    : null;

                            @endphp

                            <div class="border rounded-3 p-3 mb-3 receipt-item"

                                 data-type="{{ $type }}">

                                <div class="d-flex justify-content-between

                                            align-items-center mb-3">

                                    <div class="fw-semibold">

                                        Entry {{ $index + 1 }}

                                    </div>

                                    <span class="badge text-bg-{{

                                        $type === 'discount'

                                            ? 'success'

                                            : ($type === 'product'

                                                ? 'primary'

                                                : 'secondary')

                                    }}">

                                        {{ ucfirst($type) }}

                                    </span>

                                </div>

                                <div class="mb-3">

                                    <label class="form-label">

                                        Item Description

                                    </label>

                                    <input

                                        type="text"

                                        name="items[{{ $index }}][name]"

                                        class="form-control"

                                        value="{{ old(

                                            "items.$index.name",

                                            $item['name'] ?? ''

                                        ) }}"

                                        required

                                    >

                                    <div class="form-text">

                                        Original:

                                        {{ $item['raw_name'] ?? 'Unknown' }}

                                    </div>

                                    @if($relatedName && $type === 'discount')

                                        <div class="form-text">

                                            <i class="fas fa-link me-1"></i>

                                            Linked to: {{ $relatedName }}

                                        </div>

                                    @endif

                                </div>

                                <div class="row g-3">

                                    <div class="col-sm-7">

                                        <label class="form-label">

                                            Expense Category

                                        </label>

                                        <select

                                            name="items[{{ $index }}][category_id]"

                                            class="form-select"

                                        >

                                            <option value="">

                                                Select category

                                            </option>

                                            @foreach($categories as $category)

                                                <option

                                                    value="{{ $category->id }}"

                                                    @selected(

                                                        (string) $selectedCategory ===

                                                        (string) $category->id

                                                    )

                                                >

                                                    {{ $category->name }}

                                                </option>

                                            @endforeach

                                        </select>

                                    </div>

                                    <div class="col-sm-5">

                                        <label class="form-label">

                                            Amount (M)

                                        </label>

                                        <input

                                            type="text"

                                            inputmode="decimal"

                                            name="items[{{ $index }}][total]"

                                            class="form-control item-amount"

                                            value="{{ $value }}"

                                            placeholder="{{ $type === 'discount'

                                                ? '-0.00'

                                                : '0.00' }}"

                                            required

                                        >

                                    </div>

                                </div>

                                @if(($item['confidence'] ?? '') === 'low')

                                    <div class="text-warning small mt-3">

                                        <i class="fas fa-exclamation-triangle me-1"></i>

                                        Gemini was uncertain about this item.

                                    </div>

                                @endif

                            </div>

                        @endforeach

                    </div>

                </div>

                {{-- Reconciliation --}}

                <div class="card border-0 shadow-sm mb-4">

                    <div class="card-body p-4">

                        <h5 class="fw-bold mb-3">

                            Receipt Reconciliation

                        </h5>

                        <div class="d-flex justify-content-between mb-3">

                            <span class="text-body-secondary">

                                Sum of reviewed items

                            </span>

                            <strong id="reviewedTotal">

                                M 0.00

                            </strong>

                        </div>

                        <div class="d-flex justify-content-between mb-3">

                            <span class="text-body-secondary">

                                Receipt total

                            </span>

                            <strong id="receiptTotalDisplay">

                                M 0.00

                            </strong>

                        </div>

                        <hr>

                        <div class="d-flex justify-content-between mb-3">

                            <strong>Difference</strong>

                            <strong id="differenceDisplay">

                                M 0.00

                            </strong>

                        </div>

                        <div

                            id="reconciliationMessage"

                            class="alert alert-warning small mb-0"

                        >

                            Review the amounts before continuing.

                        </div>

                    </div>

                </div>

                {{-- Save Draft --}}

                <button

                    type="submit"

                    id="saveReviewButton"

                    class="btn btn-primary w-100 py-3 fw-semibold"

                >

                    <i class="fas fa-save me-2"></i>

                    Save Reviewed Receipt

                </button>

                <p class="text-center text-body-secondary small mt-3">

                    This saves your corrections only.

                    No financial transactions will be created yet.

                </p>

            </form>

                @if($receipt->status === 'processed'
                    && $receipt->expense_id === null
                    && !$receipt->expenses()->exists())
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-2">Confirm Receipt</h5>
                            <p class="text-body-secondary small">
                                Save your reviewed changes above first. Once confirmed,
                                your items will be recorded as expense allocations.
                            </p>
                            <form method="POST"
                                  action="{{ route('receipts.confirm', $receipt) }}"
                                  id="confirmReceiptForm">
                                @csrf
                                <button type="submit"
                                        id="confirmReceiptButton"
                                        class="btn btn-success w-100 py-3 fw-semibold">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Confirm &amp; Save Expenses
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

        </div>

    </div>

</div>

<script>

document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('receiptReviewForm');

    const amountInputs = document.querySelectorAll('.item-amount');

    const totalInput = document.getElementById('total_amount');

    const reviewedTotal = document.getElementById('reviewedTotal');

    const receiptTotalDisplay = document.getElementById('receiptTotalDisplay');

    const differenceDisplay = document.getElementById('differenceDisplay');

    const message = document.getElementById('reconciliationMessage');

    function parseMoney(value) {

        const text = String(value).trim();

        if (!/^-?\d+\.\d{2}$/.test(text)) {

            return null;

        }

        const negative = text.startsWith('-');

        const unsigned = negative ? text.substring(1) : text;

        const [whole, cents] = unsigned.split('.');

        const result = Number(whole) * 100 + Number(cents);

        return negative ? -result : result;

    }

    function formatMoney(cents) {

        const negative = cents < 0 ? '-' : '';

        return negative + 'M ' +

            (Math.abs(cents) / 100).toFixed(2);

    }

    function calculateTotals() {

        let sum = 0;

        let invalid = false;

        amountInputs.forEach(function (input) {

            const cents = parseMoney(input.value);

            if (cents === null || !Number.isSafeInteger(cents)) {

                invalid = true;

                return;

            }

            sum += cents;

        });

        const receiptCents = parseMoney(totalInput.value);

        reviewedTotal.textContent = invalid

            ? 'Incomplete'

            : formatMoney(sum);

        receiptTotalDisplay.textContent = receiptCents === null

            ? 'Invalid amount'

            : formatMoney(receiptCents);

        if (

            invalid ||

            receiptCents === null ||

            !Number.isSafeInteger(sum)

        ) {

            differenceDisplay.textContent = '—';

            message.className = 'alert alert-warning small mb-0';

            message.textContent =

                'Some amounts are missing or invalid.';

            return;

        }

        const difference = receiptCents - sum;

        differenceDisplay.textContent = formatMoney(difference);

        if (difference === 0) {

            message.className = 'alert alert-success small mb-0';

            message.textContent =

                'Amounts match the receipt total.';

        } else {

            message.className = 'alert alert-warning small mb-0';

            message.textContent =

                'There is a difference between the item amounts ' +

                'and the receipt total. Review before confirmation.';

        }

    }

    amountInputs.forEach(function (input) {

        input.addEventListener('input', calculateTotals);

    });

    totalInput.addEventListener('input', calculateTotals);

    calculateTotals();

    form.addEventListener('submit', function () {

        const button = document.getElementById('saveReviewButton');

        button.disabled = true;

        button.innerHTML =

            '<span class="spinner-border spinner-border-sm me-2"></span>' +

            'Saving Review...';

    });

    const confirmForm = document.getElementById('confirmReceiptForm');
    const confirmButton = document.getElementById('confirmReceiptButton');
    if (confirmForm && confirmButton) {
        confirmForm.addEventListener('submit', function (event) {
            if (!window.confirm('Confirm the saved receipt and create expense allocations?')) {
                event.preventDefault();
                return;
            }
            confirmButton.disabled = true;
            confirmButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving Expenses...';
        });
    }

});

</script>

@endsection
