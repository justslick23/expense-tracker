<?php

namespace App\Services;

use App\Models\FinancialPeriod;
use App\Models\User;

class DashboardService
{
    public function __construct(
        private BudgetService $budgets
    ) {}

    /**
     * Get the financial summary for a selected period.
     *
     * All monetary values are integer minor units.
     */
    public function summary(
        User $user,
        FinancialPeriod $period
    ): array {
        if ($period->user_id !== $user->id) {
            abort(403);
        }

        $totalIncome = (int) $user->incomes()
            ->where('financial_period_id', $period->id)
            ->sum('amount');

        $totalExpenses = (int) $user->expenses()
            ->where('financial_period_id', $period->id)
            ->sum('amount');

        $netCashFlow = $totalIncome - $totalExpenses;

        $budget = $this->budgets->summary($user, $period);

        $savingsRate = $totalIncome > 0
            ? round(($netCashFlow / $totalIncome) * 100, 1)
            : null;

        return [
            'period' => $period,

            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'net_cash_flow' => $netCashFlow,

            'savings_rate' => $savingsRate,

            'total_budget' => $budget['total_allocated'],
            'remaining_budget' => $budget['remaining'],
            'budget_utilization' => $budget['utilization'],

            'budgeted_spending' => $budget['budgeted_spending'],
            'unbudgeted_spending' => $budget['unbudgeted_spending'],

            'category_budgets' => $budget['categories'],

            'income_count' => $user->incomes()
                ->where('financial_period_id', $period->id)
                ->count(),

            'expense_count' => $user->expenses()
                ->where('financial_period_id', $period->id)
                ->count(),
        ];
    }
}
