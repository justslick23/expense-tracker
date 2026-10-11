<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Category;
use App\Models\FinancialPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BudgetService
{
    /**
     * Create or update a category budget.
     *
     * The amount must already be converted to minor units.
     */
    public function save(
        User $user,
        array $data,
        bool $confirmed = false,
        ?Budget $budget = null
    ): Budget {
        return DB::transaction(function () use (
            $user,
            $data,
            $confirmed,
            $budget
        ) {
            if ($budget && $budget->user_id !== $user->id) {
                abort(403);
            }

            $period = $user->financialPeriods()
                ->lockForUpdate()
                ->findOrFail($data['financial_period_id']);

            $category = $user->categories()
                ->findOrFail($data['category_id']);

            $this->validateCategory($category, $budget);
            $this->validateAmount($data['amount']);

            $this->guardClosed(
                $user,
                $confirmed,
                $period,
                $budget?->period
            );

            $duplicate = $user->budgets()
                ->where('financial_period_id', $period->id)
                ->where('category_id', $category->id)
                ->when(
                    $budget,
                    fn ($query) => $query->where(
                        'id',
                        '!=',
                        $budget->id
                    )
                )
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'category_id' =>
                        'This category already has a budget for the selected financial period.',
                ]);
            }

            $budget ??= $user->budgets()->make();

            $budget->fill([
                'financial_period_id' => $period->id,
                'category_id' => $category->id,
                'amount' => $data['amount'],
            ]);

            $budget->save();

            return $budget;
        });
    }

    /**
     * Delete a budget allocation.
     *
     * Associated expenses are never deleted.
     */
    public function delete(
        User $user,
        Budget $budget,
        bool $confirmed = false
    ): void {
        if ($budget->user_id !== $user->id) {
            abort(403);
        }

        DB::transaction(function () use (
            $user,
            $budget,
            $confirmed
        ) {
            $period = $budget->period;

            $this->guardClosed(
                $user,
                $confirmed,
                $period
            );

            $budget->delete();
        });
    }

    /**
     * Calculate category budget performance.
     *
     * All monetary values remain integers in minor units.
     */
    public function summary(
        User $user,
        FinancialPeriod $period
    ): array {
        if ($period->user_id !== $user->id) {
            abort(403);
        }

        $budgets = $user->budgets()
            ->where('financial_period_id', $period->id)
            ->with('category')
            ->orderBy('category_id')
            ->get();

        // Soft-deleted expenses are excluded automatically.
        $spending = $user->expenses()
            ->where('financial_period_id', $period->id)
            ->select('category_id')
            ->selectRaw('SUM(amount) as total_spent')
            ->groupBy('category_id')
            ->pluck('total_spent', 'category_id');

        $rows = $budgets->map(function ($budget) use ($spending) {
            $allocated = (int) $budget->amount;

            $spent = (int) (
                $spending[$budget->category_id] ?? 0
            );

            $remaining = $allocated - $spent;

            $utilization = $allocated > 0
                ? round(($spent / $allocated) * 100, 1)
                : 0;

            return [
                'budget' => $budget,
                'category' => $budget->category,
                'allocated' => $allocated,
                'spent' => $spent,
                'remaining' => $remaining,
                'utilization' => $utilization,
                'status' => $this->budgetStatus(
                    $allocated,
                    $spent
                ),
            ];
        });

        $totalAllocated = (int) $rows->sum('allocated');
        $totalSpent = (int) $rows->sum('spent');

        // This includes expenses in categories without budgets.
        $allExpenses = (int) $user->expenses()
            ->where('financial_period_id', $period->id)
            ->sum('amount');

        return [
            'period' => $period,
            'categories' => $rows,
            'total_allocated' => $totalAllocated,
            'budgeted_spending' => $totalSpent,
            'unbudgeted_spending' => $allExpenses - $totalSpent,
            'total_spent' => $allExpenses,
            'remaining' => $totalAllocated - $allExpenses,
            'utilization' => $totalAllocated > 0
                ? round(($allExpenses / $totalAllocated) * 100, 1)
                : 0,
        ];
    }

    /**
     * Validate that a category can receive a budget.
     */
    private function validateCategory(
        Category $category,
        ?Budget $budget
    ): void {
        if ($category->type !== 'expense') {
            throw ValidationException::withMessages([
                'category_id' =>
                    'Budgets can only be assigned to expense categories.',
            ]);
        }

        if (
            $category->isArchived()
            && $budget?->category_id !== $category->id
        ) {
            throw ValidationException::withMessages([
                'category_id' =>
                    'Archived categories cannot receive new budgets.',
            ]);
        }
    }

    /**
     * Validate a monetary amount stored in minor units.
     */
    private function validateAmount(mixed $amount): void
    {
        if (! is_int($amount) || $amount <= 0) {
            throw ValidationException::withMessages([
                'amount' =>
                    'The budget amount must be greater than zero.',
            ]);
        }
    }

    /**
     * Require confirmation for modifications to closed periods.
     */
    private function guardClosed(
        User $user,
        bool $confirmed,
        ?FinancialPeriod ...$periods
    ): void {
        $closed = collect($periods)
            ->first(fn ($period) =>
                $period && ! $period->isOpen()
            );

        if (! $closed) {
            return;
        }

        if (! $confirmed) {
            throw ValidationException::withMessages([
                'confirm_closed' =>
                    "This change affects the closed period \"{$closed->name}\". Confirm before proceeding.",
            ]);
        }

        Log::info('Closed-period budget change confirmed', [
            'user_id' => $user->id,
            'period_id' => $closed->id,
        ]);
    }

    /**
     * Determine category budget status.
     */
    private function budgetStatus(
        int $allocated,
        int $spent
    ): string {
        if ($spent > $allocated) {
            return 'over';
        }

        if ($spent === $allocated) {
            return 'used';
        }

        if ($allocated > 0 && ($spent / $allocated) >= 0.8) {
            return 'warning';
        }

        return 'healthy';
    }
}
