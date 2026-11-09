<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stream preferences of a profile, per content: the streams the viewer marked as favorites
     * (❤, tried first) and the last stream that actually worked (tried next). One row per
     * (content, stream). See docs/lecture.md.
     */
    public function up(): void
    {
        Schema::create('profile_streams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();

            // Content identity, same convention as profile_items (source + item_id).
            $table->string('source', 100);
            $table->string('item_id', 191);
            // Stream chosen by the app inside that content ("vavoo:<channelId>", a player URL…).
            $table->string('stream_id', 191);

            // What the player needs to replay it without resolving again.
            $table->string('stream_url', 2048)->nullable();
            $table->string('stream_type', 20)->nullable();
            $table->string('extractor', 50)->nullable();
            $table->string('quality', 50)->nullable();
            $table->string('language', 10)->nullable();

            $table->boolean('is_favorite')->default(false);
            $table->timestamp('favorited_at')->nullable();
            // Last time this stream played successfully: the most recent one is tried first.
            $table->timestamp('last_worked_at')->nullable();
            $table->timestamps();

            $table->unique(['profile_id', 'source', 'item_id', 'stream_id']);
            $table->index(['profile_id', 'source', 'item_id', 'last_worked_at']);
            $table->index(['profile_id', 'is_favorite', 'favorited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_streams');
    }
};
