<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_import_records', function (Blueprint $table) {
            $table->id();

            $table->string('source', 50)
                ->default('old_finance_tracker');

            $table->string('record_type', 50);

            $table->unsignedBigInteger('legacy_id');

            $table->unsignedBigInteger('legacy_user_id');

            $table->unsignedBigInteger('new_user_id');

            $table->unsignedBigInteger('new_record_id');

            $table->timestamp('imported_at')
                ->useCurrent();

            $table->timestamps();

            // Prevent importing the same legacy
            // record more than once.
            $table->unique(
                ['source', 'record_type', 'legacy_id'],
                'legacy_import_unique'
            );

            $table->index([
                'new_user_id',
                'record_type',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_import_records');
    }
};
