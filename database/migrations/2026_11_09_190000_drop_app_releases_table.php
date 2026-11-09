<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The update check only reads the GitHub releases of the CI now (docs/mise-a-jour.md): the
     * versions declared by hand (php artisan vixeo:release, removed) are no longer used.
     */
    public function up(): void
    {
        Schema::dropIfExists('app_releases');
    }

    /** Back to the former table, empty. */
    public function down(): void
    {
        Schema::create('app_releases', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20);
            $table->string('version', 20);
            $table->unsignedInteger('build');
            $table->boolean('required')->default(false);
            $table->text('notes')->nullable();
            $table->string('download_url', 2048)->nullable();
            $table->char('sha256', 64)->nullable();
            $table->timestamp('published_at');
            $table->timestamps();
            $table->unique(['platform', 'build']);
        });
    }
};
