<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The server no longer has stream providers, scrapers or extractors (developer's request,
     * docs/plugins.md): the sources come from the plug-ins only. The links no extractor knew go.
     */
    public function up(): void
    {
        Schema::dropIfExists('missing_extractors');
    }

    public function down(): void
    {
        if (Schema::hasTable('missing_extractors')) {
            return;
        }

        Schema::create('missing_extractors', function (Blueprint $table) {
            $table->id();
            $table->string('url', 2048);
            $table->string('host')->index();
            $table->string('provider')->nullable();
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->unsignedBigInteger('tmdb_id')->nullable()->index();
            $table->string('title')->nullable();
            $table->unsignedInteger('season')->nullable();
            $table->unsignedInteger('episode')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamps();

            $table->unique('url');
        });
    }
};
