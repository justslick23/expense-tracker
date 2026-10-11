<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavingsGoal extends Model
{
    protected $fillable = [
        'name',
        'target_amount',
        'starting_amount',
        'target_date',
        'description',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'integer',
            'starting_amount' => 'integer',
            'target_date' => 'date',
            'completed_at' => 'datetime',
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

    public function transactions(): HasMany
    {
        return $this->hasMany(SavingsTransaction::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    /*
    |--------------------------------------------------------------------------
    | Status Helpers
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    /*
    |--------------------------------------------------------------------------
    | Financial Calculations
    |--------------------------------------------------------------------------
    */

    /**
     * Total amount contributed towards the goal.
     */
    public function getContributedAmountAttribute(): int
    {
        return (int) $this->transactions()
            ->where('type', 'contribution')
            ->sum('amount');
    }

    /**
     * Total amount withdrawn from the goal.
     */
    public function getWithdrawnAmountAttribute(): int
    {
        return (int) $this->transactions()
            ->where('type', 'withdrawal')
            ->sum('amount');
    }

    /**
     * Current savings balance.
     *
     * starting_amount + contributions - withdrawals
     */
    public function getSavedAmountAttribute(): int
    {
        return $this->starting_amount
            + $this->contributed_amount
            - $this->withdrawn_amount;
    }

    /**
     * Amount still required to reach the target.
     */
    public function getRemainingAmountAttribute(): int
    {
        return max(
            0,
            $this->target_amount - $this->saved_amount
        );
    }

    /**
     * Progress percentage capped at 100%.
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->target_amount <= 0) {
            return 0.0;
        }

        return round(
            min(
                100,
                max(
                    0,
                    ($this->saved_amount / $this->target_amount) * 100
                )
            ),
            1
        );
    }

    /**
     * Whether the savings target has been reached.
     */
    public function getTargetReachedAttribute(): bool
    {
        return $this->saved_amount >= $this->target_amount;
    }

    /**
     * Number of days remaining until the target date.
     *
     * Null means no deadline was configured.
     * Negative values indicate an overdue deadline.
     */
    public function getDaysRemainingAttribute(): ?int
    {
        if (! $this->target_date) {
            return null;
        }

        return (int) today()->diffInDays(
            $this->target_date,
            false
        );
    }
}
