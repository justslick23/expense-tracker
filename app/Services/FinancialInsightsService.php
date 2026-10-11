<?php

namespace App\Services;

use App\Models\FinancialPeriod;
use App\Models\User;
use Illuminate\Support\Carbon;

class FinancialInsightsService
{
    public function __construct(
        private BudgetService $budgets
    ) {}

    /**
     * Generate financial insights for a selected period.
     * All money values are integer minor units.
     */
    public function analyze(
        User $user,
        FinancialPeriod $period
    ): array {
        if ($period->user_id !== $user->id) {
            abort(403);
        }

        $cutoffDate = $period->isOpen()
    ? Carbon::today()->toDateString()
    : ($period->end_date?->toDateString() ?? Carbon::today()->toDateString());

$elapsedExpenses = (int) $user->expenses()
    ->where('financial_period_id', $period->id)
    ->whereDate('expense_date', '<=', $cutoffDate)
    ->sum('amount');

        $budget = $this->budgets->summary($user, $period);

        $income = (int) $user->incomes()
            ->where('financial_period_id', $period->id)
            ->sum('amount');

        $expenses = (int) $user->expenses()
            ->where('financial_period_id', $period->id)
            ->sum('amount');

        $previous = $user->financialPeriods()
            ->where(function ($query) use ($period) {
                $query->where('start_date', '<', $period->start_date)
                    ->orWhere(function ($q) use ($period) {
                        $q->where('start_date', $period->start_date)
                            ->where('id', '<', $period->id);
                    });
            })
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        $comparison = $this->comparePeriods(
            $user,
            $income,
            $expenses,
            $previous
        );

        $velocity = $this->spendingVelocity(
            $period,
            $elapsedExpenses
        );

        $alerts = $this->budgetAlerts($budget);

        $recommendations = $this->recommendations(
            $income,
            $expenses,
            $comparison,
            $velocity,
            $alerts,
            $budget
        );

        return [
            'comparison' => $comparison,
            'velocity' => $velocity,
            'alerts' => $alerts,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Compare current and previous periods.
     */
    private function comparePeriods(
        User $user,
        int $income,
        int $expenses,
        ?FinancialPeriod $previous
    ): array {
        if (! $previous) {
            return [
                'available' => false,
                'period' => null,
                'income_change' => null,
                'expense_change' => null,
                'cash_flow_change' => null,
            ];
        }

        $previousIncome = (int) $user->incomes()
            ->where('financial_period_id', $previous->id)
            ->sum('amount');

        $previousExpenses = (int) $user->expenses()
            ->where('financial_period_id', $previous->id)
            ->sum('amount');

        $previousCashFlow = $previousIncome - $previousExpenses;
        $currentCashFlow = $income - $expenses;

        return [
            'available' => true,
            'period' => $previous,
            'previous_income' => $previousIncome,
            'previous_expenses' => $previousExpenses,
            'previous_cash_flow' => $previousCashFlow,
            'income_change' => $this->percentageChange(
                $previousIncome,
                $income
            ),
            'expense_change' => $this->percentageChange(
                $previousExpenses,
                $expenses
            ),
            'cash_flow_change' => $this->percentageChange(
                $previousCashFlow,
                $currentCashFlow
            ),
        ];
    }

    /**
     * Return null when a percentage change is undefined,
     * such as when the previous value is zero.
     */
    private function percentageChange(
        int $previous,
        int $current
    ): ?float {
        if ($previous === 0) {
            return null;
        }

        return round(
            (($current - $previous) / abs($previous)) * 100,
            1
        );
    }

    /**
     * Calculate spending pace and projected spending.
     *
     * Open periods without an end date have no reliable
     * end-of-period projection.
     */
    private function spendingVelocity(
        FinancialPeriod $period,
        int $expenses
    ): array {
        $start = Carbon::parse($period->start_date)
            ->startOfDay();

        $end = $period->end_date
            ? Carbon::parse($period->end_date)->startOfDay()
            : null;

        // For closed periods, use the closing date.
        // For open periods, use today.
        $today = Carbon::today();

        $cutoff = $period->isOpen()
            ? $today
            : ($end ?? $today);

        if ($end && $cutoff->gt($end)) {
            $cutoff = $end->copy();
        }

        

        // Number of elapsed days, inclusive.
        $elapsed = $cutoff->lt($start)
            ? 0
            : (int) $start->diffInDays($cutoff) + 1;

        $duration = $end
            ? (int) $start->diffInDays($end) + 1
            : null;

        $remaining = $duration !== null
            ? max(0, $duration - $elapsed)
            : null;

        $dailyAverage = $elapsed > 0
            ? round($expenses / $elapsed)
            : null;

        $projected = ($period->isOpen()
            && $remaining !== null
            && $dailyAverage !== null)
            ? $expenses + (int) round(
                ($expenses / $elapsed) * $remaining
            )
            : null;

        return [
            'days_elapsed' => $elapsed,
            'days_remaining' => $remaining,
            'duration' => $duration,
            'daily_average' => $dailyAverage,
            'projected_spending' => $projected,
            'projection_available' => $projected !== null,
        ];
    }

    /**
     * Identify category budgets approaching their limit.
     */
    private function budgetAlerts(array $summary): array
    {
        return $summary['categories']
            ->filter(fn ($row) => $row['utilization'] >= 80)
            ->map(function ($row) {
                $over = $row['spent'] > $row['allocated'];

                return [
                    'category' => $row['category']->name,
                    'category_id' => $row['category']->id,
                    'allocated' => $row['allocated'],
                    'spent' => $row['spent'],
                    'remaining' => $row['remaining'],
                    'utilization' => $row['utilization'],
                    'level' => $over
                        ? 'danger'
                        : 'warning',
                ];
            })
            ->sortByDesc('utilization')
            ->values()
            ->all();
    }

    /**
     * Generate straightforward rule-based recommendations.
     * These are not AI-generated financial advice.
     */
    private function recommendations(
        int $income,
        int $expenses,
        array $comparison,
        array $velocity,
        array $alerts,
        array $budget
    ): array {
        $items = [];

        if ($income > 0 && $expenses > $income) {
            $items[] = [
                'title' => 'Spending exceeds income',
                'message' =>
                    'Your recorded expenses are higher than your income for this period.',
                'level' => 'danger',
                'icon' => 'fa-triangle-exclamation',
            ];
        }

        if (
            $budget['total_allocated'] > 0
            && $budget['remaining'] < 0
        ) {
            $items[] = [
                'title' => 'Overall budget exceeded',
                'message' =>
                    'Your total expenses have exceeded your planned allocations.',
                'level' => 'danger',
                'icon' => 'fa-wallet',
            ];
        }

        foreach (array_slice($alerts, 0, 3) as $alert) {
            $items[] = [
                'title' => $alert['level'] === 'danger'
                    ? $alert['category'] . ' is over budget'
                    : $alert['category'] . ' is nearing its budget',

                'message' => $alert['level'] === 'danger'
                    ? 'Spending has exceeded the allocation for this category.'
                    : 'This category has used at least 80% of its allocation.',

                'level' => $alert['level'],
                'icon' => 'fa-chart-pie',
            ];
        }

        if (
            $comparison['available']
            && $comparison['expense_change'] !== null
            && $comparison['expense_change'] > 20
        ) {
            $items[] = [
                'title' => 'Spending increased',
                'message' =>
                    'Your expenses are more than 20% higher than the previous financial period.',
                'level' => 'warning',
                'icon' => 'fa-arrow-trend-up',
            ];
        }

        if (
            $velocity['projection_available']
            && $budget['total_allocated'] > 0
            && $velocity['projected_spending'] > $budget['total_allocated']
        ) {
            $items[] = [
                'title' => 'Projected overspending',
                'message' =>
                    'At your current daily spending pace, you may exceed your allocated budget.',
                'level' => 'warning',
                'icon' => 'fa-gauge-high',
            ];
        }

        if ($budget['unbudgeted_spending'] > 0) {
            $items[] = [
                'title' => 'Unbudgeted expenses',
                'message' =>
                    'Some expenses belong to categories without budget allocations.',
                'level' => 'info',
                'icon' => 'fa-circle-info',
            ];
        }

        return array_slice($items, 0, 6);
    }
}
