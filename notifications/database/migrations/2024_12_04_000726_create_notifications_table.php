<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    // Migration for notifications table
Schema::create('notifications', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedBigInteger('event_id');
    $table->string('event_name');
    $table->string('message');
    $table->timestamps();
});

// Pivot table to link notifications to users
Schema::create('event_notification', function (Blueprint $table) {
    $table->bigIncrements('id');
    $table->unsignedBigInteger('notification_id');
    $table->unsignedBigInteger('user_id');
    $table->timestamps();
});

}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
