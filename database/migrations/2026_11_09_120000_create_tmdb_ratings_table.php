<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The profile's copy of its TMDb ratings, with its own comments (docs/compte-tmdb.md):
        // TMDb keeps the rating, Vixeo the comment (TMDb's API takes no written review).
        Schema::create('tmdb_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            // TMDb account the rating belongs to (the profile may link another one later).
            $table->unsignedBigInteger('account_id');
            // "movie/550", "tv/1399", "tv/1399/season/1/episode/2".
            $table->string('target', 80);
            $table->string('type', 10);
            $table->unsignedBigInteger('tmdb_id');
            $table->unsignedSmallInteger('season_number')->nullable();
            $table->unsignedSmallInteger('episode_number')->nullable();
            $table->decimal('value', 3, 1);
            $table->text('comment')->nullable();
            // What "Mes avis" shows, copied from TMDb.
            $table->string('title');
            $table->string('episode_name')->nullable();
            $table->string('date', 10)->nullable();
            $table->string('poster_path')->nullable();
            $table->string('backdrop_path')->nullable();
            $table->string('still_path')->nullable();
            $table->decimal('vote_average', 3, 1)->default(0);
            $table->timestamp('rated_at');
            $table->timestamps();

            $table->unique(['profile_id', 'account_id', 'target']);
            $table->index(['profile_id', 'account_id', 'rated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tmdb_ratings');
    }
};
