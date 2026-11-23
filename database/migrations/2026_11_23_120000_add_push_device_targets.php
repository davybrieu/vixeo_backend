<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How each app receives its notifications (docs/notifications.md): a Firebase registration token
 * (apps before firebase_messaging 17), a Firebase installation ID (`fid`), or the real-time channel
 * of the apps without Firebase (Windows), which has no registration to keep. Can run again after a
 * failure (MySQL keeps the ALTERs already done).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('push_devices', 'target')) {
            Schema::table('push_devices', function (Blueprint $table) {
                $table->string('target', 16)->default('token')->after('token_hash');
            });
        }

        Schema::table('push_devices', function (Blueprint $table) {
            $table->text('token')->nullable()->change();
            $table->char('token_hash', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('push_devices')->whereNull('token')->delete();

        Schema::table('push_devices', function (Blueprint $table) {
            $table->text('token')->nullable(false)->change();
            $table->char('token_hash', 64)->nullable(false)->change();
        });

        if (Schema::hasColumn('push_devices', 'target')) {
            Schema::table('push_devices', function (Blueprint $table) {
                $table->dropColumn('target');
            });
        }
    }
};
