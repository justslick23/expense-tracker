
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('financial_period_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('category_id')
                ->constrained()
                ->restrictOnDelete();

            // Money stored in minor units (M1,500.00 = 150000)
            $table->bigInteger('amount');

            $table->timestamps();

            // Only one allocation per category per period
            $table->unique(
                ['financial_period_id', 'category_id'],
                'budgets_period_category_unique'
            );

            $table->index(['user_id', 'financial_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
