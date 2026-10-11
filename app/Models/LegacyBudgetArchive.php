<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyBudgetArchive extends Model
{
    protected $fillable = [
        'legacy_budget_id',
        'category_id',
        'year',
        'month',
        'allocated_amount',
    ];

    protected function casts(): array
    {
        return [
            'legacy_budget_id' => 'integer',
            'category_id' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'allocated_amount' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Original calendar month.
     */
    public function getMonthLabelAttribute(): string
    {
        return sprintf(
            '%s %d',
            \Carbon\CarbonImmutable::create(
                $this->year,
                $this->month,
                1
            )->format('F'),
            $this->year
        );
    }
}
