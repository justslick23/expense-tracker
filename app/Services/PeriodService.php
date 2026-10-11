<?php

namespace App\Services;

use App\Models\FinancialPeriod;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PeriodService
{

    public function forDate(User $user, CarbonInterface $date): ?FinancialPeriod
    {
        $d = $date->toDateString();

        return $user->financialPeriods()
            ->where('start_date', '<=', $d)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $d))
            ->first();
    }
    public function current(User $user): ?FinancialPeriod
    {
        return $user->financialPeriods()->open()->first();
    }

    public function start(User $user, string $name, CarbonInterface $start, ?CarbonInterface $end = null, int $opening = 0): FinancialPeriod
    {
        return DB::transaction(function () use ($user, $name, $start, $end, $opening) {
            User::whereKey($user->id)->lockForUpdate()->first();

            $this->guard($end !== null && $end->toDateString() < $start->toDateString(), 'end_date', 'The end date cannot be before the start date.');
            $this->guard($this->current($user) !== null, 'start_date', 'Close the current period before starting a new one.');
            $this->guard($this->overlaps($user, $start, $end), 'start_date', 'These dates overlap an existing period.');

            return $user->financialPeriods()->create([
                'name' => $name,
                'start_date' => $start->toDateString(),
                'end_date' => $end?->toDateString(),
                'opening_balance' => $opening,
            ]);
        });
    }

    public function close(FinancialPeriod $period, CarbonInterface $end): FinancialPeriod
    {
        return DB::transaction(function () use ($period, $end) {
            $period = FinancialPeriod::lockForUpdate()->findOrFail($period->id);

            $this->guard(! $period->isOpen(), 'status', 'This period is already closed.');
            $this->guard($end->toDateString() < $period->start_date->toDateString(), 'end_date', 'The end date cannot be before the start date.');
            $this->guard($this->overlaps($period->user, $period->start_date, $end, $period->id), 'end_date', 'That end date overlaps another period.');

            $period->update(['status' => 'closed', 'end_date' => $end->toDateString(), 'closed_at' => now()]);

            return $period;
        });
    }

    public function overlaps(User $user, CarbonInterface $start, ?CarbonInterface $end, ?int $ignoreId = null): bool
    {
        return $user->financialPeriods()
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->where('start_date', '<=', $end?->toDateString() ?? '9999-12-31')
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $start->toDateString()))
            ->exists();
    }

    private function guard(bool $fail, string $field, string $message): void
    {
        if ($fail) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}