<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep the oldest profile for each account. All additional profile-owned data is intentionally
        // discarded as part of the one-account/one-profile transition.
        DB::transaction(function (): void {
            $userIds = DB::table('profiles')
                ->select('user_id')
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                $duplicateIds = DB::table('profiles')
                    ->where('user_id', $userId)
                    ->orderBy('id')
                    ->skip(1)
                    ->pluck('id');

                DB::table('profiles')->whereIn('id', $duplicateIds)->delete();
            }
        });

        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropUnique('profiles_user_id_name_unique');
            $table->unique('user_id');
            $table->dropColumn([
                'is_kid',
                'max_age_rating',
                'pin',
                'allow_unrated',
                'locked_sources',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('parental_pin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('parental_pin')->nullable();
        });

        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropUnique('profiles_user_id_unique');
            $table->unique(['user_id', 'name']);
            $table->boolean('is_kid')->default(false);
            $table->unsignedTinyInteger('max_age_rating')->nullable();
            $table->string('pin')->nullable();
            $table->boolean('allow_unrated')->default(false);
            $table->json('locked_sources')->nullable();
        });
    }
};
