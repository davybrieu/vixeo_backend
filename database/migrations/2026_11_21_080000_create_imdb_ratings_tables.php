<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMDb ratings (docs/notes.md), imported every day from IMDb's datasets by `imdb:import`
     * (developer's request, 21/11/2026). They replace the ratings of other sites (MDBList) and the
     * profile's choice of a rating site.
     */
    public function up(): void
    {
        // A rating and its number of votes per IMDb title (movie, TV show, episode…): title.ratings.
        if (! Schema::hasTable('imdb_ratings')) {
            Schema::create('imdb_ratings', function (Blueprint $table) {
                $table->string('tconst', 16)->primary();
                $table->decimal('rating', 3, 1);
                $table->unsignedInteger('votes');
                // Import that last saw it (unix time of its start): older rows are gone from IMDb.
                $table->unsignedInteger('import_id')->index();
            });
        }

        // Episodes with a rating (title.episode): their show and their numbers, to find an
        // episode's rating from its show's IMDb id, without asking TMDb for each episode.
        if (! Schema::hasTable('imdb_episodes')) {
            Schema::create('imdb_episodes', function (Blueprint $table) {
                $table->string('tconst', 16)->primary();
                $table->string('parent_tconst', 16);
                $table->unsignedSmallInteger('season')->nullable();
                $table->unsignedInteger('episode')->nullable();
                $table->unsignedInteger('import_id')->index();

                $table->index(['parent_tconst', 'season', 'episode']);
            });
        }

        if (Schema::hasColumn('profiles', 'rating_source')) {
            Schema::table('profiles', fn (Blueprint $table) => $table->dropColumn('rating_source'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('imdb_episodes');
        Schema::dropIfExists('imdb_ratings');

        if (! Schema::hasColumn('profiles', 'rating_source')) {
            Schema::table('profiles', fn (Blueprint $table) => $table->string('rating_source', 20)->default('imdb'));
        }
    }
};
