<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A profile's own photo, shown instead of its avatar (developer's request, 23/11/2026):
 * the path of its file (App\Services\ProfilePhotos). Can be run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('profiles', 'photo_path')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->string('photo_path', 120)->nullable()->after('avatar');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('profiles', 'photo_path')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->dropColumn('photo_path');
            });
        }
    }
};
