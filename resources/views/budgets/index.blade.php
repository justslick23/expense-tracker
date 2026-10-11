
@extends('layouts.shell')

@use('App\Support\Money')

@section('title', 'Budgets')

@section('content')

<x-page-header
    title="Budgets"
    subtitle="Plan your spending and monitor category allocations."
>
    <a href="{{ route('budgets.create', $period ? ['period' => $period->id] : []) }}"
       class="btn btn-primary">
        <i class="fa-solid fa-plus me-1"></i>
        New allocation
    </a>
</x-page-header>

@if ($periods->isEmpty())

    <div class="card">
        <x-empty-state
            icon="fa-calendar-days"
            title="No financial periods"
            text="Create a financial period before setting category budgets."
        >
            <a href="{{ route('periods.create') }}" class="btn btn-primary">
                Create financial period
            </a>
        </x-empty-state>
    </div>

@else

    <div class="card p-3 mb-4">
        <form method="GET" action="{{ route('budgets.index') }}"
              class="d-flex flex-wrap align-items-end gap-3">

            <div style="min-width: 240px; flex: 1; max-width: 400px;">
                <label for="period" class="form-label">
                    Financial period
                </label>

                <select name="period" id="period" class="form-select"
                        onchange="this.form.submit()">
                    @foreach ($periods as $p)
                        <option value="{{ $p->id }}"
                                @selected($period?->id === $p->id)>
                            {{ $p->name }}
                            {{ $p->isOpen() ? '(Open)' : '(Closed)' }}
                        </option>
                    @endforeach
                </select>
            </div>

            @if ($period)
                <div class="pb-1">
                    <div class="small text-secondary">
                        <i class="fa-solid fa-calendar-days me-1"></i>
                        {{ $period->start_date->format('d M Y') }}
                        —
                        {{ $period->end_date?->format('d M Y') ?? 'Ongoing' }}
                    </div>
                </div>
            @endif
        </form>
    </div>

    @if ($summary)

        @php
            $allocated = $summary['total_allocated'];
            $spent = $summary['total_spent'];
            $remaining = $summary['remaining'];
            $utilization = $summary['utilization'];

            $progress = min(100, max(0, $utilization));

            $statusClass = $utilization > 100
                ? 'danger'
                : ($utilization >= 80 ? 'warning' : 'success');
        @endphp

        <div class="row g-3 mb-4">

            <div class="col-sm-6 col-xl-3">
                <div class="card p-3 h-100">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-secondary small">Total allocated</span>
                        <i class="fa-solid fa-wallet text-secondary"></i>
                    </div>

                    <h3 class="h4 fw-bold num mb-1">
                        {{ Money::format($allocated) }}
                    </h3>

                    <div class="text-secondary small">
                        Planned spending
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card p-3 h-100">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-secondary small">Total spent</span>
                        <i class="fa-solid fa-receipt text-secondary"></i>
                    </div>

                    <h3 class="h4 fw-bold num mb-1">
                        {{ Money::format($spent) }}
                    </h3>

                    <div class="text-secondary small">
                        Actual expenses
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card p-3 h-100">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-secondary small">Remaining budget</span>
                        <i class="fa-solid fa-scale-balanced text-secondary"></i>
                    </div>

                    <h3 class="h4 fw-bold num mb-1 {{ $remaining < 0 ? 'text-danger' : 'text-success' }}">
                        {{ Money::format($remaining) }}
                    </h3>

                    <div class="text-secondary small">
                        Allocated minus actual spending
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card p-3 h-100">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-secondary small">Budget utilization</span>
                        <i class="fa-solid fa-chart-simple text-secondary"></i>
                    </div>

                    <h3 class="h4 fw-bold num mb-2">
                        {{ number_format($utilization, 1) }}%
                    </h3>

                    <div class="progress" style="height: 7px;">
                        <div class="progress-bar bg-{{ $statusClass }}"
                             role="progressbar"
                             style="width: {{ $progress }}%"
                             aria-valuenow="{{ $progress }}"
                             aria-valuemin="0"
                             aria-valuemax="100">
                        </div>
                    </div>
                </div>
            </div>

        </div>

        @if ($summary['unbudgeted_spending'] > 0)
            <div class="alert alert-warning d-flex gap-2 align-items-start">
                <i class="fa-solid fa-triangle-exclamation mt-1"></i>
                <div>
                    <strong>Unbudgeted spending detected.</strong>
                    You have spent
                    {{ Money::format($summary['unbudgeted_spending']) }}
                    in categories without budget allocations.
                    This spending is included in your total expenses.
                </div>
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h2 class="h5 fw-bold mb-1">Category allocations</h2>
                <p class="text-secondary small mb-0">
                    Compare your planned amounts against actual expenses.
                </p>
            </div>
        </div>

        @if ($summary['categories']->isEmpty())

            <div class="card">
                <x-empty-state
                    icon="fa-chart-pie"
                    title="No budgets allocated"
                    text="Start by assigning spending limits to your expense categories."
                >
                    <a href="{{ route('budgets.create', ['period' => $period->id]) }}"
                       class="btn btn-primary">
                        Create allocation
                    </a>
                </x-empty-state>
            </div>

        @else

            <div class="row g-3">

                @foreach ($summary['categories'] as $row)

                    @php
                        $budget = $row['budget'];
                        $category = $row['category'];

                        $allocatedAmount = $row['allocated'];
                        $spentAmount = $row['spent'];
                        $remainingAmount = $row['remaining'];
                        $percentage = $row['utilization'];

                        $barWidth = min(100, max(0, $percentage));

                        $tone = match ($row['status']) {
                            'over' => 'danger',
                            'used', 'warning' => 'warning',
                            default => 'success',
                        };
                    @endphp

                    <div class="col-md-6 col-xl-4">
                        <div class="card p-4 h-100">

                            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                <x-category-badge :category="$category" />

                                <div class="dropdown">
                                    <button class="icon-btn"
                                            type="button"
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false"
                                            aria-label="Budget actions">
                                        <i class="fa-solid fa-ellipsis"></i>
                                    </button>

                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item"
                                               href="{{ route('budgets.edit', $budget) }}">
                                                <i class="fa-solid fa-pen me-2"></i>
                                                Edit allocation
                                            </a>
                                        </li>

                                        <li><hr class="dropdown-divider"></li>

                                        <li>
                                            <form method="POST"
                                                  action="{{ route('budgets.destroy', $budget) }}"
                                                  data-confirm="Delete this budget allocation? Existing expenses will not be deleted.">
                                                @csrf
                                                @method('DELETE')

                                                @if (! $period->isOpen())
                                                    <input type="hidden"
                                                           name="confirm_closed"
                                                           value="1">
                                                @endif

                                                <button type="submit"
                                                        class="dropdown-item text-danger">
                                                    <i class="fa-solid fa-trash me-2"></i>
                                                    Delete allocation
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="d-flex align-items-end justify-content-between gap-2 mb-2">
                                <div>
                                    <div class="text-secondary small mb-1">Spent</div>
                                    <div class="h4 fw-bold num mb-0">
                                        {{ Money::format($spentAmount) }}
                                    </div>
                                </div>

                                <div class="text-end">
                                    <div class="text-secondary small mb-1">Budget</div>
                                    <div class="fw-semibold num">
                                        {{ Money::format($allocatedAmount) }}
                                    </div>
                                </div>
                            </div>

                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar bg-{{ $tone }}"
                                     style="width: {{ $barWidth }}%"
                                     role="progressbar"
                                     aria-valuenow="{{ $barWidth }}"
                                     aria-valuemin="0"
                                     aria-valuemax="100">
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center small">
                                <span class="{{ $remainingAmount < 0 ? 'text-danger' : 'text-secondary' }}">
                                    @if ($remainingAmount < 0)
                                        {{ Money::format(abs($remainingAmount)) }} over budget
                                    @else
                                        {{ Money::format($remainingAmount) }} remaining
                                    @endif
                                </span>

                                <span class="fw-semibold num">
                                    {{ number_format($percentage, 1) }}%
                                </span>
                            </div>

                        </div>
                    </div>

                @endforeach

            </div>

        @endif

    @endif

@endif

@endsection
