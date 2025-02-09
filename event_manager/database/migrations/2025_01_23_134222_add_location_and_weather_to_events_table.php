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
        Schema::table('events', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable(); // Latitude do evento
            $table->decimal('longitude', 10, 7)->nullable(); // Longitude do evento
            $table->json('weather')->nullable(); // Dados meteorológicos em formato JSON
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('latitude'); // Remove latitude
            $table->dropColumn('longitude'); // Remove longitude
            $table->dropColumn('weather'); // Remove coluna de meteorologia
        });
    }
};
