<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SportsTableSeeder extends Seeder
{
    public function run()
    {
        DB::table('sports')->insert([
            ['name' => 'Futebol'],
            ['name' => 'Futsal'],
            ['name' => 'Ciclismo'],
            ['name' => 'Surf'],
            ['name' => 'Voleibol'],
            ['name' => 'Basquetebol'],
            ['name' => 'Ténis'],
            ['name' => 'Andebol'],
        ]);
    }
}
