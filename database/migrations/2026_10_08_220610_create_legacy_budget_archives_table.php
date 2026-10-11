<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_budget_archives', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedBigInteger('legacy_budget_id');

            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');

            // Original budget allocation in integer minor units.
            $table->unsignedBigInteger('allocated_amount');

            $table->timestamps();

            // One archive record for each legacy budget.
            $table->unique(
                ['user_id', 'legacy_budget_id'],
                'legacy_budget_archive_unique'
            );

            $table->index(
                ['user_id', 'year', 'month'],
                'legacy_budget_archive_month_index'
            );

            $table->index(
                ['category_id', 'year', 'month'],
                'legacy_budget_archive_category_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_budget_archives');
    }
};
