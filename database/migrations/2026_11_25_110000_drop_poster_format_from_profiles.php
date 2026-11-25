<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vertical posters only (developer's request, 25/11/2026): the apps have no « Format des affiches »
 * any more, the profile no `poster_format`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('profiles', 'poster_format')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->dropColumn('poster_format');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('profiles', 'poster_format')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->string('poster_format', 16)->default('portrait');
            });
        }
    }
};
