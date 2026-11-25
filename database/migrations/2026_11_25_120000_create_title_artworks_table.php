<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The poster, backdrop and logo a profile chose for a title (developer's request, 25/11/2026;
 * App\Services\TitleArtworks): applied wherever the API gives the title to this profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('title_artworks')) {
            return;
        }

        Schema::create('title_artworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            /** `movie` or `tv`. */
            $table->string('media_type', 10);
            $table->unsignedInteger('tmdb_id');
            /** TMDb file paths ("/abc.jpg"); null: TMDb's choice. */
            $table->string('poster_path', 100)->nullable();
            $table->string('backdrop_path', 100)->nullable();
            $table->string('logo_path', 100)->nullable();
            $table->float('logo_aspect_ratio')->nullable();
            $table->timestamps();
            $table->unique(['profile_id', 'media_type', 'tmdb_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('title_artworks');
    }
};
