<?php

namespace App\Services;

use App\Models\FinancialPeriod;
use App\Models\User;
use Illuminate\Support\Collection;

class FinancialReportService
{
    public function __construct(
        private BudgetService $budgets
    ) {}

    /**
     * Generate a complete report for a financial period.
     *
     * All monetary amounts are integer minor units.
     */
    public function periodReport(
        User $user,
        FinancialPeriod $period
    ): array {
        $this->authorizePeriod($user, $period);

        $income = $user->incomes()
            ->where('financial_period_id', $period->id);

        $expenses = $user->expenses()
            ->where('financial_period_id', $period->id);

        $totalIncome = (int) (clone $income)->sum('amount');
        $totalExpenses = (int) (clone $expenses)->sum('amount');

        $budgetSummary = $this->budgets->summary(
            $user,
            $period
        );

        return [
            'period' => $period,
            'overview' => [
                'income' => $totalIncome,
                'expenses' => $totalExpenses,
                'net_cash_flow' => $totalIncome - $totalExpenses,
                'income_count' => (clone $income)->count(),
                'expense_count' => (clone $expenses)->count(),
                'savings_rate' => $totalIncome > 0
                    ? round(
                        (($totalIncome - $totalExpenses) / $totalIncome) * 100,
                        1
                    )
                    : null,
            ],

            'expenses_by_category' =>
                $this->expensesByCategory($user, $period),

            'expenses_by_merchant' =>
                $this->expensesByMerchant($user, $period),

            'income_by_category' =>
                $this->incomeByCategory($user, $period),

            'budget' => $budgetSummary,
        ];
    }

    /**
     * Expense totals grouped by category.
     */
    public function expensesByCategory(
        User $user,
        FinancialPeriod $period
    ): Collection {
        $this->authorizePeriod($user, $period);

        return $user->expenses()
            ->where('financial_period_id', $period->id)
            ->with('category')
            ->get()
            ->groupBy('category_id')
            ->map(function (Collection $items) {
                $category = $items->first()->category;

                return [
                    'category_id' => $category?->id,
                    'category' => $category?->name ?? 'Uncategorized',
                    'color' => $category?->color,
                    'total' => (int) $items->sum('amount'),
                    'count' => $items->count(),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Expense totals grouped by merchant.
     * Empty merchant values are labelled "Unspecified".
     */
    public function expensesByMerchant(
        User $user,
        FinancialPeriod $period
    ): Collection {
        $this->authorizePeriod($user, $period);

        return $user->expenses()
            ->where('financial_period_id', $period->id)
            ->get(['merchant', 'amount'])
            ->groupBy(function ($expense) {
                $merchant = trim((string) $expense->merchant);

                return $merchant === ''
                    ? 'Unspecified'
                    : $merchant;
            })
            ->map(function (Collection $items, string $merchant) {
                return [
                    'merchant' => $merchant,
                    'total' => (int) $items->sum('amount'),
                    'count' => $items->count(),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Income totals grouped by income category.
     */
    public function incomeByCategory(
        User $user,
        FinancialPeriod $period
    ): Collection {
        $this->authorizePeriod($user, $period);

        return $user->incomes()
            ->where('financial_period_id', $period->id)
            ->with('category')
            ->get()
            ->groupBy('category_id')
            ->map(function (Collection $items) {
                $category = $items->first()->category;

                return [
                    'category_id' => $category?->id,
                    'category' => $category?->name ?? 'Uncategorized',
                    'color' => $category?->color,
                    'total' => (int) $items->sum('amount'),
                    'count' => $items->count(),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Compare financial totals across multiple periods.
     *
     * The provided periods must all belong to the user.
     */
    public function historicalReport(
        User $user,
        Collection $periods
    ): Collection {
        if ($periods->isEmpty()) {
            return collect();
        }

        foreach ($periods as $period) {
            $this->authorizePeriod($user, $period);
        }

        $periods = $periods
            ->sortBy('start_date')
            ->values();

        $periodIds = $periods->pluck('id');

        $incomeTotals = $user->incomes()
            ->whereIn('financial_period_id', $periodIds)
            ->selectRaw('financial_period_id, SUM(amount) as total')
            ->groupBy('financial_period_id')
            ->pluck('total', 'financial_period_id');

        $expenseTotals = $user->expenses()
            ->whereIn('financial_period_id', $periodIds)
            ->selectRaw('financial_period_id, SUM(amount) as total')
            ->groupBy('financial_period_id')
            ->pluck('total', 'financial_period_id');

        $budgetTotals = $user->budgets()
            ->whereIn('financial_period_id', $periodIds)
            ->selectRaw('financial_period_id, SUM(amount) as total')
            ->groupBy('financial_period_id')
            ->pluck('total', 'financial_period_id');

        return $periods->map(function ($period) use (
            $incomeTotals,
            $expenseTotals,
            $budgetTotals
        ) {
            $income = (int) ($incomeTotals[$period->id] ?? 0);
            $expenses = (int) ($expenseTotals[$period->id] ?? 0);
            $budget = (int) ($budgetTotals[$period->id] ?? 0);

            return [
                'period' => $period,
                'income' => $income,
                'expenses' => $expenses,
                'net_cash_flow' => $income - $expenses,
                'budget' => $budget,
                'remaining_budget' => $budget - $expenses,
                'budget_utilization' => $budget > 0
                    ? round(($expenses / $budget) * 100, 1)
                    : 0,
            ];
        });
    }

    /**
     * Prevent access to another user's financial records.
     */
    private function authorizePeriod(
        User $user,
        FinancialPeriod $period
    ): void {
        if ($period->user_id !== $user->id) {
            abort(403);
        }
    }
}
