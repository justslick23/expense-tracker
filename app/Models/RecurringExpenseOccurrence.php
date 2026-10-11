<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringExpenseOccurrence extends Model
{
    protected $fillable = [
        'recurring_expense_id',
        'user_id',
        'due_date',
        'planned_amount',
        'status',
        'expense_id',
        'paid_date',
    ];
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'planned_amount' => 'integer',
            'paid_date' => 'date',
        ];
    }

    public function recurringExpense(): BelongsTo
{
    return $this->belongsTo(RecurringExpense::class)
        ->withTrashed();
}

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isSkipped(): bool
    {
        return $this->status === 'skipped';
    }

    /**
     * Overdue is calculated from the due date.
     * It is not stored as a separate database status.
     */
    public function isOverdue(): bool
    {
        return $this->isPending()
            && $this->due_date->lt(today());
    }
}
