<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AppPermissionsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('app_permissions')->delete();
        
        \DB::table('app_permissions')->insert(array (
            0 => 
            array (
                'created_at' => '2023-05-07 19:59:43',
                'guard_name' => 'api',
                'id' => 1,
                'name' => 'view addresses',
                'updated_at' => '2023-05-07 19:59:43',
            ),
            1 => 
            array (
                'created_at' => '2023-05-07 19:59:43',
                'guard_name' => 'api',
                'id' => 2,
                'name' => 'view api_users',
                'updated_at' => '2023-05-07 19:59:43',
            ),
            2 => 
            array (
                'created_at' => '2023-05-07 19:59:43',
                'guard_name' => 'api',
                'id' => 3,
                'name' => 'view bills',
                'updated_at' => '2023-05-07 19:59:43',
            ),
            3 => 
            array (
                'created_at' => '2023-05-07 19:59:43',
                'guard_name' => 'api',
                'id' => 4,
                'name' => 'view customers',
                'updated_at' => '2023-05-07 19:59:43',
            ),
            4 => 
            array (
                'created_at' => '2023-05-07 19:59:43',
                'guard_name' => 'api',
                'id' => 5,
                'name' => 'view deliveries',
                'updated_at' => '2023-05-07 19:59:43',
            ),
            5 => 
            array (
                'created_at' => '2023-05-07 19:59:43',
                'guard_name' => 'api',
                'id' => 6,
                'name' => 'view notes',
                'updated_at' => '2023-05-07 19:59:43',
            ),
            6 => 
            array (
                'created_at' => '2023-05-07 19:59:43',
                'guard_name' => 'api',
                'id' => 7,
                'name' => 'view orders',
                'updated_at' => '2023-05-07 19:59:43',
            ),
            7 => 
            array (
                'created_at' => '2023-05-07 19:59:43',
                'guard_name' => 'api',
                'id' => 8,
                'name' => 'view payment_methods',
                'updated_at' => '2023-05-07 19:59:43',
            ),
            8 => 
            array (
                'created_at' => '2023-05-07 19:59:43',
                'guard_name' => 'api',
                'id' => 9,
                'name' => 'view products',
                'updated_at' => '2023-05-07 19:59:43',
            ),
            9 => 
            array (
                'created_at' => '2023-05-07 19:59:43',
                'guard_name' => 'api',
                'id' => 10,
                'name' => 'view prices',
                'updated_at' => '2023-05-07 19:59:43',
            ),
            10 => 
            array (
                'created_at' => '2023-05-07 19:59:43',
                'guard_name' => 'api',
                'id' => 11,
                'name' => 'view store',
                'updated_at' => '2023-05-07 19:59:43',
            ),
            11 => 
            array (
                'created_at' => '2023-05-07 19:59:43',
                'guard_name' => 'api',
                'id' => 12,
                'name' => 'view subscriptions',
                'updated_at' => '2023-05-07 19:59:43',
            ),
            12 => 
            array (
                'created_at' => '2023-05-07 19:59:43',
                'guard_name' => 'api',
                'id' => 13,
                'name' => 'view users',
                'updated_at' => '2023-05-07 19:59:43',
            ),
            13 => 
            array (
                'created_at' => '2023-05-07 19:59:43',
                'guard_name' => 'api',
                'id' => 14,
                'name' => 'create addresses',
                'updated_at' => '2023-05-07 19:59:43',
            ),
            14 => 
            array (
                'created_at' => '2023-05-07 19:59:43',
                'guard_name' => 'api',
                'id' => 15,
                'name' => 'create customers',
                'updated_at' => '2023-05-07 19:59:43',
            ),
            15 => 
            array (
                'created_at' => '2023-05-07 19:59:43',
                'guard_name' => 'api',
                'id' => 16,
                'name' => 'create orders',
                'updated_at' => '2023-05-07 19:59:43',
            ),
            16 => 
            array (
                'created_at' => '2023-05-07 19:59:43',
                'guard_name' => 'api',
                'id' => 17,
                'name' => 'create products',
                'updated_at' => '2023-05-07 19:59:43',
            ),
            17 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 18,
                'name' => 'create prices',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            18 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 19,
                'name' => 'update addresses',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            19 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 20,
                'name' => 'update customers',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            20 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 21,
                'name' => 'update orders',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            21 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 22,
                'name' => 'update products',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            22 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 23,
                'name' => 'update prices',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            23 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 24,
                'name' => 'delete addresses',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            24 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 25,
                'name' => 'delete customers',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            25 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 26,
                'name' => 'delete products',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            26 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 27,
                'name' => 'create api_users',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            27 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 28,
                'name' => 'create bills',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            28 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 29,
                'name' => 'create deliveries',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            29 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 30,
                'name' => 'create notes',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            30 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 31,
                'name' => 'create payment_methods',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            31 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 32,
                'name' => 'create shopify_shops',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            32 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 33,
                'name' => 'create store',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            33 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 34,
                'name' => 'create subscriptions',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            34 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 35,
                'name' => 'create users',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            35 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 36,
                'name' => 'update deliveries',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            36 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 37,
                'name' => 'update notes',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            37 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 38,
                'name' => 'update payment_methods',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            38 => 
            array (
                'created_at' => '2023-05-07 19:59:44',
                'guard_name' => 'api',
                'id' => 39,
                'name' => 'update shopify_shops',
                'updated_at' => '2023-05-07 19:59:44',
            ),
            39 => 
            array (
                'created_at' => '2023-05-07 19:59:45',
                'guard_name' => 'api',
                'id' => 40,
                'name' => 'update store',
                'updated_at' => '2023-05-07 19:59:45',
            ),
            40 => 
            array (
                'created_at' => '2023-05-07 19:59:45',
                'guard_name' => 'api',
                'id' => 41,
                'name' => 'update users',
                'updated_at' => '2023-05-07 19:59:45',
            ),
            41 => 
            array (
                'created_at' => '2023-05-07 19:59:45',
                'guard_name' => 'api',
                'id' => 42,
                'name' => 'delete api_users',
                'updated_at' => '2023-05-07 19:59:45',
            ),
            42 => 
            array (
                'created_at' => '2023-05-07 19:59:45',
                'guard_name' => 'api',
                'id' => 43,
                'name' => 'delete notes',
                'updated_at' => '2023-05-07 19:59:45',
            ),
            43 => 
            array (
                'created_at' => '2023-05-07 19:59:45',
                'guard_name' => 'api',
                'id' => 44,
                'name' => 'delete payment_methods',
                'updated_at' => '2023-05-07 19:59:45',
            ),
            44 => 
            array (
                'created_at' => '2023-05-07 19:59:45',
                'guard_name' => 'api',
                'id' => 45,
                'name' => 'delete shopify_shops',
                'updated_at' => '2023-05-07 19:59:45',
            ),
            45 => 
            array (
                'created_at' => '2023-05-07 19:59:45',
                'guard_name' => 'api',
                'id' => 46,
                'name' => 'delete store',
                'updated_at' => '2023-05-07 19:59:45',
            ),
            46 => 
            array (
                'created_at' => '2024-08-14 01:21:04',
                'guard_name' => 'api',
                'id' => 47,
                'name' => 'read addresses',
                'updated_at' => '2024-08-14 01:21:04',
            ),
            47 => 
            array (
                'created_at' => '2024-08-14 01:21:04',
                'guard_name' => 'api',
                'id' => 48,
                'name' => 'read api_users',
                'updated_at' => '2024-08-14 01:21:04',
            ),
            48 => 
            array (
                'created_at' => '2024-08-14 01:21:04',
                'guard_name' => 'api',
                'id' => 49,
                'name' => 'read bills',
                'updated_at' => '2024-08-14 01:21:04',
            ),
            49 => 
            array (
                'created_at' => '2024-08-14 01:21:04',
                'guard_name' => 'api',
                'id' => 50,
                'name' => 'read customers',
                'updated_at' => '2024-08-14 01:21:04',
            ),
            50 => 
            array (
                'created_at' => '2024-08-14 01:21:04',
                'guard_name' => 'api',
                'id' => 51,
                'name' => 'read deliveries',
                'updated_at' => '2024-08-14 01:21:04',
            ),
            51 => 
            array (
                'created_at' => '2024-08-14 01:21:04',
                'guard_name' => 'api',
                'id' => 52,
                'name' => 'read notes',
                'updated_at' => '2024-08-14 01:21:04',
            ),
            52 => 
            array (
                'created_at' => '2024-08-14 01:21:04',
                'guard_name' => 'api',
                'id' => 53,
                'name' => 'read orders',
                'updated_at' => '2024-08-14 01:21:04',
            ),
            53 => 
            array (
                'created_at' => '2024-08-14 01:21:04',
                'guard_name' => 'api',
                'id' => 54,
                'name' => 'read payment_methods',
                'updated_at' => '2024-08-14 01:21:04',
            ),
            54 => 
            array (
                'created_at' => '2024-08-14 01:21:04',
                'guard_name' => 'api',
                'id' => 55,
                'name' => 'read products',
                'updated_at' => '2024-08-14 01:21:04',
            ),
            55 => 
            array (
                'created_at' => '2024-08-14 01:21:04',
                'guard_name' => 'api',
                'id' => 56,
                'name' => 'read prices',
                'updated_at' => '2024-08-14 01:21:04',
            ),
            56 => 
            array (
                'created_at' => '2024-08-14 01:21:04',
                'guard_name' => 'api',
                'id' => 57,
                'name' => 'read store',
                'updated_at' => '2024-08-14 01:21:04',
            ),
            57 => 
            array (
                'created_at' => '2024-08-14 01:21:04',
                'guard_name' => 'api',
                'id' => 58,
                'name' => 'read subscriptions',
                'updated_at' => '2024-08-14 01:21:04',
            ),
            58 => 
            array (
                'created_at' => '2024-08-14 01:21:04',
                'guard_name' => 'api',
                'id' => 59,
                'name' => 'read users',
                'updated_at' => '2024-08-14 01:21:04',
            ),
        ));
        
        
    }
}