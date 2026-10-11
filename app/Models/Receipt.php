<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    protected $fillable = [
        'expense_id',
        'original_filename',
        'file_path',
        'mime_type',
        'file_size',
        'status',
        'merchant',
        'transaction_date',
        'total_amount',
        'currency',
        'items',
        'extracted_data',
        'ai_model',
        'processed_at',
        'processing_error',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'total_amount' => 'integer',
            'file_size' => 'integer',
            'items' => 'array',
            'extracted_data' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function expenses(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
{
    return $this->belongsToMany(Expense::class)
        ->withTimestamps();
}
}
