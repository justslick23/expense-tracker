<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_goals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name', 150);

            // Monetary amounts stored in minor units.
            $table->unsignedBigInteger('target_amount');
            $table->unsignedBigInteger('starting_amount')
                ->default(0);

            $table->date('target_date')->nullable();

            $table->text('description')->nullable();

            $table->enum('status', [
                'active',
                'completed',
                'archived',
            ])->default('active');

            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'target_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_goals');
    }
};
