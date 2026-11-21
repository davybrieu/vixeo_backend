<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Social features (developer's request, 21/11/2026; docs/social.md): links to the profile's social
 * networks, private accounts and their follow requests, likes of reviews, the notifications page
 * and the push setting of the social activity. Can be run again after a failure (MySQL keeps the
 * ALTERs already done).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('profiles', 'is_private')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->boolean('is_private')->default(false)->after('bio');
            });
        }
        if (! Schema::hasColumn('profiles', 'social_links')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->json('social_links')->nullable()->after('is_private');
            });
        }

        // A follow of a private account waits for its approval: null until accepted.
        if (! Schema::hasColumn('profile_follows', 'accepted_at')) {
            Schema::table('profile_follows', function (Blueprint $table) {
                $table->timestamp('accepted_at')->nullable()->after('followed_profile_id');
            });
            DB::table('profile_follows')->whereNull('accepted_at')->update(['accepted_at' => DB::raw('created_at')]);
        }

        if (! Schema::hasTable('review_likes')) {
            Schema::create('review_likes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tmdb_rating_id')->constrained('tmdb_ratings')->cascadeOnDelete();
                $table->timestamp('created_at')->nullable();
                $table->unique(['profile_id', 'tmdb_rating_id']);
                $table->index('tmdb_rating_id');
            });
        }

        if (! Schema::hasTable('social_notifications')) {
            Schema::create('social_notifications', function (Blueprint $table) {
                $table->id();
                // Who receives it, who did it.
                $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
                $table->foreignId('actor_profile_id')->constrained('profiles')->cascadeOnDelete();
                $table->string('type', 30);
                $table->foreignId('tmdb_rating_id')->nullable()->constrained('tmdb_ratings')->cascadeOnDelete();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['profile_id', 'created_at']);
                $table->index(['profile_id', 'read_at']);
            });
        }

        if (! Schema::hasColumn('push_devices', 'social')) {
            Schema::table('push_devices', function (Blueprint $table) {
                $table->boolean('social')->default(true)->after('new_episodes');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_notifications');
        Schema::dropIfExists('review_likes');
        foreach ([['push_devices', 'social'], ['profile_follows', 'accepted_at'], ['profiles', 'social_links'], ['profiles', 'is_private']] as [$table, $column]) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
