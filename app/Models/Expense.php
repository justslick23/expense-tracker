<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
class Expense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'financial_period_id',
        'category_id',
        'amount',
        'expense_date',
        'description',
        'merchant',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'expense_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(
            FinancialPeriod::class,
            'financial_period_id'
        );
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function recurringOccurrence(): HasOne
{
    return $this->hasOne(
        RecurringExpenseOccurrence::class,
        'expense_id'
    );
}


/**
 * Keep recurring payment history consistent with
 * its linked actual expense.
 */

 protected static function booted(): void
 {
     // Synchronize the recorded payment date when
     // an existing recurring-linked expense is edited.
     static::updated(function (Expense $expense) {
         if (! $expense->wasChanged('expense_date')) {
             return;
         }
 
         $occurrence = $expense->recurringOccurrence()
             ->where('status', 'paid')
             ->first();
 
         if ($occurrence) {
             $occurrence->update([
                 'paid_date' => $expense->expense_date,
             ]);
         }
     });
 
     // Prevent removal of an expense that belongs to
     // an existing recurring payment occurrence.
     static::deleting(function (Expense $expense) {
         if (! $expense->recurringOccurrence()->exists()) {
             return;
         }
 
         throw \Illuminate\Validation\ValidationException::withMessages([
             'expense' =>
                 'This expense belongs to a recurring payment. Its payment history must be preserved.',
         ]);
     });
 }
 public function receipts(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Receipt::class);
}

public function receiptAttachments(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
{
    return $this->belongsToMany(Receipt::class)
        ->withTimestamps();
}

}
