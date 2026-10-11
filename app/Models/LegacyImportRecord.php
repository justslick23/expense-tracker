<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyImportRecord extends Model
{
    protected $fillable = [
        'source',
        'record_type',
        'legacy_id',
        'legacy_user_id',
        'new_user_id',
        'new_record_id',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'legacy_id' => 'integer',
            'legacy_user_id' => 'integer',
            'new_user_id' => 'integer',
            'new_record_id' => 'integer',
            'imported_at' => 'datetime',
        ];
    }

    public function scopeFromLegacy($query, string $type, int $legacyId)
    {
        return $query
            ->where('source', 'old_finance_tracker')
            ->where('record_type', $type)
            ->where('legacy_id', $legacyId);
    }
}
