
@extends('layouts.shell')

@use('App\Support\Money')

@section('title', 'Financial Reports')

@section('content')
<div class="financial-reports">

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1">Financial Reports</h1>
            <p class="text-secondary mb-0">
                Explore your income, spending and budgets.
            </p>
        </div>

        <a href="{{ route('reports.history') }}"
           class="btn btn-outline-secondary">
            <i class="fa-solid fa-chart-line me-2"></i>
            Historical reports
        </a>

        @if ($period)
    <a href="{{ route('reports.export', ['period' => $period->id]) }}"
       class="btn btn-primary">
        <i class="fa-solid fa-file-csv me-2"></i>
        Export CSV
    </a>
@endif
    </div>

    @if ($periods->isEmpty())
        <div class="card p-5 text-center">
            <i class="fa-solid fa-calendar-days fa-2x text-secondary mb-3"></i>
            <h2 class="h5">No financial periods yet</h2>
            <p class="text-secondary">
                Create a financial period to start generating reports.
            </p>
            <div>
                <a href="{{ route('periods.create') }}"
                   class="btn btn-primary">
                    Create financial period
                </a>
            </div>
        </div>
    @else

        {{-- Period selector --}}
        <div class="card p-3 mb-4">
            <form method="GET" action="{{ route('reports.index') }}"
                  class="d-flex flex-wrap align-items-end gap-3">

                <div class="flex-grow-1" style="max-width: 400px;">
                    <label class="form-label" for="reportPeriod">
                        Financial period
                    </label>

                    <select id="reportPeriod"
                            name="period"
                            class="form-select"
                            onchange="this.form.submit()">
                        @foreach ($periods as $item)
                            <option value="{{ $item->id }}"
                                    @selected($period?->id === $item->id)>
                                {{ $item->name }}
                                {{ $item->isOpen() ? '(Open)' : '(Closed)' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="small text-secondary pb-2">
                    <i class="fa-solid fa-calendar me-1"></i>
                    {{ $period->start_date->format('d M Y') }}
                    —
                    {{ $period->end_date?->format('d M Y') ?? 'Ongoing' }}
                </div>
            </form>
        </div>

        @if ($report)
            @php
                $overview = $report['overview'];
                $budget = $report['budget'];
                $expenseCategories = $report['expenses_by_category'];
                $incomeCategories = $report['income_by_category'];
                $merchants = $report['expenses_by_merchant'];
            @endphp

            {{-- Summary cards --}}
            <div class="row g-3 mb-4">
                @foreach ([
                    [
                        'label' => 'Total income',
                        'amount' => $overview['income'],
                        'icon' => 'fa-arrow-trend-up',
                        'tone' => 'success',
                    ],
                    [
                        'label' => 'Total expenses',
                        'amount' => $overview['expenses'],
                        'icon' => 'fa-arrow-trend-down',
                        'tone' => 'danger',
                    ],
                    [
                        'label' => 'Net cash flow',
                        'amount' => $overview['net_cash_flow'],
                        'icon' => 'fa-scale-balanced',
                        'tone' => $overview['net_cash_flow'] < 0 ? 'danger' : 'success',
                    ],
                    [
                        'label' => 'Remaining budget',
                        'amount' => $budget['remaining'],
                        'icon' => 'fa-wallet',
                        'tone' => $budget['remaining'] < 0 ? 'danger' : 'primary',
                    ],
                ] as $metric)
                    <div class="col-sm-6 col-xl-3">
                        <div class="card report-stat h-100">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="text-secondary small">
                                    {{ $metric['label'] }}
                                </span>
                                <i class="fa-solid {{ $metric['icon'] }} text-{{ $metric['tone'] }}"></i>
                            </div>
                            <div class="h4 fw-bold num mb-0">
                                {{ Money::format($metric['amount']) }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Additional details --}}
            <div class="card p-4 mb-4">
                <h2 class="h5 fw-bold mb-3">Period summary</h2>

                <div class="row g-3">
                    <div class="col-6 col-lg-3">
                        <div class="small text-secondary">Income records</div>
                        <strong>{{ $overview['income_count'] }}</strong>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="small text-secondary">Expense records</div>
                        <strong>{{ $overview['expense_count'] }}</strong>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="small text-secondary">Net income ratio</div>
                        <strong>
                            {{ $overview['savings_rate'] !== null
                                ? number_format($overview['savings_rate'], 1) . '%'
                                : 'N/A' }}
                        </strong>
                    </div>
                    <div class="col-6 col-lg-3">
                        <div class="small text-secondary">Budget utilization</div>
                        <strong>
                            {{ number_format($budget['utilization'], 1) }}%
                        </strong>
                    </div>
                </div>
            </div>

            {{-- Income and expense categories --}}
            <div class="row g-3 mb-4">

                <div class="col-lg-6">
                    <div class="card report-panel h-100">
                        <div class="report-panel-heading">
                            <h2 class="h5 fw-bold mb-1">Expenses by category</h2>
                            <p class="text-secondary small mb-0">
                                Spending distribution by expense category.
                            </p>
                        </div>

                        @if ($expenseCategories->isEmpty())
                            <div class="report-empty">
                                <i class="fa-solid fa-receipt"></i>
                                No expenses recorded for this period.
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th class="text-center">Records</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($expenseCategories as $item)
                                            <tr>
                                                <td>{{ $item['category'] }}</td>
                                                <td class="text-center">
                                                    {{ $item['count'] }}
                                                </td>
                                                <td class="text-end fw-semibold num">
                                                    {{ Money::format($item['total']) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card report-panel h-100">
                        <div class="report-panel-heading">
                            <h2 class="h5 fw-bold mb-1">Income by source</h2>
                            <p class="text-secondary small mb-0">
                                Income grouped by category.
                            </p>
                        </div>

                        @if ($incomeCategories->isEmpty())
                            <div class="report-empty">
                                <i class="fa-solid fa-wallet"></i>
                                No income recorded for this period.
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Source</th>
                                            <th class="text-center">Records</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($incomeCategories as $item)
                                            <tr>
                                                <td>{{ $item['category'] }}</td>
                                                <td class="text-center">
                                                    {{ $item['count'] }}
                                                </td>
                                                <td class="text-end fw-semibold num">
                                                    {{ Money::format($item['total']) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

            </div>

            {{-- Merchant breakdown --}}
            <div class="card report-panel mb-4">
                <div class="report-panel-heading">
                    <h2 class="h5 fw-bold mb-1">Spending by merchant</h2>
                    <p class="text-secondary small mb-0">
                        Identify where your money is being spent.
                    </p>
                </div>

                @if ($merchants->isEmpty())
                    <div class="report-empty">
                        <i class="fa-solid fa-store"></i>
                        No merchant spending recorded.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Merchant</th>
                                    <th class="text-center">Transactions</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($merchants as $merchant)
                                    <tr>
                                        <td>{{ $merchant['merchant'] }}</td>
                                        <td class="text-center">
                                            {{ $merchant['count'] }}
                                        </td>
                                        <td class="text-end fw-semibold num">
                                            {{ Money::format($merchant['total']) }}
                                        </td>
                                        <td class="text-end">
                                            {{ $overview['expenses'] > 0
                                                ? number_format($merchant['total'] / $overview['expenses'] * 100, 1)
                                                : '0.0' }}%
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Budget variance --}}
            <div class="card report-panel mb-4">
                <div class="report-panel-heading d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h2 class="h5 fw-bold mb-1">Budget variance</h2>
                        <p class="text-secondary small mb-0">
                            Compare category allocations with actual spending.
                        </p>
                    </div>
                    <a href="{{ route('budgets.index', ['period' => $period->id]) }}"
                       class="btn btn-outline-secondary btn-sm">
                        Manage budgets
                    </a>
                </div>

                @if ($budget['categories']->isEmpty())
                    <div class="report-empty">
                        <i class="fa-solid fa-chart-pie"></i>
                        No budget allocations for this period.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-end">Allocated</th>
                                    <th class="text-end">Spent</th>
                                    <th class="text-end">Remaining</th>
                                    <th class="text-end">Used</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($budget['categories'] as $row)
                                    <tr>
                                        <td>{{ $row['category']?->name ?? 'Uncategorized' }}</td>
                                        <td class="text-end num">
                                            {{ Money::format($row['allocated']) }}
                                        </td>
                                        <td class="text-end num">
                                            {{ Money::format($row['spent']) }}
                                        </td>
                                        <td class="text-end fw-semibold num
                                            {{ $row['remaining'] < 0 ? 'text-danger' : '' }}">
                                            {{ Money::format($row['remaining']) }}
                                        </td>
                                        <td class="text-end">
                                            <span class="badge text-bg-{{
                                                $row['status'] === 'over' ? 'danger' :
                                                ($row['status'] === 'warning' || $row['status'] === 'used' ? 'warning' : 'success')
                                            }}">
                                                {{ number_format($row['utilization'], 1) }}%
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="report-panel-footer">
                    <span>Unbudgeted spending</span>
                    <strong class="num">
                        {{ Money::format($budget['unbudgeted_spending']) }}
                    </strong>
                </div>
            </div>

        @endif
    @endif
</div>
@endsection
