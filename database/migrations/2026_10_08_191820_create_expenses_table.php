
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('financial_period_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('category_id')
                ->constrained()
                ->restrictOnDelete();

            // Store amounts in cents/maloti minor units
            $table->bigInteger('amount');

            $table->date('expense_date');

            $table->string('description')->nullable();

            $table->string('merchant')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'expense_date']);
            $table->index(['user_id', 'financial_period_id']);
            $table->index(['user_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
