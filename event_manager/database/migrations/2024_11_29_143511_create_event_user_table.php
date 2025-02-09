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
        Schema::create('event_user', function (Blueprint $table) {
            $table->id();

            // Define 'user_id' without the foreign key constraint
            $table->unsignedBigInteger('user_id');

            // Include 'user_name' column without 'after' method
            $table->string('user_name');

            // Define 'event_id' with the foreign key constraint
            $table->foreignId('event_id')->constrained()->onDelete('cascade');

            $table->timestamps();

            // Add an index on 'user_id' for faster queries (optional)
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_user');
    }
};
