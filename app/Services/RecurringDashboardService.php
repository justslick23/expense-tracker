<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class RecurringDashboardService
{
    /**
     * Summarize scheduled recurring payments.
     *
     * All amounts are integer minor units.
     * Only pending occurrences count as outstanding.
     */
    public function summary(User $user): array
    {
        $today = CarbonImmutable::today();
        $todayDate = $today->toDateString();
        $horizonDate = $today->addDays(30)->toDateString();

        $pending = $user->recurringExpenseOccurrences()
            ->where('status', 'pending');

        $overdue = (clone $pending)
            ->whereDate('due_date', '<', $todayDate);

        $next30Days = (clone $pending)
            ->whereDate('due_date', '>=', $todayDate)
            ->whereDate('due_date', '<=', $horizonDate);

        $nextPayments = (clone $pending)
            ->whereDate('due_date', '>=', $todayDate)
            ->with('recurringExpense.category')
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(5)
            ->get();

        return [
            'overdue_count' => (clone $overdue)->count(),

            'overdue_amount' => (int) (clone $overdue)
                ->sum('planned_amount'),

            'upcoming_count' => (clone $next30Days)->count(),

            'upcoming_amount' => (int) (clone $next30Days)
                ->sum('planned_amount'),

            'total_outstanding' => (int) (clone $pending)
                ->sum('planned_amount'),

            'next_payments' => $nextPayments,
        ];
    }
}
