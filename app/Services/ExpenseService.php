<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\FinancialPeriod;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function __construct(
        private PeriodService $periods
    ) {}

    /**
     * Create or update an expense.
     */
    public function save(
        User $user,
        array $data,
        bool $confirmed,
        ?Expense $expense = null
    ): Expense {
        return DB::transaction(function () use (
            $user,
            $data,
            $confirmed,
            $expense
        ) {
            // Determine the financial period from the expense date.
            $period = $this->periods->forDate(
                $user,
                Carbon::parse($data['expense_date'])
            );

            // Prevent unconfirmed modifications to closed periods.
            $this->guardClosed(
                $user,
                $confirmed,
                $period,
                $expense?->period
            );

            // Always create records through the authenticated user.
            if ($expense === null) {
                $expense = $user->expenses()->make();
            } elseif ($expense->user_id !== $user->id) {
                abort(403);
            }

            $expense->fill([
                'financial_period_id' => $period?->id,
                'category_id' => $data['category_id'],
                'amount' => $data['amount'],
                'expense_date' => $data['expense_date'],
                'description' => $data['description'] ?? null,
                'merchant' => $data['merchant'] ?? null,
            ]);

            $expense->save();

            return $expense;
        });
    }

    /**
     * Soft-delete an expense.
     */
    public function delete(
        User $user,
        Expense $expense,
        bool $confirmed
    ): void {
        if ($expense->user_id !== $user->id) {
            abort(403);
        }

        DB::transaction(function () use (
            $user,
            $expense,
            $confirmed
        ) {
            $this->guardClosed(
                $user,
                $confirmed,
                $expense->period
            );

            $expense->delete();
        });
    }

    /**
     * Require explicit confirmation for closed-period changes.
     */
    private function guardClosed(
        User $user,
        bool $confirmed,
        ?FinancialPeriod ...$periods
    ): void {
        $closed = collect($periods)->first(
            fn ($period) => $period && ! $period->isOpen()
        );

        if (! $closed) {
            return;
        }

        if (! $confirmed) {
            throw ValidationException::withMessages([
                'confirm_closed' =>
                    "This change affects the closed period \"{$closed->name}\". Tick the confirmation box to proceed.",
            ]);
        }

        // Never log financial amounts or sensitive descriptions.
        Log::info('Closed-period expense change confirmed', [
            'user_id' => $user->id,
            'period_id' => $closed->id,
        ]);
    }
}
