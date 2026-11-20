<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The apps no longer use them (developer's request, 21/11/2026): the sources come from the
     * plug-ins run by the app itself (JavaScript scrapers, vixeo_frontend/docs/scrapers.md), not
     * from Stremio add-ons installed on the server (`addons`), and the server no longer keeps
     * favorite / working streams (`profile_streams`) nor dead ones (`dead_streams`).
     */
    public function up(): void
    {
        Schema::dropIfExists('addons');
        Schema::dropIfExists('profile_streams');
        Schema::dropIfExists('dead_streams');
    }

    public function down(): void
    {
        if (! Schema::hasTable('addons')) {
            Schema::create('addons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
                $table->text('transport_url');
                $table->string('url_hash', 64);
                $table->string('addon_id', 191);
                $table->json('manifest');
                $table->unsignedInteger('position')->default(0);
                $table->boolean('enabled')->default(true);
                $table->timestamp('manifest_checked_at')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'url_hash']);
                $table->index(['user_id', 'position']);
            });
        }

        if (! Schema::hasTable('profile_streams')) {
            Schema::create('profile_streams', function (Blueprint $table) {
                $table->id();
                $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
                $table->string('source', 100);
                $table->string('item_id', 191);
                $table->string('stream_id', 191);
                $table->string('stream_url', 2048)->nullable();
                $table->string('stream_type', 20)->nullable();
                $table->string('extractor', 50)->nullable();
                $table->string('quality', 50)->nullable();
                $table->string('language', 10)->nullable();
                $table->boolean('is_favorite')->default(false);
                $table->timestamp('favorited_at')->nullable();
                $table->timestamp('last_worked_at')->nullable();
                $table->timestamps();

                $table->unique(['profile_id', 'source', 'item_id', 'stream_id']);
                $table->index(['profile_id', 'source', 'item_id', 'last_worked_at']);
            });
        }

        if (! Schema::hasTable('dead_streams')) {
            Schema::create('dead_streams', function (Blueprint $table) {
                $table->id();
                $table->string('source', 100);
                $table->string('stream_id', 191);
                $table->timestamp('dead_at');
                $table->timestamps();

                $table->unique(['source', 'stream_id']);
                $table->index('dead_at');
            });
        }
    }
};
