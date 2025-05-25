<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notification_subscriptions', function (Blueprint $table) {
            $table->string('digest_interval')->default('immediate')->after('channel');
            $table->time('digest_at_time')->nullable()->after('digest_interval');
            $table->string('digest_at_day')->nullable()->after('digest_at_time'); // e.g., 'monday', 'tuesday'
            $table->timestamp('last_digest_sent_at')->nullable()->after('digest_at_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['digest_interval', 'digest_at_time', 'digest_at_day', 'last_digest_sent_at']);
        });
    }
}; 
