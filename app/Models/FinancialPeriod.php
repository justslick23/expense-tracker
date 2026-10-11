<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class FinancialPeriod extends Model
{
    protected $fillable = ['user_id', 'name', 'start_date', 'end_date', 'status', 'opening_balance', 'closed_at', 'closing_summary', 'source'];
    protected $attributes = ['status' => 'open', 'source' => 'manual'];
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'closed_at' => 'datetime',
            'closing_summary' => 'array',
            'opening_balance' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $p) => $p->is_open = $p->status === 'open' ? true : null);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOwnedBy(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', 'open');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function expenses(): HasMany
{
    return $this->hasMany(Expense::class);
}

public function budgets(): HasMany
{
    return $this->hasMany(Budget::class);
}
}