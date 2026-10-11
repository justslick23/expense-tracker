
@php
    $editing = isset($goal);

    $targetAmount = $editing
        ? number_format($goal->target_amount / 100, 2, '.', '')
        : '';

    $startingAmount = $editing
        ? number_format($goal->starting_amount / 100, 2, '.', '')
        : '0.00';
@endphp

<form method="POST"
      action="{{ $editing ? route('savings.update', $goal) : route('savings.store') }}">

    @csrf

    @if ($editing)
        @method('PUT')
    @endif

    <div class="row g-4">

        <div class="col-12">
            <label for="name" class="form-label">Goal name</label>
            <input type="text"
                   name="name"
                   id="name"
                   class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $editing ? $goal->name : '') }}"
                   placeholder="e.g. Laptop Upgrade"
                   maxlength="150"
                   required>

            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label for="target_amount" class="form-label">
                Target amount
            </label>

            <div class="input-group has-validation">
                <span class="input-group-text">M</span>
                <input type="text"
                       name="target_amount"
                       id="target_amount"
                       inputmode="decimal"
                       class="form-control @error('target_amount') is-invalid @enderror"
                       placeholder="20000.00"
                       value="{{ old('target_amount', $targetAmount) }}"
                       required>

                @error('target_amount')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        @unless ($editing)
            <div class="col-md-6">
                <label for="starting_amount" class="form-label">
                    Already saved
                </label>

                <div class="input-group has-validation">
                    <span class="input-group-text">M</span>
                    <input type="text"
                           name="starting_amount"
                           id="starting_amount"
                           inputmode="decimal"
                           class="form-control @error('starting_amount') is-invalid @enderror"
                           value="{{ old('starting_amount', $startingAmount) }}"
                           placeholder="0.00">

                    @error('starting_amount')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-text">
                    Enter any savings you already have for this goal.
                </div>
            </div>
        @endunless

        <div class="{{ $editing ? 'col-12' : 'col-md-6' }}">
            <label for="target_date" class="form-label">
                Target date (optional)
            </label>

            <input type="date"
                   name="target_date"
                   id="target_date"
                   class="form-control @error('target_date') is-invalid @enderror"
                   value="{{ old('target_date', $editing ? $goal->target_date?->format('Y-m-d') : '') }}">

            @error('target_date')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-12">
            <label for="description" class="form-label">
                Description (optional)
            </label>

            <textarea name="description"
                      id="description"
                      rows="4"
                      maxlength="5000"
                      class="form-control @error('description') is-invalid @enderror"
                      placeholder="What are you saving towards?">{{ old('description', $editing ? $goal->description : '') }}</textarea>

            @error('description')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

    </div>

    <div class="d-flex flex-wrap gap-2 mt-4">
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-check me-2"></i>
            {{ $editing ? 'Save changes' : 'Create savings goal' }}
        </button>

        <a href="{{ $editing ? route('savings.show', $goal) : route('savings.index') }}"
           class="btn btn-outline-secondary">
            Cancel
        </a>
    </div>
</form>
