<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_receipt', function (Blueprint $table) {
            $table->id();

            $table->foreignId('receipt_id')
                ->constrained('receipts')
                ->cascadeOnDelete();

            $table->foreignId('expense_id')
                ->constrained('expenses')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['receipt_id', 'expense_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_receipt');
    }
};
