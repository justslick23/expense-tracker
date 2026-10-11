<?php

namespace App\Services;

use App\Models\SavingsGoal;
use App\Models\SavingsTransaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
class SavingsGoalService
{
    /**
     * Create a savings goal.
     *
     * Monetary values must already be converted
     * to integer minor units.
     */
    public function create(
        User $user,
        array $data
    ): SavingsGoal {
        $target = (int) $data['target_amount'];
        $starting = (int) ($data['starting_amount'] ?? 0);

        if ($target <= 0) {
            throw ValidationException::withMessages([
                'target_amount' => 'The target amount must be greater than zero.',
            ]);
        }

        if ($starting < 0) {
            throw ValidationException::withMessages([
                'starting_amount' => 'Starting savings cannot be negative.',
            ]);
        }

        return $user->savingsGoals()->create([
            'name' => $data['name'],
            'target_amount' => $target,
            'starting_amount' => $starting,
            'target_date' => $data['target_date'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => 'active',
        ]);
    }

    /**
     * Update an existing goal.
     *
     * Financial history and existing transactions
     * are preserved.
     */
    public function update(
        User $user,
        SavingsGoal $goal,
        array $data
    ): SavingsGoal {
        $this->authorizeGoal($user, $goal);

        return DB::transaction(function () use ($user, $goal, $data) {
            $goal = $this->lockedGoal($user, $goal);

            if ($goal->isArchived()) {
                throw ValidationException::withMessages([
                    'status' => 'Archived savings goals cannot be edited.',
                ]);
            }

            if (
                array_key_exists('target_amount', $data)
                && (int) $data['target_amount'] <= 0
            ) {
                throw ValidationException::withMessages([
                    'target_amount' => 'The target amount must be greater than zero.',
                ]);
            }

            $allowed = [
                'name',
                'target_amount',
                'target_date',
                'description',
            ];

            $changes = array_intersect_key(
                $data,
                array_flip($allowed)
            );

            $goal->update($changes);

            return $goal->refresh();
        });
    }

    /**
     * Add money to a savings goal.
     */
    public function contribute(
        User $user,
        SavingsGoal $goal,
        int $amount,
        string $date,
        ?string $note = null
    ): SavingsTransaction {
        return $this->recordTransaction(
            $user,
            $goal,
            'contribution',
            $amount,
            $date,
            $note
        );
    }

    /**
     * Withdraw money from a savings goal.
     */
    public function withdraw(
        User $user,
        SavingsGoal $goal,
        int $amount,
        string $date,
        ?string $note = null
    ): SavingsTransaction {
        return $this->recordTransaction(
            $user,
            $goal,
            'withdrawal',
            $amount,
            $date,
            $note
        );
    }

    /**
     * Complete a savings goal.
     *
     * Completing a goal prevents further transactions.
     */
    public function complete(
        User $user,
        SavingsGoal $goal
    ): SavingsGoal {
        $this->authorizeGoal($user, $goal);

        return DB::transaction(function () use ($user, $goal) {
            $goal = $this->lockedGoal($user, $goal);

            if (! $goal->isActive()) {
                throw ValidationException::withMessages([
                    'status' => 'Only active savings goals can be completed.',
                ]);
            }

            if (! $goal->target_reached) {
                throw ValidationException::withMessages([
                    'status' => 'The savings target has not been reached yet.',
                ]);
            }

            $goal->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return $goal->refresh();
        });
    }

    /**
     * Reopen a completed savings goal.
     */
    public function reopen(
        User $user,
        SavingsGoal $goal
    ): SavingsGoal {
        $this->authorizeGoal($user, $goal);

        return DB::transaction(function () use ($user, $goal) {
            $goal = $this->lockedGoal($user, $goal);

            if (! $goal->isCompleted()) {
                throw ValidationException::withMessages([
                    'status' => 'Only completed savings goals can be reopened.',
                ]);
            }

            $goal->update([
                'status' => 'active',
                'completed_at' => null,
            ]);

            return $goal->refresh();
        });
    }

    /**
     * Archive a savings goal without deleting
     * its transaction history.
     */
    public function archive(
        User $user,
        SavingsGoal $goal
    ): SavingsGoal {
        $this->authorizeGoal($user, $goal);

        return DB::transaction(function () use ($user, $goal) {
            $goal = $this->lockedGoal($user, $goal);

            if ($goal->isArchived()) {
                throw ValidationException::withMessages([
                    'status' => 'This savings goal is already archived.',
                ]);
            }

            $goal->update([
                'status' => 'archived',
            ]);

            return $goal->refresh();
        });
    }

    /**
     * Record a contribution or withdrawal.
     */
    private function recordTransaction(
        User $user,
        SavingsGoal $goal,
        string $type,
        int $amount,
        string $date,
        ?string $note
    ): SavingsTransaction {
        $this->authorizeGoal($user, $goal);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'The amount must be greater than zero.',
            ]);
        }

        $parsedDate = CarbonImmutable::createFromFormat(
            '!Y-m-d',
            $date
        );

        if (
            ! $parsedDate
            || $parsedDate->toDateString() !== $date
            || $parsedDate->isFuture()
        ) {
            throw ValidationException::withMessages([
                'transaction_date' => 'Enter a valid date that is not in the future.',
            ]);
        }

        return DB::transaction(function () use (
            $user,
            $goal,
            $type,
            $amount,
            $parsedDate,
            $note
        ) {
            $goal = $this->lockedGoal($user, $goal);

            if (! $goal->isActive()) {
                throw ValidationException::withMessages([
                    'status' => 'Only active savings goals can accept transactions.',
                ]);
            }

            if (
                $type === 'withdrawal'
                && $amount > $goal->saved_amount
            ) {
                throw ValidationException::withMessages([
                    'amount' => 'The withdrawal exceeds the available savings balance.',
                ]);
            }

            $transaction = new SavingsTransaction([
                'type' => $type,
                'amount' => $amount,
                'transaction_date' => $parsedDate->toDateString(),
                'note' => $note,
            ]);
            
            $transaction->user()->associate($user);
            $transaction->savingsGoal()->associate($goal);
            $transaction->save();
            
            return $transaction;
        });
    }

    /**
     * Lock a goal during financial operations.
     */
    private function lockedGoal(
        User $user,
        SavingsGoal $goal
    ): SavingsGoal {
        return $user->savingsGoals()
            ->lockForUpdate()
            ->findOrFail($goal->id);
    }

    /**
     * Prevent access to another user's goal.
     */
    private function authorizeGoal(
        User $user,
        SavingsGoal $goal
    ): void {
        abort_unless(
            $goal->user_id === $user->id,
            403
        );
    }
}
