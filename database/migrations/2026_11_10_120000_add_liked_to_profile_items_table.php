<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Favoris" (❤), separate from "Ma liste" (is_favorite): movies, TV shows, episodes and live
     * channels a profile loves. Same last-write-wins merging, with its own liked_changed_at.
     */
    public function up(): void
    {
        Schema::table('profile_items', function (Blueprint $table) {
            $table->boolean('is_liked')->default(false)->after('favorite_changed_at');
            $table->timestamp('liked_at')->nullable()->after('is_liked');
            $table->timestamp('liked_changed_at')->nullable()->after('liked_at');

            $table->index(['profile_id', 'is_liked', 'liked_at']);
        });
    }

    public function down(): void
    {
        Schema::table('profile_items', function (Blueprint $table) {
            $table->dropIndex(['profile_id', 'is_liked', 'liked_at']);
            $table->dropColumn(['is_liked', 'liked_at', 'liked_changed_at']);
        });
    }
};
