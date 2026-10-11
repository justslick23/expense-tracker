<?php

namespace App\Http\Controllers;

use App\Services\FinancialReportService;
use App\Services\PeriodService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\ReportExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;
class FinancialReportController extends Controller
{
    public function __construct(
        private FinancialReportService $reports,
        private PeriodService $periods,
        private ReportExportService $exports
    ) {}

    /**
     * Display a detailed report for a selected
     * financial period.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $periods = $user->financialPeriods()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        $validated = $request->validate([
            'period' => [
                'nullable',
                'integer',
                Rule::exists('financial_periods', 'id')
                    ->where('user_id', $user->id),
            ],
        ]);

        $selectedId = $validated['period'] ?? null;

        if ($selectedId !== null) {
            $period = $periods->firstWhere(
                'id',
                (int) $selectedId
            );
        } else {
            // Prefer the active period.
            $period = $this->periods->current($user);

            // Otherwise use the most recent period.
            $period ??= $periods->first();
        }

        $report = $period
            ? $this->reports->periodReport($user, $period)
            : null;

        return view('reports.index', compact(
            'periods',
            'period',
            'report'
        ));
    }

    /**
     * Compare multiple historical financial periods.
     *
     * Defaults to the six most recent periods.
     */
    public function history(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'limit' => [
                'nullable',
                'integer',
                Rule::in([3, 6, 12, 24]),
            ],
        ]);

        $limit = (int) ($validated['limit'] ?? 6);

        $periods = $user->financialPeriods()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $history = $this->reports->historicalReport(
            $user,
            $periods
        );

        $totals = [
            'income' => (int) $history->sum('income'),
            'expenses' => (int) $history->sum('expenses'),
            'net_cash_flow' => (int) $history->sum('net_cash_flow'),
            'budget' => (int) $history->sum('budget'),
        ];

        $totals['remaining_budget'] =
            $totals['budget'] - $totals['expenses'];

        $totals['budget_utilization'] =
            $totals['budget'] > 0
                ? round(
                    ($totals['expenses'] / $totals['budget']) * 100,
                    1
                )
                : 0;

        return view('reports.history', compact(
            'history',
            'totals',
            'limit'
        ));
    }

    
/**
 * Export a detailed financial period report.
 */
public function export(
    Request $request
): StreamedResponse {
    $user = $request->user();

    $validated = $request->validate([
        'period' => [
            'required',
            'integer',
            Rule::exists('financial_periods', 'id')
                ->where('user_id', $user->id),
        ],
    ]);

    $period = $user->financialPeriods()
        ->findOrFail($validated['period']);

    return $this->exports->period(
        $user,
        $period
    );
}

/**
 * Export historical financial comparison data.
 */
public function exportHistory(
    Request $request
): StreamedResponse {
    $user = $request->user();

    $validated = $request->validate([
        'limit' => [
            'nullable',
            'integer',
            Rule::in([3, 6, 12, 24]),
        ],
    ]);

    $limit = (int) ($validated['limit'] ?? 6);

    $periods = $user->financialPeriods()
        ->orderByDesc('start_date')
        ->orderByDesc('id')
        ->limit($limit)
        ->get();

    return $this->exports->history(
        $user,
        $periods
    );
}

}
