<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A TMDb account can no longer be linked to a profile (developer's request, docs/avis.md):
     * the reviews are kept by Vixeo only. The links (and their encrypted TMDb sessions) go, and
     * so does the TMDb account each review was last sent to.
     *
     * Safe to run again after a failure: each step checks first. No index uses `account_id` any
     * more (2026_11_16_030000 dropped them).
     */
    public function up(): void
    {
        Schema::dropIfExists('tmdb_links');

        if (Schema::hasColumn('tmdb_ratings', 'account_id')) {
            Schema::table('tmdb_ratings', function (Blueprint $table) {
                $table->dropColumn('account_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tmdb_ratings', 'account_id')) {
            Schema::table('tmdb_ratings', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable()->after('profile_id');
            });
        }

        if (! Schema::hasTable('tmdb_links')) {
            Schema::create('tmdb_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('profile_id')->unique()->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('account_id');
                $table->string('username');
                $table->string('name')->nullable();
                $table->string('avatar_path')->nullable();
                $table->text('session_id');
                $table->timestamp('linked_at');
                $table->timestamps();
            });
        }
    }
};
