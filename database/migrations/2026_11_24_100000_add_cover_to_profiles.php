<?php

use App\Services\CoverCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A profile's cover, the banner of its page (developer's request, 24/11/2026): a cover of the
 * catalog (`cover`, App\Services\CoverCatalog) or its own image (`cover_path`,
 * App\Services\ProfileCovers). The existing profiles get a cover of the catalog at random, like a
 * new profile. Can be run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('profiles', 'cover')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->string('cover', 40)->nullable()->after('photo_path');
            });
        }
        if (! Schema::hasColumn('profiles', 'cover_path')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->string('cover_path', 120)->nullable()->after('cover');
            });
        }

        $ids = app(CoverCatalog::class)->ids();
        if ($ids === []) {
            return;
        }
        DB::table('profiles')->whereNull('cover')->orderBy('id')->select('id')
            ->chunkById(500, function ($profiles) use ($ids) {
                foreach ($profiles as $profile) {
                    DB::table('profiles')->where('id', $profile->id)->update(['cover' => $ids[array_rand($ids)]]);
                }
            });
    }

    public function down(): void
    {
        foreach (['cover_path', 'cover'] as $column) {
            if (Schema::hasColumn('profiles', $column)) {
                Schema::table('profiles', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
