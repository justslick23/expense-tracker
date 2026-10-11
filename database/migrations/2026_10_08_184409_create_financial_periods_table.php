<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_periods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->string('status', 10)->default('open');
            $t->boolean('is_open')->nullable();
            $t->bigInteger('opening_balance')->default(0);
            $t->timestamp('closed_at')->nullable();
            $t->json('closing_summary')->nullable();
            $t->string('source', 20)->default('manual');
            $t->timestamps();

            $t->unique(['user_id', 'is_open']);
            $t->index(['user_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_periods');
    }
};