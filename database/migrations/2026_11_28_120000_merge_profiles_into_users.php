<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The account row becomes the only identity row. Profile-owned attributes move to users and every
 * FK that pointed to profiles is remapped through profiles.user_id before profiles is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('profiles')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 30)->nullable()->unique();
            $table->string('bio', 500)->nullable();
            $table->boolean('is_private')->default(false);
            $table->json('social_links')->nullable();
            $table->string('avatar', 100)->nullable();
            $table->string('photo_path', 120)->nullable();
            $table->string('cover', 40)->nullable();
            $table->string('cover_path', 120)->nullable();
            $table->unsignedTinyInteger('position')->default(0);
            $table->unsignedBigInteger('sync_revision')->default(0);
            $table->string('audio_language', 10)->nullable();
            $table->string('subtitle_language', 10)->nullable();
            $table->boolean('no_spoiler')->default(false);
            $table->boolean('skip_intro')->default(false);
            $table->boolean('skip_recap')->default(false);
            $table->boolean('skip_credits')->default(false);
            $table->boolean('skip_preview')->default(false);
            $table->boolean('rate_after_watching')->default(true);
            $table->string('rating_source', 20)->default('imdb');
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamp('registration_completed_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
        });

        DB::table('profiles')->orderBy('id')->each(function (object $profile): void {
            $attributes = [];
            foreach ([
                'name', 'username', 'bio', 'is_private', 'social_links', 'avatar', 'photo_path',
                'cover', 'cover_path', 'position', 'sync_revision', 'audio_language',
                'subtitle_language', 'no_spoiler', 'skip_intro', 'skip_recap', 'skip_credits',
                'skip_preview', 'rate_after_watching', 'rating_source', 'onboarded_at',
                'registration_completed_at', 'suspended_at',
            ] as $column) {
                if (property_exists($profile, $column)) {
                    $attributes[$column] = $profile->{$column};
                }
            }

            DB::table('users')->where('id', $profile->user_id)->update($attributes);
        });

        // Polymorphic profile reports have no FK, so remap their subject IDs separately.\n        if (Schema::hasTable('reports') && Schema::hasColumn('reports', 'subject_type')) {\n            DB::table('reports')->where('subject_type', 'profile')->update([\n                'subject_id' => DB::raw('(SELECT user_id FROM profiles WHERE profiles.id = reports.subject_id)'),\n            ]);\n        }\n\n        // Remap every FK targeting profiles to the corresponding account ID, then target users.
        foreach (Schema::getTables() as $tableInfo) {
            $table = is_array($tableInfo) ? ($tableInfo['name'] ?? null) : ($tableInfo->name ?? null);
            if (! is_string($table) || $table === 'profiles' || $table === 'users') {
                continue;
            }

            foreach (Schema::getForeignKeys($table) as $foreign) {
                if (($foreign['foreign_table'] ?? null) !== 'profiles') {
                    continue;
                }

                $columns = $foreign['columns'];
                $foreignColumns = $foreign['foreign_columns'];
                $constraintName = $foreign['name'];

                Schema::table($table, function (Blueprint $blueprint) use ($constraintName): void {
                    $blueprint->dropForeign($constraintName);
                });

                if (count($columns) === 1 && count($foreignColumns) === 1 && $foreignColumns[0] === 'id') {
                    $column = $columns[0];
                    DB::table($table)->whereNotNull($column)->update([
                        $column => DB::raw('(SELECT user_id FROM profiles WHERE profiles.id = '.$table.'.'.$column.')'),
                    ]);
                }

                Schema::table($table, function (Blueprint $blueprint) use ($columns, $foreignColumns, $constraintName, $foreign): void {
                    $constraint = $blueprint->foreign($columns, $constraintName)
                        ->references($foreignColumns)->on('users');

                    $delete = strtolower((string) ($foreign['on_delete'] ?? ''));
                    if ($delete === 'cascade') {
                        $constraint->cascadeOnDelete();
                    } elseif ($delete === 'set null') {
                        $constraint->nullOnDelete();
                    } elseif ($delete === 'restrict') {
                        $constraint->restrictOnDelete();
                    }
                });
            }
        }

        Schema::drop('profiles');
    }

    public function down(): void
    {
        throw new RuntimeException('The users/profile consolidation is intentionally irreversible.');
    }
};
