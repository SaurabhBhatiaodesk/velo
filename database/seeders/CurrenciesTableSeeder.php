<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CurrenciesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('currencies')->delete();
        
        \DB::table('currencies')->insert(array (
            0 => 
            array (
                'id' => 1,
                'iso' => 'USD',
                'symbol' => '$',
            ),
            1 => 
            array (
                'id' => 2,
                'iso' => 'ILS',
                'symbol' => '₪',
            ),
        ));
        
        
    }
}