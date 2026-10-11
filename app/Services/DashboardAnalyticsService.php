<?php

namespace App\Services;

use App\Models\FinancialPeriod;
use App\Models\User;

class DashboardAnalyticsService
{
    /**
     * Prepare all chart datasets for the dashboard.
     *
     * Monetary values are stored in integer minor units.
     */
    public function charts(
        User $user,
        FinancialPeriod $period
    ): array {
        if ($period->user_id !== $user->id) {
            abort(403);
        }

        return [
            'income_expenses' => $this->incomeExpenses($user, $period),
            'category_spending' => $this->categorySpending($user, $period),
            'budget_performance' => $this->budgetPerformance($user, $period),
        ];
    }

    /**
     * Compare income and expenses across up to six
     * financial periods, ending with the selected period.
     */
    private function incomeExpenses(
        User $user,
        FinancialPeriod $period
    ): array {
        $periods = $user->financialPeriods()
            ->where(function ($query) use ($period) {
                $query->where('start_date', '<', $period->start_date)
                    ->orWhere(function ($q) use ($period) {
                        $q->where('start_date', $period->start_date)
                            ->where('id', '<=', $period->id);
                    });
            })
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->reverse()
            ->values();

        $ids = $periods->pluck('id');

        $incomes = $user->incomes()
            ->whereIn('financial_period_id', $ids)
            ->selectRaw('financial_period_id, SUM(amount) as total')
            ->groupBy('financial_period_id')
            ->pluck('total', 'financial_period_id');

        $expenses = $user->expenses()
            ->whereIn('financial_period_id', $ids)
            ->selectRaw('financial_period_id, SUM(amount) as total')
            ->groupBy('financial_period_id')
            ->pluck('total', 'financial_period_id');

        return [
            'labels' => $periods->pluck('name')->all(),

            'income' => $periods->map(
                fn ($p) => (int) ($incomes[$p->id] ?? 0)
            )->all(),

            'expenses' => $periods->map(
                fn ($p) => (int) ($expenses[$p->id] ?? 0)
            )->all(),
        ];
    }

    /**
     * Calculate spending distribution for all expense
     * categories, including categories without budgets.
     */
    private function categorySpending(
        User $user,
        FinancialPeriod $period
    ): array {
        $expenses = $user->expenses()
            ->where('financial_period_id', $period->id)
            ->with('category:id,name,color')
            ->get();

        $groups = $expenses
            ->groupBy('category_id')
            ->map(function ($items) {
                $category = $items->first()->category;

                return [
                    'name' => $category?->name ?? 'Uncategorized',
                    'amount' => (int) $items->sum('amount'),
                    'color' => $category?->color,
                ];
            })
            ->sortByDesc('amount')
            ->values();

        return [
            'labels' => $groups->pluck('name')->all(),
            'amounts' => $groups->pluck('amount')->all(),
            'colors' => $groups->pluck('color')->all(),
        ];
    }

    /**
     * Compare allocations against actual spending.
     */
    private function budgetPerformance(
        User $user,
        FinancialPeriod $period
    ): array {
        $budgets = $user->budgets()
            ->where('financial_period_id', $period->id)
            ->with('category:id,name')
            ->get();

        $spending = $user->expenses()
            ->where('financial_period_id', $period->id)
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        return [
            'labels' => $budgets->map(
                fn ($b) => $b->category?->name ?? 'Uncategorized'
            )->all(),

            'allocated' => $budgets->map(
                fn ($b) => (int) $b->amount
            )->all(),

            'spent' => $budgets->map(
                fn ($b) => (int) ($spending[$b->category_id] ?? 0)
            )->all(),
        ];
    }
}
