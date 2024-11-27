<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AppModelHasRolesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('app_model_has_roles')->delete();
        
        \DB::table('app_model_has_roles')->insert(array (
            0 => 
            array (
                'model_id' => 1,
                'model_type' => 'App\\Models\\User',
                'role_id' => 1,
            ),
            1 => 
            array (
                'model_id' => 1,
                'model_type' => 'App\\Models\\User',
                'role_id' => 2,
            ),
            2 => 
            array (
                'model_id' => 1,
                'model_type' => 'App\\Models\\User',
                'role_id' => 3,
            ),
            3 => 
            array (
                'model_id' => 1,
                'model_type' => 'App\\Models\\User',
                'role_id' => 4,
            ),
            4 => 
            array (
                'model_id' => 2,
                'model_type' => 'App\\Models\\User',
                'role_id' => 2,
            ),
            5 => 
            array (
                'model_id' => 28,
                'model_type' => 'App\\Models\\User',
                'role_id' => 1,
            ),
            6 => 
            array (
                'model_id' => 28,
                'model_type' => 'App\\Models\\User',
                'role_id' => 2,
            ),
            7 => 
            array (
                'model_id' => 28,
                'model_type' => 'App\\Models\\User',
                'role_id' => 3,
            ),
            8 => 
            array (
                'model_id' => 28,
                'model_type' => 'App\\Models\\User',
                'role_id' => 5,
            ),
            9 => 
            array (
                'model_id' => 127,
                'model_type' => 'App\\Models\\User',
                'role_id' => 6,
            ),
            10 => 
            array (
                'model_id' => 146,
                'model_type' => 'App\\Models\\User',
                'role_id' => 2,
            ),
            11 => 
            array (
                'model_id' => 146,
                'model_type' => 'App\\Models\\User',
                'role_id' => 3,
            ),
            12 => 
            array (
                'model_id' => 146,
                'model_type' => 'App\\Models\\User',
                'role_id' => 4,
            ),
            13 => 
            array (
                'model_id' => 181,
                'model_type' => 'App\\Models\\User',
                'role_id' => 2,
            ),
            14 => 
            array (
                'model_id' => 181,
                'model_type' => 'App\\Models\\User',
                'role_id' => 3,
            ),
            15 => 
            array (
                'model_id' => 181,
                'model_type' => 'App\\Models\\User',
                'role_id' => 4,
            ),
            16 => 
            array (
                'model_id' => 181,
                'model_type' => 'App\\Models\\User',
                'role_id' => 5,
            ),
            17 => 
            array (
                'model_id' => 430,
                'model_type' => 'App\\Models\\User',
                'role_id' => 3,
            ),
            18 => 
            array (
                'model_id' => 430,
                'model_type' => 'App\\Models\\User',
                'role_id' => 4,
            ),
            19 => 
            array (
                'model_id' => 430,
                'model_type' => 'App\\Models\\User',
                'role_id' => 5,
            ),
            20 => 
            array (
                'model_id' => 484,
                'model_type' => 'App\\Models\\User',
                'role_id' => 2,
            ),
            21 => 
            array (
                'model_id' => 484,
                'model_type' => 'App\\Models\\User',
                'role_id' => 4,
            ),
        ));
        
        
    }
}