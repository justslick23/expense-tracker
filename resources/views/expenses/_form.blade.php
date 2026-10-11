
@php
    $e = $expense ?? null;

    $needsConfirm = $errors->has('confirm_closed')
        || old('confirm_closed')
        || ($e?->period && ! $e->period->isOpen());
@endphp

<form
    method="POST"
    action="{{ $e ? route('expenses.update', $e) : route('expenses.store') }}"
>
    @csrf

    @if ($e)
        @method('PUT')
    @endif

    <div class="mb-3">
        <label for="category_id" class="form-label">Category</label>

        <select
            id="category_id"
            name="category_id"
            class="form-select @error('category_id') is-invalid @enderror"
            required
        >
            <option value="">Select a category</option>

            @foreach ($categories as $category)
                <option
                    value="{{ $category->id }}"
                    @selected(old('category_id', $e?->category_id) == $category->id)
                >
                    {{ $category->name }}
                    {{ $category->isArchived() ? '(archived)' : '' }}
                </option>
            @endforeach
        </select>

        @error('category_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror

        <div class="form-text">
            <a href="{{ route('categories.create', ['type' => 'expense']) }}">
                Create a new expense category
            </a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-sm-6">
            <label for="amount" class="form-label">Amount</label>

            <div class="input-group has-validation">
                <span class="input-group-text">M</span>

                <input
                    id="amount"
                    name="amount"
                    inputmode="decimal"
                    value="{{ old('amount', $e ? \App\Support\Money::plain($e->amount) : '') }}"
                    class="form-control num @error('amount') is-invalid @enderror"
                    placeholder="0.00"
                    required
                >

                @error('amount')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="col-sm-6">
            <label for="expense_date" class="form-label">Expense date</label>

            <input
                type="date"
                id="expense_date"
                name="expense_date"
                value="{{ old('expense_date', $e?->expense_date?->toDateString() ?? today()->toDateString()) }}"
                class="form-control @error('expense_date') is-invalid @enderror"
                required
            >

            @error('expense_date')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="mb-3">
        <label for="description" class="form-label">Description</label>

        <input
            id="description"
            name="description"
            value="{{ old('description', $e?->description) }}"
            class="form-control @error('description') is-invalid @enderror"
            maxlength="255"
            list="expense-descriptions"
            placeholder="e.g. Weekly groceries"
        >

        <datalist id="expense-descriptions">
            @foreach ($pastDescriptions as $description)
                <option value="{{ $description }}"></option>
            @endforeach
        </datalist>

        @error('description')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="merchant" class="form-label">
            Merchant <span class="text-secondary">(optional)</span>
        </label>

        <input
            id="merchant"
            name="merchant"
            value="{{ old('merchant', $e?->merchant) }}"
            class="form-control @error('merchant') is-invalid @enderror"
            maxlength="255"
            list="expense-merchants"
            placeholder="e.g. Pick n Pay"
        >

        <datalist id="expense-merchants">
            @foreach ($pastMerchants as $merchant)
                <option value="{{ $merchant }}"></option>
            @endforeach
        </datalist>

        @error('merchant')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-text mb-4">
        <i class="fa-solid fa-calendar-days me-1"></i>
        The financial period is assigned automatically from the expense date.
    </div>

    @if ($needsConfirm)
        <div class="alert alert-warning">
            <div class="form-check mb-0">
                <input
                    type="checkbox"
                    id="confirm_closed"
                    name="confirm_closed"
                    value="1"
                    class="form-check-input @error('confirm_closed') is-invalid @enderror"
                    @checked(old('confirm_closed'))
                >

                <label class="form-check-label" for="confirm_closed">
                    This change affects a closed financial period.
                    I understand and want to proceed.
                </label>

                @error('confirm_closed')
                    <div class="invalid-feedback d-block">
                        {{ $message }}
                    </div>
                @enderror
            </div>
        </div>
    @endif

    <div class="d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-check me-1"></i>
            {{ $e ? 'Save changes' : 'Record expense' }}
        </button>

        <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">
            Cancel
        </a>
    </div>
</form>
