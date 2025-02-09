<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('sport_id'); // Reference to the sports table
            $table->foreign('sport_id')->references('id')->on('sports')->onDelete('cascade'); // Add foreign key constraint
            $table->date('date');
            $table->string('place');
            $table->string('status')->default('in progress'); 
            $table->integer('max_participants')->nullable(); 
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
