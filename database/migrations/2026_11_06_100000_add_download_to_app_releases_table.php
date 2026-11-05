<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Android ships as an APK (no Play Store): where to download it, and its SHA-256. */
    public function up(): void
    {
        Schema::table('app_releases', function (Blueprint $table) {
            $table->string('download_url', 2048)->nullable()->after('notes');
            $table->char('sha256', 64)->nullable()->after('download_url');
        });
    }

    public function down(): void
    {
        Schema::table('app_releases', function (Blueprint $table) {
            $table->dropColumn(['download_url', 'sha256']);
        });
    }
};
