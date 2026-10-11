<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('savings_goal_id')
                ->constrained()
                ->restrictOnDelete();

            $table->enum('type', [
                'contribution',
                'withdrawal',
            ]);

            // Monetary amount in minor units.
            $table->unsignedBigInteger('amount');

            $table->date('transaction_date');

            $table->string('note', 500)->nullable();

            $table->timestamps();

            $table->index([
                'savings_goal_id',
                'transaction_date',
            ]);

            $table->index([
                'user_id',
                'type',
                'transaction_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_transactions');
    }
};
