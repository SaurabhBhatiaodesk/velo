<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SupportSystemsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('support_systems')->delete();
        
        \DB::table('support_systems')->insert(array (
            0 => 
            array (
                'created_at' => '2024-08-01 01:47:38',
                'id' => 1,
                'key' => '7SxphO0LUZ1pLEhrw7Zd',
                'name' => 'zendesk_dev',
                'secret' => 'd4aKF4gLFt0QzPa6oHOe',
                'updated_at' => '2024-09-27 01:15:41',
            ),
            1 => 
            array (
                'created_at' => '2024-09-27 01:18:59',
                'id' => 2,
                'key' => '7Avpxt3L4Z3qLzGrh7RE',
                'name' => 'zendesk',
                'secret' => 'do2TMD5gV6uclvFJYz4k',
                'updated_at' => '2024-09-27 01:18:59',
            ),
        ));
        
        
    }
}