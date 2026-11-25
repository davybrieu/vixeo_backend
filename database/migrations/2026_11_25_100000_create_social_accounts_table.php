<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « Continuer avec Google / Apple » (docs/connexion-sociale.md): the accounts of a provider linked
 * to a Vixeo account, and an account without a password (created with Google or Apple).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_accounts')) {
            Schema::create('social_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                /** `google` or `apple`. */
                $table->string('provider', 20);
                /** The provider's identifier of the person (`sub` of its ID token). */
                $table->string('subject');
                /** The address the provider gave (Apple: maybe a relay address). */
                $table->string('email')->nullable();
                $table->timestamps();
                $table->unique(['provider', 'subject']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
