<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AppRolesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('app_roles')->delete();
        
        \DB::table('app_roles')->insert(array (
            0 => 
            array (
                'created_at' => '2023-05-07 19:59:43',
                'guard_name' => 'api',
                'id' => 1,
                'name' => 'super_admin',
                'updated_at' => '2023-05-07 19:59:43',
            ),
            1 => 
            array (
                'created_at' => '2023-05-07 19:59:43',
                'guard_name' => 'api',
                'id' => 2,
                'name' => 'store_member',
                'updated_at' => '2023-05-07 19:59:43',
            ),
            2 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 3,
                'name' => 'store_owner',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            3 => 
            array (
                'created_at' => '2023-07-05 19:30:27',
                'guard_name' => 'api',
                'id' => 4,
                'name' => 'developer',
                'updated_at' => '2023-07-05 19:30:27',
            ),
            4 => 
            array (
                'created_at' => '2023-08-09 15:05:33',
                'guard_name' => 'api',
                'id' => 5,
                'name' => 'admin',
                'updated_at' => '2023-08-09 15:05:33',
            ),
            5 => 
            array (
                'created_at' => '2024-08-14 01:21:04',
                'guard_name' => 'api',
                'id' => 6,
                'name' => 'support',
                'updated_at' => '2024-08-14 01:21:04',
            ),
        ));
        
        
    }
}