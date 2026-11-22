<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL uses the composite unique index to support the profile_low_id
        // foreign key. Add a replacement index in a separate ALTER before dropping it.
        Schema::table('direct_conversations', function (Blueprint $table) {
            $table->index('profile_low_id');
        });

        Schema::table('direct_conversations', function (Blueprint $table) {
            $table->dropUnique('direct_conversations_profile_low_id_profile_high_id_unique');

            $table->string('type', 12)->default('direct')->after('id');
            $table->string('name', 80)->nullable()->after('type');
            $table->foreignId('created_by_profile_id')->nullable()->after('name')->constrained('profiles')->nullOnDelete();
            $table->string('status', 16)->default('accepted')->after('created_by_profile_id');
            $table->foreignId('requested_by_profile_id')->nullable()->after('status')->constrained('profiles')->nullOnDelete();
        });

        Schema::create('direct_conversation_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('direct_conversations')->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('role', 12)->default('member');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();
            $table->unique(['conversation_id', 'profile_id']);
            $table->index(['profile_id', 'left_at']);
        });

        $rows = DB::table('direct_conversations')->get(['id', 'profile_low_id', 'profile_high_id']);
        foreach ($rows as $row) {
            foreach (array_unique([(int) $row->profile_low_id, (int) $row->profile_high_id]) as $profileId) {
                DB::table('direct_conversation_members')->insert([
                    'conversation_id' => $row->id,
                    'profile_id' => $profileId,
                    'role' => 'member',
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::create('direct_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('direct_messages')->cascadeOnDelete();
            $table->string('disk', 24)->default('local');
            $table->string('path');
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });

        Schema::create('direct_message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('direct_messages')->cascadeOnDelete();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->unique(['message_id', 'profile_id']);
        });

        Schema::create('direct_message_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('blocked_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['blocker_profile_id', 'blocked_profile_id']);
        });

        Schema::table('direct_messages', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->index();
            $table->foreignId('deleted_by_profile_id')->nullable()->constrained('profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('direct_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by_profile_id');
            $table->dropColumn('deleted_at');
        });
        Schema::dropIfExists('direct_message_blocks');
        Schema::dropIfExists('direct_message_reads');
        Schema::dropIfExists('direct_message_attachments');
        Schema::dropIfExists('direct_conversation_members');
        Schema::table('direct_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by_profile_id');
            $table->dropColumn(['type', 'name', 'status']);
            $table->dropConstrainedForeignId('created_by_profile_id');
            $table->unique(['profile_low_id', 'profile_high_id']);
            $table->dropIndex('direct_conversations_profile_low_id_index');
        });
    }
};
