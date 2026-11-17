<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plug-ins (add-ons in the Stremio format) installed by an account, or for every account
     * (`user_id` null, `php artisan addons:install`). The Vixeo catalog is built in and has no
     * row. See docs/plugins.md.
     */
    public function up(): void
    {
        if (Schema::hasTable('addons')) {
            return;
        }

        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            // The manifest's address, encrypted: a configured add-on often carries a personal key
            // in it. Found again by its SHA-256 (`url_hash`), never sent back to the apps whole.
            $table->text('transport_url');
            $table->string('url_hash', 64);
            // `id` of the manifest ("org.example.addon") and the manifest itself, refreshed now and then.
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

    public function down(): void
    {
        Schema::dropIfExists('addons');
    }
};
