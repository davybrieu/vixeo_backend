<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An account's choice for a plug-in it does not own: the Vixeo catalog ("vixeo") or a plug-in
     * installed for every account (its number). The account's own plug-ins keep `addons.enabled`.
     * No row: enabled. See docs/plugins.md.
     */
    public function up(): void
    {
        if (Schema::hasTable('addon_preferences')) {
            return;
        }

        Schema::create('addon_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('addon_key', 20);
            $table->boolean('enabled');
            $table->timestamps();

            $table->unique(['user_id', 'addon_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_preferences');
    }
};
