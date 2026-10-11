<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class SavingsDashboardService
{
    /**
     * Return a savings overview for the dashboard.
     *
     * All monetary values use integer minor units.
     */
    public function summary(User $user): array
    {
        $goals = $user->savingsGoals()
            ->where('status', 'active')
            ->withSum([
                'transactions as contributions_total' => fn ($query) =>
                    $query->where('type', 'contribution'),
            ], 'amount')
            ->withSum([
                'transactions as withdrawals_total' => fn ($query) =>
                    $query->where('type', 'withdrawal'),
            ], 'amount')
            ->orderByRaw('CASE WHEN target_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('target_date')
            ->orderBy('id')
            ->get();

        $totalTarget = 0;
        $totalSaved = 0;

        $goals = $goals->map(function ($goal) use (
            &$totalTarget,
            &$totalSaved
        ) {
            $saved = (int) $goal->starting_amount
                + (int) ($goal->contributions_total ?? 0)
                - (int) ($goal->withdrawals_total ?? 0);

            $remaining = max(
                0,
                $goal->target_amount - $saved
            );

            $progress = $goal->target_amount > 0
                ? round(
                    min(100, max(0, $saved / $goal->target_amount * 100)),
                    1
                )
                : 0.0;

            $totalTarget += $goal->target_amount;
            $totalSaved += $saved;

            return [
                'id' => $goal->id,
                'name' => $goal->name,
                'saved' => $saved,
                'target' => $goal->target_amount,
                'remaining' => $remaining,
                'progress' => $progress,
                'target_date' => $goal->target_date,
                'target_reached' => $saved >= $goal->target_amount,
            ];
        });

        $overallProgress = $totalTarget > 0
            ? round(
                min(100, max(0, $totalSaved / $totalTarget * 100)),
                1
            )
            : 0.0;

        return [
            'active_count' => $goals->count(),
            'total_target' => $totalTarget,
            'total_saved' => $totalSaved,
            'remaining' => max(0, $totalTarget - $totalSaved),
            'overall_progress' => $overallProgress,
            'goals' => $goals->take(4),
        ];
    }
}
