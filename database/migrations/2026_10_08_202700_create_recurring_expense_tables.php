<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('name', 150);

            // Planned amount in integer minor units.
            $table->bigInteger('amount');

            $table->enum('frequency', [
                'daily',
                'weekly',
                'monthly',
                'quarterly',
                'yearly',
            ]);

            // Day the recurring schedule begins.
            $table->date('start_date');

            // Next date requiring occurrence generation.
            $table->date('next_due_date');

            // Optional final scheduled date.
            $table->date('end_date')->nullable();

            $table->string('merchant')->nullable();
            $table->text('description')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'user_id',
                'is_active',
                'next_due_date',
            ]);
        });

        Schema::create(
            'recurring_expense_occurrences',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('recurring_expense_id')
                    ->constrained('recurring_expenses')
                    ->cascadeOnDelete();

                $table->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->date('due_date');

                // Snapshot of the amount scheduled.
                $table->bigInteger('planned_amount');

                $table->enum('status', [
                    'pending',
                    'paid',
                    'skipped',
                ])->default('pending');

                // Links to the actual expense when paid.
                $table->foreignId('expense_id')
                    ->nullable()
                    ->constrained('expenses')
                    ->nullOnDelete();

                $table->date('paid_date')->nullable();

                $table->timestamps();

                // A scheduled payment should be generated once.
                $table->unique(
                    ['recurring_expense_id', 'due_date'],
                    'recurring_occurrence_unique'
                );

                // One actual expense per occurrence.
                $table->unique('expense_id');

                $table->index([
                    'user_id',
                    'status',
                    'due_date',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'recurring_expense_occurrences'
        );

        Schema::dropIfExists('recurring_expenses');
    }
};
