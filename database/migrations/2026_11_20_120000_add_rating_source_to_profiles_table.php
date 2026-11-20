<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Can run again after a failure (MySQL keeps the ALTER already done).
        if (Schema::hasColumn('profiles', 'rating_source')) {
            return;
        }

        Schema::table('profiles', function (Blueprint $table) {
            $table->string('rating_source', 20)->default('imdb');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('profiles', 'rating_source')) {
            return;
        }

        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('rating_source');
        });
    }
};
