<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The « Fil d'actualité » shows the titles a profile likes (« J'aime »), next to its reviews, lists
 * and watched titles: an activity can point at a synced item (developer's request, 23/11/2026).
 * Lists have no kind any more: a « Top 10 » is a list like the others, in the order its author
 * chose. Can be run again after a failure (MySQL keeps the ALTERs already done).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('social_activities', 'profile_item_id')) {
            Schema::table('social_activities', function (Blueprint $table) {
                // A title liked (« J'aime »): unliking it deletes the activity.
                $table->foreignId('profile_item_id')->nullable()->constrained()->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('profile_lists', 'kind')) {
            Schema::table('profile_lists', function (Blueprint $table) {
                $table->dropColumn('kind');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('profile_lists', 'kind')) {
            Schema::table('profile_lists', function (Blueprint $table) {
                $table->string('kind', 8)->default('list');
            });
        }

        if (Schema::hasColumn('social_activities', 'profile_item_id')) {
            Schema::table('social_activities', function (Blueprint $table) {
                $table->dropConstrainedForeignId('profile_item_id');
            });
        }
    }
};
