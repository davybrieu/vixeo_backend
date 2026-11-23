<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « Mes 4 favoris » removed from the profile (developer's request, 23/11/2026). Can be run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('profiles', 'pinned_titles')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->dropColumn('pinned_titles');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('profiles', 'pinned_titles')) {
            Schema::table('profiles', function (Blueprint $table) {
                $table->json('pinned_titles')->nullable();
            });
        }
    }
};
