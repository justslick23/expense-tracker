@php
    $i = $income ?? null;
    $needsConfirm = $errors->has('confirm_closed') || old('confirm_closed') || ($i?->period && ! $i->period->isOpen());
@endphp
<form method="POST" action="{{ $i ? route('incomes.update', $i) : route('incomes.store') }}">
    @csrf @if ($i) @method('PUT') @endif

    <div class="mb-3">
        <label class="form-label" for="category_id">Category</label>
        <select id="category_id" name="category_id" class="form-select @error('category_id') is-invalid @enderror" required>
            <option value="">Select a category</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}" @selected(old('category_id', $i?->category_id) == $c->id)>{{ $c->name }}{{ $c->isArchived() ? ' (archived)' : '' }}</option>
            @endforeach
        </select>
        @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text"><a href="{{ route('categories.create', ['type' => 'income']) }}">Create a new income category</a></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-sm-6">
            <label class="form-label" for="amount">Amount</label>
            <div class="input-group has-validation">
                <span class="input-group-text">M</span>
                <input id="amount" name="amount" inputmode="decimal" value="{{ old('amount', $i ? \App\Support\Money::plain($i->amount) : '') }}" class="form-control num @error('amount') is-invalid @enderror" placeholder="0.00" required>
                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-sm-6">
            <label class="form-label" for="income_date">Date received</label>
            <input id="income_date" type="date" name="income_date" value="{{ old('income_date', $i?->income_date->toDateString() ?? today()->toDateString()) }}" class="form-control @error('income_date') is-invalid @enderror" required>
            @error('income_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="mb-4">
        <label class="form-label" for="description">Description <span class="text-secondary">(optional)</span></label>
        <input id="description" name="description" value="{{ old('description', $i?->description) }}" maxlength="255" class="form-control @error('description') is-invalid @enderror" placeholder="e.g. October salary">
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">The financial period is assigned automatically from the date.</div>
    </div>

    @if ($needsConfirm)
        <div class="alert alert-warning">
            <div class="form-check mb-0">
                <input class="form-check-input @error('confirm_closed') is-invalid @enderror" type="checkbox" name="confirm_closed" id="confirm_closed" value="1" @checked(old('confirm_closed'))>
                <label class="form-check-label" for="confirm_closed">This change affects a closed period. I understand and want to proceed.</label>
                @error('confirm_closed')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
    @endif

    <button class="btn btn-primary">{{ $i ? 'Save changes' : 'Record income' }}</button>
    <a href="{{ route('incomes.index') }}" class="btn btn-link">Cancel</a>
</form>