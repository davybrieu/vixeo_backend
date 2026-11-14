<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;
use Illuminate\\Support\\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->string('username', 30)->nullable()->after('name');
            $table->string('bio', 500)->nullable()->after('username');
        });

        // Give existing profiles a stable public handle before enforcing global uniqueness.
        DB::table('profiles')->orderBy('id')->each(function (object $profile): void {
            $base = Str::slug($profile->name) ?: 'membre';
            $base = Str::lower(Str::limit(str_replace('-', '_', $base), 22, ''));
            $username = $base.'_'.$profile->id;
            DB::table('profiles')->where('id', $profile->id)->update(['username' => $username]);
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->unique('username');
        });

        Schema::create('profile_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('follower_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('followed_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['follower_profile_id', 'followed_profile_id']);
            $table->index(['followed_profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_follows');
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'bio']);
        });
    }
};
