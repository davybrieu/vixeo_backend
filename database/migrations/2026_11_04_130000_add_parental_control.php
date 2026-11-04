<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Account-wide parental PIN (hashed). Null = parental control disabled.
            $table->string('parental_pin')->nullable();
        });

        Schema::table('profiles', function (Blueprint $table) {
            // With an age limit: also show titles without a known age rating.
            $table->boolean('allow_unrated')->default(false);
            // Sources / folders hidden on this profile ("iptv:<list>", "iptv:<list>#<group>"…).
            $table->json('locked_sources')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['allow_unrated', 'locked_sources']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('parental_pin');
        });
    }
};
