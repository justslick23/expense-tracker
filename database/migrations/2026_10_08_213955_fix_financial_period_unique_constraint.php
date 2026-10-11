<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_periods', function (Blueprint $table) {
            $table->dropUnique(
                'financial_periods_user_id_is_open_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('financial_periods', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'is_open'],
                'financial_periods_user_id_is_open_unique'
            );
        });
    }
};
