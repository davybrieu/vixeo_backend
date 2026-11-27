<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->timestamp('registration_completed_at')->nullable()->after('onboarded_at');
        });

        // Existing accounts already have a profile and must not be forced through registration.
        DB::table('profiles')->whereNull('onboarded_at')->update(['onboarded_at' => now()]);
        DB::table('profiles')->whereNull('registration_completed_at')->update([
            'registration_completed_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn('registration_completed_at');
        });
    }
};
