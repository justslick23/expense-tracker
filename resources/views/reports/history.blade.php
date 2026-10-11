
@extends('layouts.shell')

@use('App\Support\Money')

@section('title', 'Historical Reports')

@section('content')
<div class="financial-reports">

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <a href="{{ route('reports.index') }}"
               class="text-decoration-none small d-inline-block mb-2">
                <i class="fa-solid fa-arrow-left me-1"></i>
                Financial reports
            </a>

            <h1 class="h3 fw-bold mb-1">Historical Reports</h1>
            <p class="text-secondary mb-0">
                Compare your financial performance across different periods.
            </p>
        </div>

        <a href="{{ route('reports.history.export', ['limit' => $limit]) }}"
            class="btn btn-outline-secondary">
             <i class="fa-solid fa-file-arrow-down me-2"></i>
             Export history
         </a>

        <form action="{{ route('reports.history') }}" method="GET">
            <label for="reportLimit" class="form-label small">
                Show recent periods
            </label>
            <select name="limit"
                    id="reportLimit"
                    class="form-select"
                    onchange="this.form.submit()">
                @foreach ([3, 6, 12, 24] as $option)
                    <option value="{{ $option }}" @selected($limit === $option)>
                        Last {{ $option }} periods
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    @if ($history->isEmpty())
        <div class="card p-5 text-center">
            <i class="fa-solid fa-chart-line fa-2x text-secondary mb-3"></i>
            <h2 class="h5">No historical data available</h2>
            <p class="text-secondary">
                Your financial periods will appear here once created.
            </p>
            <div>
                <a href="{{ route('periods.create') }}"
                   class="btn btn-primary">
                    Create financial period
                </a>
            </div>
        </div>
    @else

        {{-- Historical totals --}}
        <div class="row g-3 mb-4">
            @foreach ([
                ['label' => 'Total income', 'value' => $totals['income'], 'icon' => 'fa-arrow-trend-up'],
                ['label' => 'Total expenses', 'value' => $totals['expenses'], 'icon' => 'fa-arrow-trend-down'],
                ['label' => 'Net cash flow', 'value' => $totals['net_cash_flow'], 'icon' => 'fa-scale-balanced'],
                ['label' => 'Total allocated', 'value' => $totals['budget'], 'icon' => 'fa-wallet'],
            ] as $metric)
                <div class="col-sm-6 col-xl-3">
                    <div class="card report-stat h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="small text-secondary">
                                {{ $metric['label'] }}
                            </span>
                            <i class="fa-solid {{ $metric['icon'] }} text-secondary"></i>
                        </div>
                        <div class="h4 fw-bold num mb-0">
                            {{ Money::format($metric['value']) }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Comparison chart --}}
        <div class="card report-panel mb-4">
            <div class="report-panel-heading">
                <h2 class="h5 fw-bold mb-1">Income vs Expenses</h2>
                <p class="text-secondary small mb-0">
                    Financial trends across {{ $history->count() }}
                    {{ \Illuminate\Support\Str::plural('period', $history->count()) }}.
                </p>
            </div>

            <div class="p-4">
                @if ($totals['income'] != 0 || $totals['expenses'] != 0)
                    <div class="report-chart">
                        <canvas id="historicalReportChart"
                                role="img"
                                aria-label="Historical income, expenses and net cash flow chart">
                        </canvas>
                    </div>
                @else
                    <div class="report-empty">
                        <i class="fa-solid fa-chart-column"></i>
                        No income or expense data to visualize yet.
                    </div>
                @endif
            </div>
        </div>

        {{-- Historical comparison table --}}
        <div class="card report-panel mb-4">
            <div class="report-panel-heading">
                <h2 class="h5 fw-bold mb-1">Period comparison</h2>
                <p class="text-secondary small mb-0">
                    Detailed financial totals for each period.
                </p>
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Financial period</th>
                            <th class="text-end">Income</th>
                            <th class="text-end">Expenses</th>
                            <th class="text-end">Net cash flow</th>
                            <th class="text-end">Budget</th>
                            <th class="text-end">Budget used</th>
                            <th class="text-end">Report</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($history as $row)
                            <tr>
                                <td>
                                    <div class="fw-semibold">
                                        {{ $row['period']->name }}
                                    </div>
                                    <div class="text-secondary small">
                                        {{ $row['period']->start_date->format('d M Y') }}
                                        —
                                        {{ $row['period']->end_date?->format('d M Y') ?? 'Ongoing' }}
                                    </div>
                                </td>
                                <td class="text-end num">
                                    {{ Money::format($row['income']) }}
                                </td>
                                <td class="text-end num">
                                    {{ Money::format($row['expenses']) }}
                                </td>
                                <td class="text-end fw-semibold num
                                    {{ $row['net_cash_flow'] < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ Money::format($row['net_cash_flow']) }}
                                </td>
                                <td class="text-end num">
                                    {{ Money::format($row['budget']) }}
                                </td>
                                <td class="text-end">
                                    {{ $row['budget'] > 0
                                        ? number_format($row['budget_utilization'], 1) . '%'
                                        : 'N/A' }}
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('reports.index', ['period' => $row['period']->id]) }}"
                                       class="btn btn-sm btn-outline-secondary"
                                       aria-label="View report for {{ $row['period']->name }}">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tfoot>
                        <tr class="fw-bold">
                            <td>Total</td>
                            <td class="text-end num">
                                {{ Money::format($totals['income']) }}
                            </td>
                            <td class="text-end num">
                                {{ Money::format($totals['expenses']) }}
                            </td>
                            <td class="text-end num">
                                {{ Money::format($totals['net_cash_flow']) }}
                            </td>
                            <td class="text-end num">
                                {{ Money::format($totals['budget']) }}
                            </td>
                            <td class="text-end">
                                {{ $totals['budget'] > 0
                                    ? number_format($totals['budget_utilization'], 1) . '%'
                                    : 'N/A' }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <p class="text-secondary small">
            <i class="fa-solid fa-circle-info me-1"></i>
            Reports compare full recorded period totals.
            Open periods may have incomplete data, and period lengths may differ.
        </p>
    @endif
</div>


@if ($history->isNotEmpty() && ($totals['income'] != 0 || $totals['expenses'] != 0))
    @php
        $historicalChartData = [
            'labels' => $history->pluck('period.name')->values()->all(),
            'income' => $history->pluck('income')->values()->all(),
            'expenses' => $history->pluck('expenses')->values()->all(),
            'cashFlow' => $history->pluck('net_cash_flow')->values()->all(),
        ];
    @endphp

    @push('scripts')
        <script>
            window.historicalReportData = {{ Illuminate\Support\Js::from($historicalChartData) }};
        </script>

        <script src="{{ asset('js/report-charts.js') }}"></script>
    @endpush
@endif

@endsection


