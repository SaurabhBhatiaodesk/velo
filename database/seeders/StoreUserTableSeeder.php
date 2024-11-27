<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class StoreUserTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('store_user')->delete();
        
        \DB::table('store_user')->insert(array (
            0 => 
            array (
                'address_id' => NULL,
                'invited_at' => '2024-05-15 16:34:51',
                'joined_at' => '2024-05-15 16:42:43',
                'store_role' => NULL,
                'store_slug' => 'velo',
                'token' => NULL,
                'user_id' => 573,
            ),
        ));
        
        
    }
}