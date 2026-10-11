
@php
    $b = $budget ?? null;

    $selectedPeriod = old(
        'financial_period_id',
        $b?->financial_period_id ?? ($selectedPeriodId ?? null)
    );

    $needsConfirm = $errors->has('confirm_closed')
        || old('confirm_closed')
        || ($b?->period && ! $b->period->isOpen());
@endphp

<form method="POST"
      action="{{ $b ? route('budgets.update', $b) : route('budgets.store') }}">

    @csrf

    @if ($b)
        @method('PUT')
    @endif

    <div class="mb-3">
        <label for="financial_period_id" class="form-label">
            Financial period
        </label>

        <select id="financial_period_id"
                name="financial_period_id"
                class="form-select @error('financial_period_id') is-invalid @enderror"
                required>

            <option value="">Select a financial period</option>

            @foreach ($periods as $period)
                <option value="{{ $period->id }}"
                        data-closed="{{ $period->isOpen() ? '0' : '1' }}"
                        @selected((string) $selectedPeriod === (string) $period->id)>
                    {{ $period->name }}
                    {{ $period->isOpen() ? '(Open)' : '(Closed)' }}
                </option>
            @endforeach
        </select>

        @error('financial_period_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="category_id" class="form-label">Expense category</label>

        <select id="category_id"
                name="category_id"
                class="form-select @error('category_id') is-invalid @enderror"
                required>

            <option value="">Select a category</option>

            @foreach ($categories as $category)
                <option value="{{ $category->id }}"
                        @selected(old('category_id', $b?->category_id) == $category->id)>
                    {{ $category->name }}
                    {{ $category->isArchived() ? '(archived)' : '' }}
                </option>
            @endforeach
        </select>

        @error('category_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="amount" class="form-label">Allocated amount</label>

        <div class="input-group has-validation">
            <span class="input-group-text">M</span>

            <input id="amount"
                   name="amount"
                   inputmode="decimal"
                   value="{{ old('amount', $b ? \App\Support\Money::plain($b->amount) : '') }}"
                   class="form-control num @error('amount') is-invalid @enderror"
                   placeholder="0.00"
                   required>

            @error('amount')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-text">
            The amount you plan to spend in this category during the selected period.
        </div>
    </div>

    <div id="closed-period-warning"
         class="alert alert-warning {{ $needsConfirm ? '' : 'd-none' }}">

        <div class="form-check">
            <input type="checkbox"
                   id="confirm_closed"
                   name="confirm_closed"
                   value="1"
                   class="form-check-input @error('confirm_closed') is-invalid @enderror"
                   @checked(old('confirm_closed'))>

            <label for="confirm_closed" class="form-check-label">
                This change affects a closed financial period.
                I understand and want to proceed.
            </label>
        </div>

        @error('confirm_closed')
            <div class="text-danger small mt-2">{{ $message }}</div>
        @enderror
    </div>

    <div class="d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-check me-1"></i>
            {{ $b ? 'Save changes' : 'Create allocation' }}
        </button>

        <a href="{{ route('budgets.index') }}"
           class="btn btn-outline-secondary">
            Cancel
        </a>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const periodSelect = document.getElementById('financial_period_id');
    const warning = document.getElementById('closed-period-warning');
    const checkbox = document.getElementById('confirm_closed');

    if (!periodSelect || !warning) return;

    const originalPeriodId = @json($b?->financial_period_id);
    const originalPeriodClosed = @json(
        $b?->period && ! $b->period->isOpen()
    );

    function updateWarning() {
        const option = periodSelect.selectedOptions[0];
        const selectedClosed = option?.dataset.closed === '1';

        const affectsClosedPeriod = selectedClosed || originalPeriodClosed;

        warning.classList.toggle('d-none', !affectsClosedPeriod);

        if (!affectsClosedPeriod && checkbox) {
            checkbox.checked = false;
        }
    }

    periodSelect.addEventListener('change', updateWarning);
    updateWarning();
});
</script>
@endpush
