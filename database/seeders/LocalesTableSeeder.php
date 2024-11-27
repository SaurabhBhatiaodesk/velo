<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class LocalesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('locales')->delete();
        
        \DB::table('locales')->insert(array (
            0 => 
            array (
                'country' => NULL,
                'created_at' => '2023-03-17 02:49:49',
                'id' => 1,
                'ietf' => 'en',
                'iso' => 'en_US',
                'regex_identifier' => '/^[a-zA-Z 0-9_\'-]+$/i',
                'state' => NULL,
                'updated_at' => '2024-03-31 15:29:03',
            ),
            1 => 
            array (
                'country' => 'Israel,IL',
                'created_at' => '2023-03-17 02:49:49',
                'id' => 2,
                'ietf' => 'iw',
                'iso' => 'he',
                'regex_identifier' => '/^[א-ת 0-9_\'-]+$/i',
                'state' => NULL,
                'updated_at' => '2024-03-31 15:25:03',
            ),
        ));
        
        
    }
}