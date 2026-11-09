<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Streams reported as dead by the players (link expired, 403, unsupported codec…). Shared by
     * every account and kept for 24 h (like ONYX's IptvDeadServersCache): the sources panel drops
     * them right away instead of making each viewer wait for the failure. See docs/lecture.md.
     */
    public function up(): void
    {
        Schema::create('dead_streams', function (Blueprint $table) {
            $table->id();
            // Provider id ("frenchstream", "wiflix", "vavoo"…) and the stream the app knows it by
            // (usually the player page URL returned in the sources list).
            $table->string('source', 100);
            $table->string('stream_id', 191);
            $table->timestamp('dead_at');
            $table->timestamps();

            $table->unique(['source', 'stream_id']);
            $table->index('dead_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dead_streams');
    }
};
