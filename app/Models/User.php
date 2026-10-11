<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function financialPeriods(): HasMany
    {
        return $this->hasMany(FinancialPeriod::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function expenses(): HasMany
{
    return $this->hasMany(Expense::class);
}

public function budgets(): HasMany
{
    return $this->hasMany(Budget::class);
}

public function recurringExpenses(): HasMany
{
    return $this->hasMany(RecurringExpense::class);
}

public function recurringExpenseOccurrences(): HasMany
{
    return $this->hasMany(RecurringExpenseOccurrence::class);
}


/*
|--------------------------------------------------------------------------
| Savings Goals
|--------------------------------------------------------------------------
*/

public function savingsGoals(): HasMany
{
    return $this->hasMany(SavingsGoal::class);
}

public function savingsTransactions(): HasMany
{
    return $this->hasMany(SavingsTransaction::class);
}
public function legacyBudgetArchives(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(LegacyBudgetArchive::class);
}

public function receipts(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Receipt::class);
}
}
