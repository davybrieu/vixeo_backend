<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The social network of movies and TV shows (developer's request, 23/11/2026; docs/social.md):
 * lists and top 10s, comments on reviews and lists, spoilers, the diary (« Journal »), the feed of
 * the followed profiles, reports and moderation, muted profiles, the 4 favorites of a profile, the
 * rating proposed at the end of playback, the first launch, and titles, reviews or lists sent in
 * a message. Can be run again after a failure (MySQL keeps the ALTERs already done).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addColumns('tmdb_ratings', [
            'contains_spoilers' => fn (Blueprint $t) => $t->boolean('contains_spoilers')->default(false)->after('comment'),
            // Hidden by the moderation (docs/moderation.md).
            'hidden_at' => fn (Blueprint $t) => $t->timestamp('hidden_at')->nullable(),
        ]);

        $onboarded = ! Schema::hasColumn('profiles', 'onboarded_at');
        $this->addColumns('profiles', [
            // « Mes 4 favoris » at the top of the profile: [{type, id, title, year, poster_path}].
            'pinned_titles' => fn (Blueprint $t) => $t->json('pinned_titles')->nullable(),
            // Propose to rate at the end of a movie or an episode.
            'rate_after_watching' => fn (Blueprint $t) => $t->boolean('rate_after_watching')->default(true),
            // The first launch (rate known titles, accounts to follow) was done or skipped.
            'onboarded_at' => fn (Blueprint $t) => $t->timestamp('onboarded_at')->nullable(),
            // Suspended by the moderation: no page, no content shown.
            'suspended_at' => fn (Blueprint $t) => $t->timestamp('suspended_at')->nullable(),
        ]);
        if ($onboarded) {
            // The profiles that already use Vixeo don't see the first launch.
            DB::table('profiles')->update(['onboarded_at' => now()]);
        }

        if (! Schema::hasTable('profile_lists')) {
            Schema::create('profile_lists', function (Blueprint $table) {
                $table->id();
                $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
                $table->string('title', 100);
                $table->text('description')->nullable();
                // `top`: numbered, 10 titles at most; `list`: in the chosen order, 100 at most.
                $table->string('kind', 8)->default('list');
                $table->unsignedSmallInteger('items_count')->default(0);
                // First time it had a title: then it appears in the feed.
                $table->timestamp('published_at')->nullable();
                $table->timestamp('hidden_at')->nullable();
                $table->timestamps();
                $table->index(['profile_id', 'updated_at']);
            });
        }

        if (! Schema::hasTable('profile_list_items')) {
            Schema::create('profile_list_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('profile_list_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('position');
                $table->string('media_type', 5);
                $table->unsignedInteger('tmdb_id');
                // What the list shows of the title (from TMDb when it was added).
                $table->string('title');
                $table->unsignedSmallInteger('year')->nullable();
                $table->string('poster_path')->nullable();
                $table->string('backdrop_path')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->unique(['profile_list_id', 'media_type', 'tmdb_id']);
                $table->index(['media_type', 'tmdb_id']);
            });
        }

        if (! Schema::hasTable('list_likes')) {
            Schema::create('list_likes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
                $table->foreignId('profile_list_id')->constrained()->cascadeOnDelete();
                $table->timestamp('created_at')->nullable();
                $table->unique(['profile_id', 'profile_list_id']);
                $table->index('profile_list_id');
            });
        }

        if (! Schema::hasTable('social_comments')) {
            Schema::create('social_comments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
                // On a review or on a list.
                $table->foreignId('tmdb_rating_id')->nullable()->constrained('tmdb_ratings')->cascadeOnDelete();
                $table->foreignId('profile_list_id')->nullable()->constrained()->cascadeOnDelete();
                $table->text('body');
                $table->boolean('contains_spoilers')->default(false);
                $table->timestamp('hidden_at')->nullable();
                $table->timestamps();
                $table->index(['tmdb_rating_id', 'id']);
                $table->index(['profile_list_id', 'id']);
            });
        }

        if (! Schema::hasTable('diary_entries')) {
            Schema::create('diary_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
                $table->string('media_type', 5);
                $table->unsignedInteger('tmdb_id');
                $table->unsignedSmallInteger('season_number')->nullable();
                $table->unsignedSmallInteger('episode_number')->nullable();
                $table->string('title');
                $table->string('episode_name')->nullable();
                $table->unsignedSmallInteger('year')->nullable();
                $table->string('poster_url', 500)->nullable();
                $table->timestamp('watched_at');
                $table->boolean('rewatch')->default(false);
                // From TMDb after the entry is written (the « Bilan de l'année »).
                $table->unsignedSmallInteger('runtime')->nullable();
                $table->json('genres')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['profile_id', 'watched_at']);
                $table->index(['profile_id', 'media_type', 'tmdb_id']);
            });
        }

        if (! Schema::hasTable('social_activities')) {
            Schema::create('social_activities', function (Blueprint $table) {
                $table->id();
                // Who did it: a review, a watched title (diary) or a list.
                $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
                $table->string('type', 10);
                $table->foreignId('tmdb_rating_id')->nullable()->constrained('tmdb_ratings')->cascadeOnDelete();
                $table->foreignId('diary_entry_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('profile_list_id')->nullable()->constrained()->cascadeOnDelete();
                $table->timestamp('created_at')->nullable();
                $table->index(['profile_id', 'id']);
            });
        }

        if (! Schema::hasTable('profile_mutes')) {
            Schema::create('profile_mutes', function (Blueprint $table) {
                $table->id();
                // Hidden from the feed of `profile_id`, still followed.
                $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
                $table->foreignId('muted_profile_id')->constrained('profiles')->cascadeOnDelete();
                $table->timestamp('created_at')->nullable();
                $table->unique(['profile_id', 'muted_profile_id']);
            });
        }

        if (! Schema::hasTable('reports')) {
            Schema::create('reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reporter_profile_id')->constrained('profiles')->cascadeOnDelete();
                // profile, review, list, comment or message, and its id.
                $table->string('subject_type', 10);
                $table->unsignedBigInteger('subject_id');
                // Who wrote it (the reported profile itself for a profile).
                $table->foreignId('author_profile_id')->nullable()->constrained('profiles')->nullOnDelete();
                $table->string('reason', 20);
                $table->text('details')->nullable();
                // open, hidden (content hidden or profile suspended) or dismissed.
                $table->string('status', 10)->default('open');
                $table->text('decision')->nullable();
                $table->timestamp('handled_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'id']);
            });
        }

        $this->addColumns('social_notifications', [
            'profile_list_id' => fn (Blueprint $t) => $t->foreignId('profile_list_id')->nullable()->constrained()->cascadeOnDelete(),
            'social_comment_id' => fn (Blueprint $t) => $t->foreignId('social_comment_id')->nullable()->constrained()->cascadeOnDelete(),
            // A decision of the moderation: what was hidden and why.
            'data' => fn (Blueprint $t) => $t->json('data')->nullable(),
        ]);

        $this->addColumns('direct_messages', [
            // A title, a review or a list sent in the message (« Recommander à un ami »).
            'shared' => fn (Blueprint $t) => $t->json('shared')->nullable(),
        ]);
    }

    public function down(): void
    {
        foreach (['direct_messages' => ['shared'], 'social_notifications' => ['data']] as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
                }
            }
        }
        foreach (['social_comment_id', 'profile_list_id'] as $column) {
            if (Schema::hasColumn('social_notifications', $column)) {
                Schema::table('social_notifications', fn (Blueprint $t) => $t->dropConstrainedForeignId($column));
            }
        }
        foreach (['reports', 'profile_mutes', 'social_activities', 'diary_entries', 'social_comments', 'list_likes', 'profile_list_items', 'profile_lists'] as $table) {
            Schema::dropIfExists($table);
        }
        foreach (['profiles' => ['pinned_titles', 'rate_after_watching', 'onboarded_at', 'suspended_at'], 'tmdb_ratings' => ['contains_spoilers', 'hidden_at']] as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
                }
            }
        }
    }

    /** @param  array<string, Closure(Blueprint): mixed>  $columns */
    private function addColumns(string $table, array $columns): void
    {
        foreach ($columns as $column => $define) {
            if (! Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $define($t));
            }
        }
    }
};
