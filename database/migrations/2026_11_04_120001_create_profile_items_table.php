<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * State of one content for one profile: favorite, playback progress, watched.
     * Each group carries its own *_changed_at timestamp for last-write-wins merging.
     */
    public function up(): void
    {
        Schema::create('profile_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();

            // Identity: source chosen by the app ("tmdb", "jellyfin:<server>", "iptv:<list>"…)
            // and the item id inside that source ("movie:550", "episode:1399:1:3"…).
            $table->string('source', 100);
            $table->string('item_id', 191);
            $table->string('type', 20);

            // Display snapshot, so lists render without asking the source.
            $table->string('title', 300);
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('poster_url', 2048)->nullable();
            $table->string('backdrop_url', 2048)->nullable();
            $table->string('parent_item_id', 191)->nullable();
            $table->unsignedInteger('season_number')->nullable();
            $table->unsignedInteger('episode_number')->nullable();

            $table->boolean('is_favorite')->default(false);
            $table->timestamp('favorited_at')->nullable();
            $table->timestamp('favorite_changed_at')->nullable();

            $table->unsignedBigInteger('position_ms')->default(0);
            $table->unsignedBigInteger('duration_ms')->nullable();
            // Non-null = shown in "Continuer à regarder".
            $table->timestamp('last_engaged_at')->nullable();
            $table->timestamp('progress_changed_at')->nullable();

            $table->boolean('is_watched')->default(false);
            $table->timestamp('watched_at')->nullable();
            $table->timestamp('watched_changed_at')->nullable();

            $table->unsignedBigInteger('revision');
            $table->timestamps();

            $table->unique(['profile_id', 'source', 'item_id']);
            $table->index(['profile_id', 'revision']);
            $table->index(['profile_id', 'is_favorite', 'favorited_at']);
            $table->index(['profile_id', 'last_engaged_at']);
            $table->index(['profile_id', 'is_watched', 'watched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_items');
    }
};
