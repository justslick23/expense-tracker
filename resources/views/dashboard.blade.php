
@extends('layouts.shell')

@use('App\Support\Money')

@section('title', 'Dashboard')

@section('content')

<div class="dashboard">

    {{-- Page header --}}
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
        <div>
            <div class="text-secondary small mb-1">
                <i class="fa-solid fa-chart-line me-1"></i>
                Financial overview
            </div>

            <h1 class="h3 fw-bold mb-1">Dashboard</h1>

            <p class="text-secondary mb-0">
                Your money, spending and budgets in one place.
            </p>
        </div>

      
<div class="d-flex flex-wrap gap-2">

    <a href="{{ route('incomes.create') }}"
       class="btn btn-outline-secondary">
        <i class="fa-solid fa-plus me-1"></i>
        Income
    </a>

    <a href="{{ route('expenses.create') }}"
       class="btn btn-primary">
        <i class="fa-solid fa-plus me-1"></i>
        Expense
    </a>

    <a href="{{ route('receipts.create') }}"
       class="btn btn-outline-primary">
        <i class="fa-solid fa-camera me-2"></i>
        Scan Receipt
    </a>

</div>

    </div>

    @if (!$period)

        <div class="card">
            <x-empty-state
                icon="fa-calendar-days"
                title="Start your financial journey"
                text="Create your first financial period to begin tracking income, expenses and budgets."
            >
                <a href="{{ route('periods.create') }}"
                   class="btn btn-primary">
                    <i class="fa-solid fa-plus me-1"></i>
                    Create financial period
                </a>
            </x-empty-state>
        </div>

    @else

        {{-- Financial period selector --}}
        <div class="card p-3 mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">

                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="dashboard-period-icon">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>

                    <div>
                        <div class="small text-secondary mb-1">
                            Selected financial period
                        </div>

                        <div class="fw-bold">
                            {{ $period->name }}
                        </div>

                        <div class="small text-secondary">
                            {{ $period->start_date->format('d M Y') }}
                            —
                            {{ $period->end_date?->format('d M Y') ?? 'Ongoing' }}
                        </div>
                    </div>

                    <x-chip
                        :tone="$period->isOpen() ? 'success' : 'neutral'"
                        :icon="$period->isOpen() ? 'fa-lock-open' : 'fa-lock'"
                    >
                        {{ $period->isOpen() ? 'Open' : 'Closed' }}
                    </x-chip>
                </div>

                <form method="GET" action="{{ route('home') }}">
                    <select name="period"
                            class="form-select"
                            aria-label="Select financial period"
                            onchange="this.form.submit()">

                        @foreach ($periods as $p)
                            <option value="{{ $p->id }}"
                                    @selected($p->id === $period->id)>
                                {{ $p->name }}
                                {{ $p->isOpen() ? '(Open)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        {{-- Summary cards --}}
        <div class="row g-3 mb-4">

            <div class="col-sm-6 col-xl-3">
                <div class="card dashboard-stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <span class="dashboard-stat-label">
                            Total income
                        </span>

                        <span class="dashboard-stat-icon income">
                            <i class="fa-solid fa-arrow-trend-up"></i>
                        </span>
                    </div>

                    <div class="dashboard-stat-value num">
                        {{ Money::format($summary['total_income']) }}
                    </div>

                    <div class="dashboard-stat-footer">
                        <i class="fa-solid fa-circle-check me-1"></i>
                        {{ $summary['income_count'] }} income records
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card dashboard-stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <span class="dashboard-stat-label">
                            Total expenses
                        </span>

                        <span class="dashboard-stat-icon expense">
                            <i class="fa-solid fa-arrow-trend-down"></i>
                        </span>
                    </div>

                    <div class="dashboard-stat-value num">
                        {{ Money::format($summary['total_expenses']) }}
                    </div>

                    <div class="dashboard-stat-footer">
                        <i class="fa-solid fa-receipt me-1"></i>
                        {{ $summary['expense_count'] }} expenses recorded
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card dashboard-stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <span class="dashboard-stat-label">
                            Net cash flow
                        </span>

                        <span class="dashboard-stat-icon cashflow">
                            <i class="fa-solid fa-scale-balanced"></i>
                        </span>
                    </div>

                    <div class="dashboard-stat-value num
                        {{ $summary['net_cash_flow'] < 0 ? 'text-danger' : '' }}">
                        {{ Money::format($summary['net_cash_flow']) }}
                    </div>

                    <div class="dashboard-stat-footer">
                        @if ($summary['savings_rate'] !== null)
                            <i class="fa-solid fa-chart-line me-1"></i>
                            {{ number_format($summary['savings_rate'], 1) }}%
                            net income ratio
                        @else
                            No income recorded
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card dashboard-stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start mb-4">
                        <span class="dashboard-stat-label">
                            Remaining budget
                        </span>

                        <span class="dashboard-stat-icon budget">
                            <i class="fa-solid fa-wallet"></i>
                        </span>
                    </div>

                    <div class="dashboard-stat-value num
                        {{ $summary['remaining_budget'] < 0 ? 'text-danger' : '' }}">
                        {{ Money::format($summary['remaining_budget']) }}
                    </div>

                    <div class="dashboard-stat-footer">
                        <i class="fa-solid fa-chart-pie me-1"></i>
                        {{ number_format($summary['budget_utilization'], 1) }}%
                        budget utilized
                    </div>
                </div>
            </div>

        </div>

        @include('dashboard.partials.recurring-expenses')
        @include('dashboard.partials.savings-goals')
        {{-- Budget and category overview --}}
        <div class="row g-3 mb-4">

            <div class="col-lg-5">
                <div class="card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="h5 fw-bold mb-1">Budget overview</h2>
                            <p class="text-secondary small mb-0">
                                Your spending against planned allocations.
                            </p>
                        </div>

                        <a href="{{ route('budgets.index', ['period' => $period->id]) }}"
                           class="icon-btn"
                           title="View budgets"
                           aria-label="View budgets">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                        </a>
                    </div>

                    @php
                        $budgetTotal = $summary['total_budget'];
                        $budgetSpent = $summary['total_expenses'];
                        $budgetRemaining = $summary['remaining_budget'];
                        $budgetPercent = $summary['budget_utilization'];
                        $barWidth = min(100, max(0, $budgetPercent));

                        $barTone = $budgetPercent > 100
                            ? 'danger'
                            : ($budgetPercent >= 80 ? 'warning' : 'success');
                    @endphp

                    <div class="mb-4">
                        <div class="small text-secondary mb-1">Total allocated</div>
                        <div class="h3 fw-bold num mb-1">
                            {{ Money::format($budgetTotal) }}
                        </div>
                        <div class="small text-secondary">
                            {{ Money::format($budgetSpent) }} spent
                        </div>
                    </div>

                    @if ($budgetTotal > 0)
                        <div class="d-flex justify-content-between mb-2 small">
                            <span class="text-secondary">Budget utilization</span>
                            <span class="fw-semibold num">
                                {{ number_format($budgetPercent, 1) }}%
                            </span>
                        </div>

                        <div class="progress mb-3" style="height: 10px;">
                            <div class="progress-bar bg-{{ $barTone }}"
                                 role="progressbar"
                                 style="width: {{ $barWidth }}%"
                                 aria-valuenow="{{ $barWidth }}"
                                 aria-valuemin="0"
                                 aria-valuemax="100">
                            </div>
                        </div>
                    @else
                        <div class="alert alert-info small mb-3">
                            No category budgets have been allocated yet.
                        </div>
                    @endif

                    <div class="d-flex justify-content-between align-items-center py-2 border-top">
                        <span class="text-secondary small">Remaining allocation</span>
                        <strong class="num {{ $budgetRemaining < 0 ? 'text-danger' : '' }}">
                            {{ Money::format($budgetRemaining) }}
                        </strong>
                    </div>

                    <div class="d-flex justify-content-between align-items-center py-2 border-top">
                        <span class="text-secondary small">Unbudgeted spending</span>
                        <strong class="num">
                            {{ Money::format($summary['unbudgeted_spending']) }}
                        </strong>
                    </div>

                    <a href="{{ route('budgets.index', ['period' => $period->id]) }}"
                       class="btn btn-outline-secondary mt-auto">
                        Manage budgets
                        <i class="fa-solid fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card p-4 h-100">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                        <div>
                            <h2 class="h5 fw-bold mb-1">Category spending</h2>
                            <p class="text-secondary small mb-0">
                                How your expenses compare against budgets.
                            </p>
                        </div>

                        <a href="{{ route('budgets.index', ['period' => $period->id]) }}"
                           class="small text-decoration-none">
                            View all
                            <i class="fa-solid fa-arrow-right ms-1"></i>
                        </a>
                    </div>

                    @forelse ($summary['category_budgets']->sortByDesc('spent')->take(5) as $row)
                        @php
                            $percentage = $row['utilization'];
                            $width = min(100, max(0, $percentage));

                            $tone = match ($row['status']) {
                                'over' => 'danger',
                                'warning', 'used' => 'warning',
                                default => 'success',
                            };
                        @endphp

                        <div class="dashboard-category-row">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                <x-category-badge :category="$row['category']" />

                                <div class="text-end">
                                    <strong class="num small">
                                        {{ Money::format($row['spent']) }}
                                    </strong>
                                    <span class="text-secondary small">
                                        / {{ Money::format($row['allocated']) }}
                                    </span>
                                </div>
                            </div>

                            <div class="progress" style="height: 7px;">
                                <div class="progress-bar bg-{{ $tone }}"
                                     role="progressbar"
                                     style="width: {{ $width }}%"
                                     aria-valuenow="{{ $width }}"
                                     aria-valuemin="0"
                                     aria-valuemax="100">
                                </div>
                            </div>

                            <div class="text-end text-secondary mt-1"
                                 style="font-size: .75rem;">
                                {{ number_format($percentage, 1) }}%
                            </div>
                        </div>
                    @empty
                        <div class="dashboard-empty-chart">
                            <i class="fa-solid fa-chart-pie"></i>
                            <p class="mb-0">
                                Create category budgets to see spending comparisons.
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>

        </div>

        
{{-- Financial analytics --}}
<div class="mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Financial analytics</h2>
            <p class="text-secondary small mb-0">
                Understand your income, spending patterns and budget performance.
            </p>
        </div>
    </div>

    <div class="row g-3">

        {{-- Income vs expenses --}}
        <div class="col-12">
                        <div class="card p-4 h-100">
                <h3 class="h6 fw-bold mb-1">Income vs Expenses</h3>
                <p class="text-secondary small mb-4">
                    Compare your financial activity across recent periods.
                </p>

                @if (array_sum($charts['income_expenses']['income']) > 0
                    || array_sum($charts['income_expenses']['expenses']) > 0)
                    <div class="dashboard-chart-container">
                        <canvas id="incomeExpenseChart"
                                role="img"
                                aria-label="Income versus expenses chart"></canvas>
                    </div>
                @else
                    <div class="dashboard-empty-chart">
                        <i class="fa-solid fa-chart-column"></i>
                        <p>No income or expenses have been recorded for these periods.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Category spending --}}
        <div class="col-12">
            <div class="card p-4 h-100">
                <h3 class="h6 fw-bold mb-1">Spending by Category</h3>
                <p class="text-secondary small mb-4">
                    Categories ranked by highest spending.
                    Scroll to explore your spending breakdown.
                </p>

                @if (array_sum($charts['category_spending']['amounts']) > 0)
                   
<div
class="category-spending-scroll"
style="
    max-height: 540px;
    overflow-y: auto;
    overflow-x: auto;
"
>
<div
    class="dashboard-chart-container"
    style="min-width: 560px;"
>
    <canvas
        id="categorySpendingChart"
        role="img"
        aria-label="Spending by category, ranked from highest to lowest"
    ></canvas>
</div>
</div>

                @else
                    <div class="dashboard-empty-chart">
                        <i class="fa-solid fa-chart-pie"></i>
                        <p>No category spending recorded yet.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Budget performance --}}
        <div class="col-12">
            <div class="card p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h3 class="h6 fw-bold mb-1">Budget Performance</h3>
                        <p class="text-secondary small mb-0">
                            Compare allocations against actual category spending.
                        </p>
                    </div>

                    <a href="{{ route('budgets.index', ['period' => $period->id]) }}"
                       class="btn btn-outline-secondary btn-sm">
                        Manage budgets
                        <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>

                @if (count($charts['budget_performance']['labels']) > 0)
                    <div class="dashboard-chart-container dashboard-chart-wide">
                        <canvas id="budgetPerformanceChart"
                                role="img"
                                aria-label="Budget allocations versus spending chart"></canvas>
                    </div>
                @else
                    <div class="dashboard-empty-chart">
                        <i class="fa-solid fa-chart-bar"></i>
                        <p>Create category allocations to see budget comparisons.</p>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
    window.dashboardChartData = @json($charts);
</script>
<script src="{{ asset('js/dashboard-charts.js') }}"></script>
@endpush


@include('dashboard.partials.ai-financial-advisor')

{{-- Financial insights --}}
<section class="mb-4" aria-labelledby="insights-heading">

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h2 id="insights-heading" class="h5 fw-bold mb-1">
                Financial insights
            </h2>
            <p class="text-secondary small mb-0">
                Understand your spending habits and financial progress.
            </p>
        </div>
    </div>

    @php
        $comparison = $insights['comparison'];
        $velocity = $insights['velocity'];
        $alerts = $insights['alerts'];
        $recommendations = $insights['recommendations'];
    @endphp

    {{-- Comparison and spending velocity --}}
    <div class="row g-3 mb-3">

        <div class="col-lg-6">
            <div class="card p-4 h-100">

                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h3 class="h6 fw-bold mb-1">
                            Period comparison
                        </h3>
                        <p class="text-secondary small mb-0">
                            @if ($comparison['available'])
                                Compared with {{ $comparison['period']->name }}
                            @else
                                Compare your financial progress over time.
                            @endif
                        </p>
                    </div>

                    <i class="fa-solid fa-arrow-right-arrow-left text-secondary"></i>
                </div>

                @if ($comparison['available'])

                    @foreach ([
                        [
                            'label' => 'Income',
                            'value' => $comparison['income_change'],
                            'favorable_positive' => true,
                        ],
                        [
                            'label' => 'Expenses',
                            'value' => $comparison['expense_change'],
                            'favorable_positive' => false,
                        ],
                        [
                            'label' => 'Net cash flow',
                            'value' => $comparison['cash_flow_change'],
                            'favorable_positive' => true,
                        ],
                    ] as $metric)

                        @php
                            $change = $metric['value'];

                            $favorable = $change !== null
                                && $change != 0
                                && (($change > 0) === $metric['favorable_positive']);

                            $tone = $change === null || $change == 0
                                ? 'secondary'
                                : ($favorable ? 'success' : 'danger');
                        @endphp

                        <div class="insight-metric-row">
                            <span class="text-secondary small">
                                {{ $metric['label'] }}
                            </span>

                            @if ($change === null)
                                <span class="small text-secondary">
                                    N/A
                                </span>
                            @else
                                <span class="fw-semibold text-{{ $tone }} num">
                                    <i class="fa-solid {{ $change > 0 ? 'fa-arrow-up' : ($change < 0 ? 'fa-arrow-down' : 'fa-minus') }} me-1"></i>
                                    {{ number_format(abs($change), 1) }}%
                                </span>
                            @endif
                        </div>

                    @endforeach

                    <div class="small text-secondary mt-3">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Comparisons use recorded period totals, not daily averages.
                    </div>

                @else
                    <div class="dashboard-empty-chart">
                        <i class="fa-solid fa-chart-line"></i>
                        <p class="mb-0">
                            Insights will appear when you have an earlier financial period.
                        </p>
                    </div>
                @endif

            </div>
        </div>

        <div class="col-lg-6">
            <div class="card p-4 h-100">

                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h3 class="h6 fw-bold mb-1">
                            Spending velocity
                        </h3>
                        <p class="text-secondary small mb-0">
                            Your average daily spending and projected expenses.
                        </p>
                    </div>

                    <i class="fa-solid fa-gauge-high text-secondary"></i>
                </div>

                <div class="mb-4">
                    <div class="text-secondary small mb-1">
                        Average daily spending
                    </div>

                    <div class="h3 fw-bold num mb-1">
                        @if ($velocity['daily_average'] !== null)
                            {{ \App\Support\Money::format($velocity['daily_average']) }}
                        @else
                            —
                        @endif
                    </div>
                </div>

                <div class="insight-metric-row">
                    <span class="text-secondary small">
                        Days elapsed
                    </span>
                    <strong class="num">
                        {{ $velocity['days_elapsed'] }}
                    </strong>
                </div>

                <div class="insight-metric-row">
                    <span class="text-secondary small">
                        Days remaining
                    </span>
                    <strong class="num">
                        {{ $velocity['days_remaining'] ?? 'Not set' }}
                    </strong>
                </div>

                <div class="insight-metric-row">
                    <span class="text-secondary small">
                        Projected period spending
                    </span>

                    @if ($velocity['projection_available'])
                        <strong class="num">
                            {{ \App\Support\Money::format($velocity['projected_spending']) }}
                        </strong>
                    @else
                        <span class="small text-secondary">
                            Not available
                        </span>
                    @endif
                </div>

                @if ($period->isOpen() && !$period->end_date)
                    <div class="small text-secondary mt-3">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Set an end date to enable spending projections.
                    </div>
                @endif

            </div>
        </div>

    </div>

    {{-- Budget alerts and recommendations --}}
    <div class="row g-3">

        <div class="col-lg-5">
            <div class="card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h6 fw-bold mb-0">
                        Budget alerts
                    </h3>

                    @if (count($alerts) > 0)
                        <span class="badge text-bg-warning">
                            {{ count($alerts) }}
                        </span>
                    @endif
                </div>

                @forelse ($alerts as $alert)

                    <div class="insight-alert-row">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div class="fw-semibold small">
                                {{ $alert['category'] }}
                            </div>

                            <span class="badge text-bg-{{ $alert['level'] }}">
                                {{ number_format($alert['utilization'], 1) }}%
                            </span>
                        </div>

                        <div class="progress mb-2" style="height: 7px;">
                            <div class="progress-bar bg-{{ $alert['level'] }}"
                                 role="progressbar"
                                 style="width: {{ min(100, max(0, $alert['utilization'])) }}%"
                                 aria-valuenow="{{ min(100, max(0, $alert['utilization'])) }}"
                                 aria-valuemin="0"
                                 aria-valuemax="100">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between small text-secondary">
                            <span>
                                {{ \App\Support\Money::format($alert['spent']) }} spent
                            </span>
                            <span>
                                {{ \App\Support\Money::format($alert['allocated']) }} allocated
                            </span>
                        </div>
                    </div>

                @empty
                    <div class="dashboard-empty-chart">
                        <i class="fa-solid fa-circle-check text-success"></i>
                        <p class="mb-0">
                            No category budgets have reached the 80% alert threshold.
                        </p>
                    </div>
                @endforelse

            </div>
        </div>

        <div class="col-lg-7">
            <div class="card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h6 fw-bold mb-0">
                        Recommendations
                    </h3>

                    <i class="fa-solid fa-lightbulb text-secondary"></i>
                </div>

                @forelse ($recommendations as $recommendation)

                    <div class="insight-recommendation">
                        <div class="insight-recommendation-icon {{ $recommendation['level'] }}">
                            <i class="fa-solid {{ $recommendation['icon'] }}"></i>
                        </div>

                        <div>
                            <div class="fw-semibold small mb-1">
                                {{ $recommendation['title'] }}
                            </div>

                            <p class="text-secondary small mb-0">
                                {{ $recommendation['message'] }}
                            </p>
                        </div>
                    </div>

                @empty
                    <div class="dashboard-empty-chart">
                        <i class="fa-solid fa-circle-check text-success"></i>
                        <p class="mb-0">
                            No financial warnings have been identified from the available data.
                        </p>
                    </div>
                @endforelse

                <div class="small text-secondary mt-auto pt-3">
                    <i class="fa-solid fa-shield-halved me-1"></i>
                    Insights are generated locally from your financial records.
                </div>
            </div>
        </div>

    </div>

</section>



        {{-- Recent transactions --}}
        <div class="card mb-4">
            <div class="p-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h2 class="h5 fw-bold mb-1">Recent transactions</h2>
                    <p class="text-secondary small mb-0">
                        Latest activity in this financial period.
                    </p>
                </div>

                <a href="{{ route('expenses.index', ['period' => $period->id]) }}"
                   class="btn btn-outline-secondary btn-sm">
                    View expenses
                    <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>

            @if ($recentTransactions->isEmpty())
                <x-empty-state
                    icon="fa-receipt"
                    title="No transactions yet"
                    text="Record income or expenses to see your recent financial activity."
                />
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Transaction</th>
                                <th>Category</th>
                                <th>Date</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($recentTransactions as $transaction)
                                @php
                                    $isIncome = $transaction['type'] === 'income';
                                @endphp

                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="dashboard-transaction-icon {{ $isIncome ? 'income' : 'expense' }}">
                                                <i class="fa-solid {{ $isIncome ? 'fa-arrow-down' : 'fa-arrow-up' }}"></i>
                                            </span>

                                            <div>
                                                <div class="fw-semibold">
                                                    {{ $transaction['description'] }}
                                                </div>

                                                @if ($transaction['merchant'])
                                                    <div class="text-secondary small">
                                                        {{ $transaction['merchant'] }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <td>
                                        @if ($transaction['category'])
                                            <x-category-badge :category="$transaction['category']" />
                                        @else
                                            <span class="text-secondary">Uncategorized</span>
                                        @endif
                                    </td>

                                    <td class="text-nowrap text-secondary small">
                                        {{ $transaction['date']->format('d M Y') }}
                                    </td>

                                    <td class="text-end fw-semibold num {{ $isIncome ? 'text-success' : '' }}">
                                        {{ $isIncome ? '+' : '-' }}{{ Money::format($transaction['amount']) }}
                                    </td>

                                    <td class="text-end">
                                        <a href="{{ $transaction['url'] }}"
                                           class="icon-btn"
                                           title="Edit transaction"
                                           aria-label="Edit transaction">
                                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    @endif

</div>

@endsection
