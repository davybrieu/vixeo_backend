<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Imports of IMDb's datasets (docs/notes.md): the version imported (Last-Modified of the files),
     * so that the hourly `imdb:import` only imports what IMDb published since.
     */
    public function up(): void
    {
        if (Schema::hasTable('imdb_imports')) {
            return;
        }
        Schema::create('imdb_imports', function (Blueprint $table) {
            $table->id();
            $table->string('version');
            $table->unsignedInteger('ratings');
            $table->unsignedInteger('episodes');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imdb_imports');
    }
};
