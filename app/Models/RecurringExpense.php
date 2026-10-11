<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringExpense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'amount',
        'frequency',
        'start_date',
        'next_due_date',
        'end_date',
        'merchant',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'start_date' => 'date',
            'next_due_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
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

    public function occurrences(): HasMany
    {
        return $this->hasMany(RecurringExpenseOccurrence::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDue($query, $date)
    {
        return $query->active()
            ->whereDate('next_due_date', '<=', $date)
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhereColumn('next_due_date', '<=', 'end_date');
            });
    }
}
