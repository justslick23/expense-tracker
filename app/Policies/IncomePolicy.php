<?php

namespace App\Policies;

use App\Models\Income;
use App\Models\User;

class IncomePolicy
{
    public function update(User $user, Income $income): bool
    {
        return $user->id === $income->user_id;
    }

    public function delete(User $user, Income $income): bool
    {
        return $this->update($user, $income);
    }
}