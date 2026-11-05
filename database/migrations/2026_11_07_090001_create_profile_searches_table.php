<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('query', 100);
            // Lowercased query: one entry per search, whatever the case.
            $table->string('query_key', 100);
            // updated_at = last time it was searched.
            $table->timestamps();

            $table->unique(['profile_id', 'query_key']);
            $table->index(['profile_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_searches');
    }
};
