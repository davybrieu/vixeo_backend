<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Episodes can be rated again (developer's request, 21/11/2026, docs/avis.md): the columns
     * dropped by 2026_11_20_230000_drop_episode_reviews come back (empty: the reviews it deleted
     * are gone). Safe to run again after a failure: each column is checked first.
     */
    public function up(): void
    {
        $columns = [
            'season_number' => fn (Blueprint $table) => $table->unsignedSmallInteger('season_number')->nullable()->after('tmdb_id'),
            'episode_number' => fn (Blueprint $table) => $table->unsignedSmallInteger('episode_number')->nullable()->after('season_number'),
            'episode_name' => fn (Blueprint $table) => $table->string('episode_name')->nullable()->after('title'),
            'still_path' => fn (Blueprint $table) => $table->string('still_path')->nullable()->after('backdrop_path'),
        ];
        foreach ($columns as $column => $add) {
            if (! Schema::hasColumn('tmdb_ratings', $column)) {
                Schema::table('tmdb_ratings', $add);
            }
        }
    }

    public function down(): void
    {
        foreach (['season_number', 'episode_number', 'episode_name', 'still_path'] as $column) {
            if (Schema::hasColumn('tmdb_ratings', $column)) {
                Schema::table('tmdb_ratings', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
