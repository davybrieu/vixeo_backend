<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reviews belong to Vixeo (docs/compte-tmdb.md): written without a TMDb account, and synced
     * to the profile's TMDb account when one is linked. `account_id` is now the TMDb account the
     * review was last sent to (null: not sent yet).
     *
     * Safe to run again after a failure, and on MySQL: the new indexes are added before the old
     * ones are dropped, since MySQL refuses to drop the last index its `profile_id` foreign key
     * can use (error 1553). A first version dropped the old unique index, then failed there.
     */
    public function up(): void
    {
        // One review per profile and title: a profile that linked two TMDb accounts could have two
        // (the most recent stays).
        $duplicates = DB::table('tmdb_ratings')
            ->select('profile_id', 'target')
            ->groupBy('profile_id', 'target')
            ->havingRaw('count(*) > 1')
            ->get();
        foreach ($duplicates as $duplicate) {
            $rows = DB::table('tmdb_ratings')->where('profile_id', $duplicate->profile_id)->where('target', $duplicate->target);
            $keep = (clone $rows)->orderByDesc('rated_at')->orderByDesc('id')->value('id');
            (clone $rows)->where('id', '!=', $keep)->delete();
        }

        Schema::table('tmdb_ratings', function (Blueprint $table) {
            if (! Schema::hasIndex('tmdb_ratings', ['profile_id', 'target'], 'unique')) {
                $table->unique(['profile_id', 'target']);
            }
            if (! Schema::hasIndex('tmdb_ratings', ['profile_id', 'rated_at'])) {
                $table->index(['profile_id', 'rated_at']);
            }
        });

        Schema::table('tmdb_ratings', function (Blueprint $table) {
            if (Schema::hasIndex('tmdb_ratings', ['profile_id', 'account_id', 'target'], 'unique')) {
                $table->dropUnique(['profile_id', 'account_id', 'target']);
            }
            if (Schema::hasIndex('tmdb_ratings', ['profile_id', 'account_id', 'rated_at'])) {
                $table->dropIndex(['profile_id', 'account_id', 'rated_at']);
            }
        });

        Schema::table('tmdb_ratings', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('tmdb_ratings')->whereNull('account_id')->update(['account_id' => 0]);

        Schema::table('tmdb_ratings', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable(false)->change();
        });

        // The old indexes first (MySQL's foreign key needs one at all times).
        Schema::table('tmdb_ratings', function (Blueprint $table) {
            if (! Schema::hasIndex('tmdb_ratings', ['profile_id', 'account_id', 'target'], 'unique')) {
                $table->unique(['profile_id', 'account_id', 'target']);
            }
            if (! Schema::hasIndex('tmdb_ratings', ['profile_id', 'account_id', 'rated_at'])) {
                $table->index(['profile_id', 'account_id', 'rated_at']);
            }
        });

        Schema::table('tmdb_ratings', function (Blueprint $table) {
            if (Schema::hasIndex('tmdb_ratings', ['profile_id', 'target'], 'unique')) {
                $table->dropUnique(['profile_id', 'target']);
            }
            if (Schema::hasIndex('tmdb_ratings', ['profile_id', 'rated_at'])) {
                $table->dropIndex(['profile_id', 'rated_at']);
            }
        });
    }
};
