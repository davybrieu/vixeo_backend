<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            // Null = original version / no subtitles.
            $table->string('audio_language', 10)->nullable();
            $table->string('subtitle_language', 10)->nullable();
            // Skipped automatically by the player; otherwise it shows a "Passer" button.
            $table->boolean('skip_intro')->default(false);
            $table->boolean('skip_recap')->default(false);
            $table->boolean('skip_credits')->default(false);
            $table->boolean('skip_preview')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['audio_language', 'subtitle_language', 'skip_intro', 'skip_recap', 'skip_credits', 'skip_preview']);
        });
    }
};
