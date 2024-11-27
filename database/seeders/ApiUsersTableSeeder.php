<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ApiUsersTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('api_users')->delete();
        
        \DB::table('api_users')->insert(array (
            0 => 
            array (
                'active' => 1,
                'created_at' => NULL,
                'id' => 4,
                'key' => 'B28AC8C77E93F3AE6EFAF2FA293F5',
                'nonce' => NULL,
                'secret' => 'FB8B9A58443F33C641EA2E5A88223',
                'settings' => NULL,
                'slug' => 'enterprise',
                'store_slug' => 'velo',
                'updated_at' => '2023-06-13 01:40:02',
            ),
            1 => 
            array (
                'active' => 1,
                'created_at' => '2023-06-06 03:14:07',
                'id' => 6,
                'key' => 'mrOd5LmJPwCxiRcVWtB8',
                'nonce' => NULL,
                'secret' => 'IcstNpkT33w4BWrKda8t',
                'settings' => NULL,
                'slug' => 'wp',
                'store_slug' => 'velo',
                'updated_at' => '2023-06-13 01:40:02',
            ),
            2 => 
            array (
                'active' => 0,
                'created_at' => '2023-06-13 14:25:00',
                'id' => 10,
                'key' => 'KzoWByjyoqFGF1dGCQUN',
                'nonce' => NULL,
                'secret' => '0xSSz9LtwueYJSHb1d7R',
                'settings' => NULL,
                'slug' => 'wp',
                'store_slug' => 'ari',
                'updated_at' => '2023-06-13 14:25:00',
            ),
            3 => 
            array (
                'active' => 0,
                'created_at' => '2023-10-01 09:57:09',
                'id' => 30,
                'key' => '1iNsEBM6vaCXU4Li4zwS',
                'nonce' => NULL,
                'secret' => 'n0IEvWQMYhJWfskXDYtg',
                'settings' => NULL,
                'slug' => 'wp',
                'store_slug' => 'velo-qa',
                'updated_at' => '2023-10-01 11:20:10',
            ),
            4 => 
            array (
                'active' => 1,
                'created_at' => '2023-10-03 23:44:08',
                'id' => 31,
                'key' => 'eQy0j69bslg7lRfngy4L',
                'nonce' => NULL,
                'secret' => 'ubjspsyqRt86GbB2Do8Y',
                'settings' => '{"charge": 0, "returnRate": 30, "replacementRate": 50, "returnsPolicyUrl": "https://testurl.com"}',
                'slug' => 'venti',
                'store_slug' => 'velo',
                'updated_at' => '2024-09-03 20:42:17',
            ),
        ));
        
        
    }
}