<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Episodes can no longer be rated (developer's request, docs/avis.md): their reviews go, and
     * so do the columns only they used. Safe to run again after a failure: each step checks
     * first; no index uses these columns.
     */
    public function up(): void
    {
        DB::table('tmdb_ratings')->where('type', 'episode')->delete();

        foreach (['season_number', 'episode_number', 'episode_name', 'still_path'] as $column) {
            if (Schema::hasColumn('tmdb_ratings', $column)) {
                Schema::table('tmdb_ratings', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    /** The columns come back empty: the deleted reviews are not restored. */
    public function down(): void
    {
        Schema::table('tmdb_ratings', function (Blueprint $table) {
            if (! Schema::hasColumn('tmdb_ratings', 'season_number')) {
                $table->unsignedSmallInteger('season_number')->nullable()->after('tmdb_id');
            }
            if (! Schema::hasColumn('tmdb_ratings', 'episode_number')) {
                $table->unsignedSmallInteger('episode_number')->nullable()->after('season_number');
            }
            if (! Schema::hasColumn('tmdb_ratings', 'episode_name')) {
                $table->string('episode_name')->nullable()->after('title');
            }
            if (! Schema::hasColumn('tmdb_ratings', 'still_path')) {
                $table->string('still_path')->nullable()->after('backdrop_path');
            }
        });
    }
};
