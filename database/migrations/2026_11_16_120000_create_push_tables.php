<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Push notifications (docs/notifications.md): the apps that can receive them, and the episodes
 * already announced to each profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Profile in use on the device: its new episodes are announced there.
            $table->foreignId('profile_id')->nullable()->constrained()->nullOnDelete();
            // One per signed-in app: signing out (the API token deleted) removes it.
            $table->foreignId('personal_access_token_id')->unique()->constrained()->cascadeOnDelete();
            // Firebase Cloud Messaging registration token, encrypted; its hash finds it again.
            $table->text('token');
            $table->char('token_hash', 64)->unique();
            $table->string('platform', 16);
            $table->boolean('new_episodes')->default(false);
            // Since when: episodes out before are not announced.
            $table->timestamp('new_episodes_since')->nullable();
            // Device time minus UTC, in minutes: no episode notification at night.
            $table->smallInteger('utc_offset')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['new_episodes', 'profile_id']);
        });

        Schema::create('episode_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('show_id');
            $table->unsignedSmallInteger('season_number');
            $table->unsignedSmallInteger('episode_number');
            $table->timestamp('available_at');
            $table->timestamp('sent_at');

            $table->unique(['profile_id', 'show_id', 'season_number', 'episode_number'], 'episode_notifications_episode_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episode_notifications');
        Schema::dropIfExists('push_devices');
    }
};
