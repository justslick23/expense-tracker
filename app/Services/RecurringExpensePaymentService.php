<?php

namespace App\Services;

use App\Models\FinancialPeriod;
use App\Models\RecurringExpenseOccurrence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RecurringExpensePaymentService
{
    public function __construct(
        private PeriodService $periods
    ) {}

    /**
     * Confirm payment and create the corresponding expense.
     *
     * Amounts are integer minor units.
     */
    public function pay(
        User $user,
        RecurringExpenseOccurrence $occurrence,
        int $amount,
        string $paidDate,
        bool $confirmClosed = false
    ): RecurringExpenseOccurrence {
        Validator::make(
            [
                'amount' => $amount,
                'paid_date' => $paidDate,
            ],
            [
                'amount' => ['required', 'integer', 'min:1'],
                'paid_date' => [
                    'required',
                    'date_format:Y-m-d',
                    'before_or_equal:today',
                ],
            ]
        )->validate();

        return DB::transaction(function () use (
            $user,
            $occurrence,
            $amount,
            $paidDate,
            $confirmClosed
        ) {
            $payment = $this->lockedOccurrence(
                $user,
                $occurrence
            );

            if (! $payment->isPending()) {
                throw ValidationException::withMessages([
                    'status' =>
                        'Only pending payments can be marked as paid.',
                ]);
            }

            if ($payment->expense_id !== null) {
                throw ValidationException::withMessages([
                    'status' =>
                        'This payment already has a linked expense.',
                ]);
            }

            $template = $payment->recurringExpense;

            if (
                ! $template
                || $template->user_id !== $user->id
            ) {
                abort(403);
            }

            $category = $template->category;

            if (
                ! $category
                || $category->user_id !== $user->id
                || $category->type !== 'expense'
            ) {
                throw ValidationException::withMessages([
                    'category_id' =>
                        'A valid expense category is required.',
                ]);
            }

            $date = CarbonImmutable::parse($paidDate);

            // Resolve the period from the actual payment date,
            // not the scheduled due date.
            $period = $this->periods->forDate(
                $user,
                $date
            );

            if (! $period) {
                throw ValidationException::withMessages([
                    'paid_date' =>
                        'No financial period covers this payment date.',
                ]);
            }

            $this->guardClosedPeriod(
                $user,
                $period,
                $confirmClosed
            );

            // Create the actual transaction.
            // This is the point at which financial totals change.
            $expense = $user->expenses()->create([
                'financial_period_id' => $period->id,
                'category_id' => $category->id,
                'amount' => $amount,
                'expense_date' => $date->toDateString(),
                'merchant' => $template->merchant,
                'description' => $template->description
                    ?: $template->name,
            ]);

            // Link the scheduled payment to the expense.
            $payment->update([
                'status' => 'paid',
                'expense_id' => $expense->id,
                'paid_date' => $date->toDateString(),
            ]);

            return $payment->refresh();
        });
    }

    /**
     * Skip a pending scheduled payment.
     *
     * No expense transaction is created.
     */
    public function skip(
        User $user,
        RecurringExpenseOccurrence $occurrence
    ): RecurringExpenseOccurrence {
        return DB::transaction(function () use (
            $user,
            $occurrence
        ) {
            $payment = $this->lockedOccurrence(
                $user,
                $occurrence
            );

            if (! $payment->isPending()) {
                throw ValidationException::withMessages([
                    'status' =>
                        'Only pending payments can be skipped.',
                ]);
            }

            if ($payment->expense_id !== null) {
                throw ValidationException::withMessages([
                    'status' =>
                        'A payment linked to an expense cannot be skipped.',
                ]);
            }

            $payment->update([
                'status' => 'skipped',
            ]);

            return $payment->refresh();
        });
    }

    /**
     * Restore a skipped occurrence to pending.
     *
     * Paid occurrences cannot be reopened through this method.
     */
    public function reopen(
        User $user,
        RecurringExpenseOccurrence $occurrence
    ): RecurringExpenseOccurrence {
        return DB::transaction(function () use (
            $user,
            $occurrence
        ) {
            $payment = $this->lockedOccurrence(
                $user,
                $occurrence
            );

            if (! $payment->isSkipped()) {
                throw ValidationException::withMessages([
                    'status' =>
                        'Only skipped payments can be reopened.',
                ]);
            }

            if ($payment->expense_id !== null) {
                throw ValidationException::withMessages([
                    'status' =>
                        'This payment is already linked to an expense.',
                ]);
            }

            $payment->update([
                'status' => 'pending',
                'paid_date' => null,
            ]);

            return $payment->refresh();
        });
    }

    /**
     * Retrieve and lock an occurrence owned by the user.
     */
    private function lockedOccurrence(
        User $user,
        RecurringExpenseOccurrence $occurrence
    ): RecurringExpenseOccurrence {
        if ($occurrence->user_id !== $user->id) {
            abort(403);
        }

        return $user->recurringExpenseOccurrences()
            ->with([
                'recurringExpense.category',
            ])
            ->lockForUpdate()
            ->findOrFail($occurrence->id);
    }

    /**
     * Require explicit confirmation for closed periods.
     */
    private function guardClosedPeriod(
        User $user,
        FinancialPeriod $period,
        bool $confirmed
    ): void {
        if ($period->isOpen()) {
            return;
        }

        if (! $confirmed) {
            throw ValidationException::withMessages([
                'confirm_closed' =>
                    'This payment belongs to a closed financial period. Confirm the change to continue.',
            ]);
        }

        Log::info('Recurring expense payment confirmed for closed period', [
            'user_id' => $user->id,
            'financial_period_id' => $period->id,
        ]);
    }
}
