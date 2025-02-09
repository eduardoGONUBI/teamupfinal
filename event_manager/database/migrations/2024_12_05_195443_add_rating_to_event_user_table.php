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
        Schema::table('event_user', function (Blueprint $table) {
            $table->unsignedTinyInteger('rating')->nullable()->after('user_name')->comment('Rating for the user (1-5)');
        });
    }
    
    public function down()
    {
        Schema::table('event_user', function (Blueprint $table) {
            $table->dropColumn('rating');
        });
    }
    
};
