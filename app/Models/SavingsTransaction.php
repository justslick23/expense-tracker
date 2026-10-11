<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingsTransaction extends Model
{
    protected $fillable = [
        'type',
        'amount',
        'transaction_date',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'transaction_date' => 'date',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function savingsGoal(): BelongsTo
    {
        return $this->belongsTo(SavingsGoal::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Transaction Helpers
    |--------------------------------------------------------------------------
    */

    public function isContribution(): bool
    {
        return $this->type === 'contribution';
    }

    public function isWithdrawal(): bool
    {
        return $this->type === 'withdrawal';
    }
}
