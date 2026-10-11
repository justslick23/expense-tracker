<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Category extends Model
{
    public const TYPES = ['expense', 'income'];

    public const ICONS = [
        'fa-cart-shopping', 'fa-house', 'fa-bolt', 'fa-car', 'fa-utensils', 'fa-heart-pulse',
        'fa-graduation-cap', 'fa-tv', 'fa-shirt', 'fa-phone', 'fa-gift', 'fa-briefcase',
        'fa-money-bill-wave', 'fa-piggy-bank', 'fa-plane', 'fa-paw', 'fa-gas-pump', 'fa-wifi',
    ];

    protected $fillable = ['name', 'type', 'color', 'icon', 'archived_at'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
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

public function legacyBudgetArchives(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(LegacyBudgetArchive::class);
}
}