<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifySportFieldInUsersTable extends Migration
{
    /**
     * Run the migrations to make the 'sport' field nullable.
     *
     * @return void
     */
    public function up()
    {
        // Check if the 'sport' column exists before modifying
        if (Schema::hasColumn('users', 'sport')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('sport')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations to make the 'sport' field not nullable.
     *
     * @return void
     */
    public function down()
    {
        // Check if the 'sport' column exists before modifying
        if (Schema::hasColumn('users', 'sport')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('sport')->nullable(false)->change();
            });
        }
    }
}
