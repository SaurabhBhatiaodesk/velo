<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PlansTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('plans')->delete();
        
        \DB::table('plans')->insert(array (
            0 => 
            array (
                'id' => 1,
                'is_public' => 0,
                'name' => 'subscription',
            ),
            1 => 
            array (
                'id' => 2,
                'is_public' => 0,
                'name' => 'perUsage',
            ),
            2 => 
            array (
                'id' => 3,
                'is_public' => 1,
                'name' => 'flex',
            ),
            3 => 
            array (
                'id' => 4,
                'is_public' => 1,
                'name' => 'plus',
            ),
            4 => 
            array (
                'id' => 5,
                'is_public' => 1,
                'name' => 'pro',
            ),
        ));
        
        
    }
}