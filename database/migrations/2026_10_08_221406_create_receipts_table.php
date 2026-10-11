<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('expense_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Original uploaded receipt.
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');

            // Receipt processing status.
            $table->string('status', 30)->default('uploaded');

            // AI-extracted transaction information.
            $table->string('merchant')->nullable();
            $table->date('transaction_date')->nullable();

            // Amount stored in minor units (cents).
            $table->unsignedBigInteger('total_amount')->nullable();

            $table->string('currency', 3)->default('LSL');

            // Optional extracted line items.
            $table->json('items')->nullable();

            // Original structured AI response.
            $table->json('extracted_data')->nullable();

            // AI processing metadata.
            $table->string('ai_model')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('processing_error')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
