<?php

namespace App\Services;

use App\Models\RecurringExpense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecurringExpenseScheduleService
{
    /**
     * Generate pending occurrences up to the supplied date.
     *
     * Returns the number of newly created occurrences.
     * Existing occurrences are never overwritten.
     */
    public function generate(
        User $user,
        RecurringExpense $recurring,
        CarbonInterface $throughDate,
        int $limit = 500
    ): int {
        if ($recurring->user_id !== $user->id) {
            abort(403);
        }

        if ($limit < 1 || $limit > 500) {
            throw new \InvalidArgumentException(
                'The generation limit must be between 1 and 500.'
            );
        }

        return DB::transaction(function () use (
            $user,
            $recurring,
            $throughDate,
            $limit
        ) {
            // Lock the template to prevent concurrent generators
            // from advancing the same schedule.
            $template = $user->recurringExpenses()
                ->lockForUpdate()
                ->findOrFail($recurring->id);

            if (! $template->is_active) {
                return 0;
            }

            $anchor = CarbonImmutable::instance(
                $template->start_date
            )->startOfDay();

            $cursor = CarbonImmutable::instance(
                $template->next_due_date
            )->startOfDay();

            $horizon = CarbonImmutable::instance(
                $throughDate
            )->startOfDay();

            $end = $template->end_date
                ? CarbonImmutable::instance(
                    $template->end_date
                )->startOfDay()
                : null;

            if ($cursor->lessThan($anchor)) {
                throw ValidationException::withMessages([
                    'next_due_date' =>
                        'The next due date cannot precede the start date.',
                ]);
            }

            if ($template->amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' =>
                        'The recurring amount must be greater than zero.',
                ]);
            }

            if (
                $cursor->greaterThan($horizon)
                || ($end && $cursor->greaterThan($end))
            ) {
                return 0;
            }

            $index = $this->indexForDate(
                $anchor,
                $cursor,
                $template->frequency
            );

            // Reject an invalid schedule cursor rather than
            // silently skipping or duplicating payments.
            if (
                ! $this->dateAt(
                    $anchor,
                    $template->frequency,
                    $index
                )->equalTo($cursor)
            ) {
                throw ValidationException::withMessages([
                    'next_due_date' =>
                        'The next due date does not match the recurring schedule.',
                ]);
            }

            $created = 0;
            $processed = 0;

            while (
                $cursor->lessThanOrEqualTo($horizon)
                && (! $end || $cursor->lessThanOrEqualTo($end))
                && $processed < $limit
            ) {
               
// Check by calendar date to accommodate SQLite's
// datetime representation of DATE-cast attributes.
$occurrence = $template->occurrences()
->whereDate('due_date', $cursor->toDateString())
->first();

if (! $occurrence) {
$template->occurrences()->create([
    'user_id' => $user->id,
    'due_date' => $cursor->toDateString(),
    'planned_amount' => $template->amount,
    'status' => 'pending',
]);

$created++;
}

                $processed++;
                $index++;

                // Always calculate from the original anchor.
                $next = $this->dateAt(
                    $anchor,
                    $template->frequency,
                    $index
                );

                if (! $next->greaterThan($cursor)) {
                    throw new \LogicException(
                        'The recurring schedule failed to advance.'
                    );
                }

                $cursor = $next;
            }

            // Store the first date not yet processed.
            // This may fall after the template's end date.
            $template->next_due_date = $cursor->toDateString();
            $template->save();

            return $created;
        });
    }

    /**
     * Calculate a scheduled date from the original start date.
     *
     * Monthly and yearly schedules use no-overflow arithmetic.
     */
    public function dateAt(
        CarbonInterface $startDate,
        string $frequency,
        int $index
    ): CarbonImmutable {
        if ($index < 0) {
            throw new \InvalidArgumentException(
                'The occurrence index cannot be negative.'
            );
        }

        $anchor = CarbonImmutable::instance($startDate)
            ->startOfDay();

        return match ($frequency) {
            'daily' => $anchor->addDays($index),
            'weekly' => $anchor->addWeeks($index),
            'monthly' => $anchor->addMonthsNoOverflow($index),
            'quarterly' => $anchor->addMonthsNoOverflow(
                $index * 3
            ),
            'yearly' => $anchor->addYearsNoOverflow($index),

            default => throw new \InvalidArgumentException(
                'Unsupported recurring frequency.'
            ),
        };
    }

    /**
     * Find the occurrence index matching an existing cursor.
     *
     * This avoids iterating from the first payment when a
     * template already has years of payment history.
     */
    private function indexForDate(
        CarbonImmutable $anchor,
        CarbonImmutable $date,
        string $frequency
    ): int {
        $days = (int) $anchor->diffInDays($date);

        $months = (
            ($date->year - $anchor->year) * 12
            + ($date->month - $anchor->month)
        );

        return match ($frequency) {
            'daily' => $days,
            'weekly' => intdiv($days, 7),
            'monthly' => max(0, $months),
            'quarterly' => max(0, intdiv($months, 3)),
            'yearly' => max(0, intdiv($months, 12)),

            default => throw new \InvalidArgumentException(
                'Unsupported recurring frequency.'
            ),
        };
    }
}
