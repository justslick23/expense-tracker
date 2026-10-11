<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incomes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('financial_period_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('category_id')->constrained()->restrictOnDelete();
            $t->bigInteger('amount');
            $t->date('income_date');
            $t->string('description')->nullable();
            $t->softDeletes();
            $t->timestamps();

            $t->index(['user_id', 'income_date']);
            $t->index(['user_id', 'financial_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};