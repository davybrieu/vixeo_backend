<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Published versions of the Android and iOS apps (docs/mise-a-jour.md). */
    public function up(): void
    {
        Schema::create('app_releases', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20);
            // "1.2.0": shown to people. The build number decides (always increasing).
            $table->string('version', 20);
            $table->unsignedInteger('build');
            // Every install older than this build must update.
            $table->boolean('required')->default(false);
            $table->text('notes')->nullable();
            // Invisible to the apps before this date.
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['platform', 'build']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
    }
};
