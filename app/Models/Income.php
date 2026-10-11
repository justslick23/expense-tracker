<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Income extends Model
{
    use SoftDeletes;

    protected $fillable = ['financial_period_id', 'category_id', 'amount', 'income_date', 'description'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'income_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}