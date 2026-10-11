<?php

namespace App\Services;

use App\Models\FinancialPeriod;
use App\Models\Income;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class IncomeService
{
    public function __construct(private PeriodService $periods) {}

    public function save(User $user, array $d, bool $confirmed, ?Income $income = null): Income
    {
        $period = $this->periods->forDate($user, Carbon::parse($d['income_date']));
        $this->guardClosed($user, $confirmed, $period, $income?->period);

        $income ??= $user->incomes()->make();
        $income->fill([
            'financial_period_id' => $period?->id,
            'category_id' => $d['category_id'],
            'amount' => $d['amount'],
            'income_date' => $d['income_date'],
            'description' => $d['description'] ?? null,
        ])->save();

        return $income;
    }

    public function delete(User $user, Income $income, bool $confirmed): void
    {
        $this->guardClosed($user, $confirmed, $income->period);
        $income->delete();
    }

    private function guardClosed(User $user, bool $confirmed, ?FinancialPeriod ...$periods): void
    {
        $closed = collect($periods)->first(fn ($p) => $p && ! $p->isOpen());

        if (! $closed) {
            return;
        }

        if (! $confirmed) {
            throw ValidationException::withMessages([
                'confirm_closed' => "This change affects the closed period \"{$closed->name}\". Tick the box to confirm.",
            ]);
        }

        Log::info('Closed-period income change confirmed', ['user_id' => $user->id, 'period_id' => $closed->id]);
    }
}