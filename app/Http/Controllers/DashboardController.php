<?php

namespace App\Http\Controllers;

use App\Models\FinancialPeriod;
use App\Services\DashboardService;
use App\Services\PeriodService;
use Illuminate\Http\Request;
use App\Services\DashboardAnalyticsService;
use App\Services\FinancialInsightsService;
use App\Services\RecurringDashboardService;
use App\Services\SavingsDashboardService;
use Illuminate\Support\Facades\Cache;
class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboard,
        private PeriodService $periods,
        private DashboardAnalyticsService $analytics,
        private FinancialInsightsService $insights
    ) {}

    /**
     * Display the financial dashboard.
     */
    public function index(Request $request, RecurringDashboardService $recurringDashboard, SavingsDashboardService $savingsDashboard
    )
    {
        $user = $request->user();

        // Retrieve financial periods belonging to the user.
        $periods = $user->financialPeriods()
            ->orderByDesc('start_date')
            ->get();

        // Determine the requested financial period.
        $selectedId = $request->query('period');

        if (
            $selectedId !== null
            && (
                ! is_scalar($selectedId)
                || ! ctype_digit((string) $selectedId)
            )
        ) {
            abort(404);
        }

        if ($selectedId !== null) {
            $period = $periods->firstWhere(
                'id',
                (int) $selectedId
            );

            if (! $period) {
                abort(404);
                
            }
        } else {
            // Prefer the currently open financial period.
            $period = $this->periods->current($user);

            // Otherwise fall back to the most recent period.
            $period ??= $periods->first();
        }

        // Handle users without financial periods.
        if (! $period) {
            return view('dashboard', [
                'period' => null,
                'periods' => $periods,
                'summary' => null,
                'recentTransactions' => collect(),
                'charts' => null,
                'insights' => null,
            ]);
        }

        // Retrieve financial calculations.
        $summary = $this->dashboard->summary(
            $user,
            $period
        );

        $charts = $this->analytics->charts($user, $period);
        $insights = $this->insights->analyze($user, $period);
        // Retrieve recent expenses.
        $expenses = $user->expenses()
            ->where('financial_period_id', $period->id)
            ->with('category')
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'type' => 'expense',
                    'date' => $expense->expense_date,
                    'description' => $expense->description
                        ?: 'Expense',
                    'category' => $expense->category,
                    'amount' => $expense->amount,
                    'merchant' => $expense->merchant,
                    'url' => route(
                        'expenses.edit',
                        $expense
                    ),
                ];
            });

        // Retrieve recent income.
        $incomes = $user->incomes()
            ->where('financial_period_id', $period->id)
            ->with('category')
            ->orderByDesc('income_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function ($income) {
                return [
                    'id' => $income->id,
                    'type' => 'income',
                    'date' => $income->income_date,
                    'description' => $income->description
                        ?: 'Income',
                    'category' => $income->category,
                    'amount' => $income->amount,
                    'merchant' => null,
                    'url' => route(
                        'incomes.edit',
                        $income
                    ),
                ];
            });

        // Merge income and expenses into one timeline.
        $recentTransactions = $expenses
            ->concat($incomes)
            ->sort(function ($a, $b) {
                $dateComparison = $b['date']->timestamp
                    <=> $a['date']->timestamp;

                if ($dateComparison !== 0) {
                    return $dateComparison;
                }

                return $b['id'] <=> $a['id'];
            })
            ->take(10)
            ->values();

            $recurringSummary = $recurringDashboard->summary(
                $request->user()
            );
            $savingsSummary = $savingsDashboard->summary($request->user());

            
/*
|--------------------------------------------------------------------------
| Cached AI Financial Analysis
|--------------------------------------------------------------------------
*/

$aiCacheKey = "financial_ai:user:{$user->id}:period:{$period->id}";

$aiFinancialAnalysis = Cache::get($aiCacheKey);

// Ignore malformed or unexpected cache contents.
if (
    ! is_array($aiFinancialAnalysis) ||
    ! isset($aiFinancialAnalysis['analysis']) ||
    ! is_array($aiFinancialAnalysis['analysis'])
) {
    $aiFinancialAnalysis = null;
}

            return view('dashboard', compact(
                'period',
                'periods',
                'summary',
                'recentTransactions',
                'charts',
                'insights',
                'recurringSummary',
                'savingsSummary',
                'aiFinancialAnalysis'

            ));
    }
}
