<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name', 60);
            $t->string('type', 10);
            $t->string('color', 7)->nullable();
            $t->string('icon', 40)->nullable();
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();

            $t->unique(['user_id', 'type', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};