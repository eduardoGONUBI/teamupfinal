<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFailedMessagesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('failed_messages', function (Blueprint $table) {
            $table->id();
            $table->string('queue_name');
            $table->text('message_body');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamps(); // Includes created_at and updated_at columns
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('failed_messages');
    }
}
