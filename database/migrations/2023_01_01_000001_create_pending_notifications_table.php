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
        Schema::create('pending_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // We might not need notification_subscription_id if we store type and channel, makes it more flexible for digests
            // $table->foreignId('notification_subscription_id')->constrained()->cascadeOnDelete(); 
            $table->string('notification_type')->index(); // e.g., 'comment:created'
            $table->string('channel'); // e.g., 'mail', 'database'
            $table->string('notification_class'); // The FQCN of the original notification
            $table->json('notification_data'); // Stores constructor args or payload
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_notifications');
    }
}; 
