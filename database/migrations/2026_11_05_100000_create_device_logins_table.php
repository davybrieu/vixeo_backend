<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sign-in of a TV (or any device without a keyboard) approved from a signed-in phone.
     */
    public function up(): void
    {
        Schema::create('device_logins', function (Blueprint $table) {
            $table->id();
            // Secret the TV polls with: only its SHA-256 is stored.
            $table->string('device_code_hash', 64)->unique();
            // Short code the person types or scans (6 characters, no ambiguous ones).
            $table->string('user_code', 6)->unique();
            $table->string('device_name', 100);
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_logins');
    }
};
