<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A profile's TMDb account (docs/compte-tmdb.md). The same TMDb account can be linked to
        // several profiles, of any Vixeo account: no unique index on account_id.
        Schema::create('tmdb_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('account_id');
            $table->string('username');
            $table->string('name')->nullable();
            $table->string('avatar_path')->nullable();
            // TMDb session of this link only, encrypted (APP_KEY): it gives write access to the account.
            $table->text('session_id');
            $table->timestamp('linked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tmdb_links');
    }
};
