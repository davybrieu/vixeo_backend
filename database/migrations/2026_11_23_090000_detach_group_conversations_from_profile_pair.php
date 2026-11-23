<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A group conversation filled `profile_low_id` / `profile_high_id` with two of its members, whose
 * foreign keys cascade: deleting one of these two profiles (or their account) deleted the whole
 * group, and every message, for everybody. The pair now only describes a direct conversation;
 * groups leave it empty (their members are in direct_conversation_members).
 *
 * Retry-safe: making a nullable column nullable again and clearing it again change nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('direct_conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('profile_low_id')->nullable()->change();
            $table->unsignedBigInteger('profile_high_id')->nullable()->change();
        });

        DB::table('direct_conversations')->where('type', 'group')->update([
            'profile_low_id' => null,
            'profile_high_id' => null,
        ]);

        // The lookup of a direct conversation between two profiles (start a conversation).
        if (! Schema::hasIndex('direct_conversations', 'direct_conversations_pair_index')) {
            Schema::table('direct_conversations', function (Blueprint $table) {
                $table->index(['type', 'profile_low_id', 'profile_high_id', 'status'], 'direct_conversations_pair_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('direct_conversations', 'direct_conversations_pair_index')) {
            Schema::table('direct_conversations', function (Blueprint $table) {
                $table->dropIndex('direct_conversations_pair_index');
            });
        }
        // Groups have no pair to restore: they go with the columns' NOT NULL.
        DB::table('direct_conversations')->whereNull('profile_low_id')->orWhereNull('profile_high_id')->delete();

        Schema::table('direct_conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('profile_low_id')->nullable(false)->change();
            $table->unsignedBigInteger('profile_high_id')->nullable(false)->change();
        });
    }
};
