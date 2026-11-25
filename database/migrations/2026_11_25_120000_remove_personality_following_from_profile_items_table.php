<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Personality entries existed only to store follows; they are no longer profile content.
        DB::table('profile_items')->where('type', 'person')->delete();

        if (Schema::hasIndex('profile_items', ['profile_id', 'is_followed', 'followed_at'])) {
            Schema::table('profile_items', fn (Blueprint $table) => $table->dropIndex(['profile_id', 'is_followed', 'followed_at']));
        }

        $columns = array_values(array_filter(
            ['is_followed', 'followed_at', 'followed_changed_at'],
            fn (string $column) => Schema::hasColumn('profile_items', $column),
        ));
        if ($columns !== []) {
            Schema::table('profile_items', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }

    public function down(): void
    {
        foreach (['is_followed', 'followed_at', 'followed_changed_at'] as $column) {
            if (! Schema::hasColumn('profile_items', $column)) {
                Schema::table('profile_items', function (Blueprint $table) use ($column) {
                    match ($column) {
                        'is_followed' => $table->boolean($column)->default(false),
                        default => $table->timestamp($column)->nullable(),
                    };
                });
            }
        }

        if (! Schema::hasIndex('profile_items', ['profile_id', 'is_followed', 'followed_at'])) {
            Schema::table('profile_items', fn (Blueprint $table) => $table->index(['profile_id', 'is_followed', 'followed_at']));
        }
    }
};
