
@php
    $editing = isset($recurring);

    $hasHistory = $editing
        ? $recurring->occurrences()->exists()
        : false;

    $selectedCategory = old(
        'category_id',
        $editing ? $recurring->category_id : null
    );

    $amountValue = $editing
        ? number_format($recurring->amount / 100, 2, '.', '')
        : '';

    $frequency = old(
        'frequency',
        $editing ? $recurring->frequency : 'monthly'
    );

    $startDate = old(
        'start_date',
        $editing ? $recurring->start_date->format('Y-m-d') : today()->toDateString()
    );
@endphp

<form method="POST"
      action="{{ $editing ? route('recurring.update', $recurring) : route('recurring.store') }}">

    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    <div class="row g-3">

        <div class="col-12">
            <label for="name" class="form-label">Payment name</label>
            <input type="text"
                   id="name"
                   name="name"
                   class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $editing ? $recurring->name : '') }}"
                   placeholder="e.g. Internet subscription"
                   maxlength="150"
                   required>

            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label for="category_id" class="form-label">Expense category</label>

            <select id="category_id"
                    name="category_id"
                    class="form-select @error('category_id') is-invalid @enderror"
                    required>

                <option value="">Select category</option>

                @foreach ($categories as $category)
                    <option value="{{ $category->id }}"
                            @selected((string) $selectedCategory === (string) $category->id)>
                        {{ $category->name }}
                        @if ($category->isArchived())
                            (Archived)
                        @endif
                    </option>
                @endforeach
            </select>

            @error('category_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label for="amount" class="form-label">Planned amount</label>
            <div class="input-group has-validation">
                <span class="input-group-text">M</span>
                <input type="text"
                       id="amount"
                       name="amount"
                       inputmode="decimal"
                       class="form-control @error('amount') is-invalid @enderror"
                       value="{{ old('amount', $amountValue) }}"
                       placeholder="650.00"
                       required>

                @error('amount')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="col-md-6">
            <label for="frequency" class="form-label">Frequency</label>

            <select id="frequency"
                    name="frequency"
                    class="form-select @error('frequency') is-invalid @enderror"
                    @disabled($hasHistory)
                    required>

                @foreach ([
                    'daily' => 'Daily',
                    'weekly' => 'Weekly',
                    'monthly' => 'Monthly',
                    'quarterly' => 'Quarterly',
                    'yearly' => 'Yearly',
                ] as $value => $label)
                    <option value="{{ $value }}"
                            @selected($frequency === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>

            @if ($hasHistory)
                <input type="hidden"
                       name="frequency"
                       value="{{ $recurring->frequency }}">
            @endif

            @error('frequency')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label for="start_date" class="form-label">First payment date</label>

            <input type="date"
                   id="start_date"
                   name="start_date"
                   class="form-control @error('start_date') is-invalid @enderror"
                   value="{{ $startDate }}"
                   @readonly($hasHistory)
                   required>

            @error('start_date')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label for="end_date" class="form-label">End date (optional)</label>
            <input type="date"
                   id="end_date"
                   name="end_date"
                   class="form-control @error('end_date') is-invalid @enderror"
                   value="{{ old('end_date', $editing ? $recurring->end_date?->format('Y-m-d') : '') }}">

            @error('end_date')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label for="merchant" class="form-label">Merchant / Payee</label>
            <input type="text"
                   id="merchant"
                   name="merchant"
                   class="form-control @error('merchant') is-invalid @enderror"
                   value="{{ old('merchant', $editing ? $recurring->merchant : '') }}"
                   placeholder="e.g. Internet Provider">

            @error('merchant')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-12">
            <label for="description" class="form-label">Description (optional)</label>
            <textarea id="description"
                      name="description"
                      class="form-control @error('description') is-invalid @enderror"
                      rows="3"
                      placeholder="Additional payment details">{{ old('description', $editing ? $recurring->description : '') }}</textarea>

            @error('description')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-12">
            <input type="hidden" name="is_active" value="0">

            <div class="form-check form-switch">
                <input class="form-check-input"
                       type="checkbox"
                       role="switch"
                       id="is_active"
                       name="is_active"
                       value="1"
                       @checked((bool) old('is_active', $editing ? $recurring->is_active : true))>

                <label for="is_active" class="form-check-label">
                    Active recurring schedule
                </label>
            </div>

            <div class="form-text">
                Inactive schedules do not generate new payments.
            </div>
        </div>
    </div>

    @if ($hasHistory)
        <div class="alert alert-info mt-4 mb-0">
            <i class="fa-solid fa-circle-info me-2"></i>
            The original start date and frequency are locked because
            scheduled payment history already exists.
            Updating the amount only affects future generated payments.
        </div>
    @endif

    <div class="d-flex flex-wrap gap-2 mt-4">
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-check me-2"></i>
            {{ $editing ? 'Save changes' : 'Create recurring expense' }}
        </button>

        <a href="{{ route('recurring.index') }}"
           class="btn btn-outline-secondary">
            Cancel
        </a>
    </div>
</form>
