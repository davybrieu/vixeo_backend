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
     */
    public function up(): void
    {
        Schema::table('tmdb_ratings', function (Blueprint $table) {
            $table->dropUnique(['profile_id', 'account_id', 'target']);
            $table->dropIndex(['profile_id', 'account_id', 'rated_at']);
        });

        Schema::table('tmdb_ratings', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->change();
            $table->unique(['profile_id', 'target']);
            $table->index(['profile_id', 'rated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tmdb_ratings', function (Blueprint $table) {
            $table->dropUnique(['profile_id', 'target']);
            $table->dropIndex(['profile_id', 'rated_at']);
        });

        DB::table('tmdb_ratings')->whereNull('account_id')->update(['account_id' => 0]);

        Schema::table('tmdb_ratings', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable(false)->change();
            $table->unique(['profile_id', 'account_id', 'target']);
            $table->index(['profile_id', 'account_id', 'rated_at']);
        });
    }
};
