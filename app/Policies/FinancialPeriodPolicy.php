<?php

namespace App\Policies;

use App\Models\FinancialPeriod;
use App\Models\User;

class FinancialPeriodPolicy
{
    public function view(User $user, FinancialPeriod $period): bool
    {
        return $user->id === $period->user_id;
    }

    public function close(User $user, FinancialPeriod $period): bool
    {
        return $this->view($user, $period);
    }
}