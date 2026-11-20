<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two more synced groups (developer's request, docs/synchronisation.md): « Favoris » ❤ on
     * movies and TV shows (`liked`, back after their removal on 2026-11-11), and people a profile
     * follows (`followed`, items of type `person`). Same last-write-wins merging as « Ma liste »,
     * each with its own date of change.
     *
     * Safe to run again after a failure: each column and index is checked first.
     */
    public function up(): void
    {
        foreach (['liked', 'followed'] as $group) {
            Schema::table('profile_items', function (Blueprint $table) use ($group) {
                if (! Schema::hasColumn('profile_items', "is_{$group}")) {
                    $table->boolean("is_{$group}")->default(false);
                }
                if (! Schema::hasColumn('profile_items', "{$group}_at")) {
                    $table->timestamp("{$group}_at")->nullable();
                }
                if (! Schema::hasColumn('profile_items', "{$group}_changed_at")) {
                    $table->timestamp("{$group}_changed_at")->nullable();
                }
            });

            Schema::table('profile_items', function (Blueprint $table) use ($group) {
                if (! Schema::hasIndex('profile_items', ['profile_id', "is_{$group}", "{$group}_at"])) {
                    $table->index(['profile_id', "is_{$group}", "{$group}_at"]);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['liked', 'followed'] as $group) {
            // The index first: on MySQL the profile_id foreign key keeps the unique (profile, source, item) index.
            Schema::table('profile_items', function (Blueprint $table) use ($group) {
                if (Schema::hasIndex('profile_items', ['profile_id', "is_{$group}", "{$group}_at"])) {
                    $table->dropIndex(['profile_id', "is_{$group}", "{$group}_at"]);
                }
            });

            Schema::table('profile_items', function (Blueprint $table) use ($group) {
                $columns = array_values(array_filter(
                    ["is_{$group}", "{$group}_at", "{$group}_changed_at"],
                    fn (string $column) => Schema::hasColumn('profile_items', $column),
                ));
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
