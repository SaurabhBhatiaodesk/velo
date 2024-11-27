<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('products')->delete();
        
        \DB::table('products')->insert(array (
            0 => 
            array (
                'code' => 'RF3092',
                'created_at' => '2023-02-22 19:34:13',
                'id' => 1,
                'name' => 'Sport coat',
                'shopify_id' => NULL,
                'store_slug' => 'velo',
                'updated_at' => '2023-06-13 01:40:21',
            ),
            1 => 
            array (
                'code' => 'WJ3849',
                'created_at' => '2023-02-22 19:34:13',
                'id' => 2,
                'name' => 'Covering cover',
                'shopify_id' => NULL,
                'store_slug' => 'velo',
                'updated_at' => '2023-06-13 01:40:21',
            ),
            2 => 
            array (
                'code' => NULL,
                'created_at' => '2023-03-14 12:48:29',
                'id' => 15,
                'name' => 'Black T-shirt',
                'shopify_id' => '44088947605812',
                'store_slug' => 'velo',
                'updated_at' => '2023-06-13 01:40:22',
            ),
            3 => 
            array (
                'code' => '2',
                'created_at' => '2023-03-19 13:53:57',
                'id' => 18,
                'name' => 'code',
                'shopify_id' => NULL,
                'store_slug' => 'velo',
                'updated_at' => '2023-06-13 01:40:22',
            ),
            4 => 
            array (
                'code' => 'ZE11',
                'created_at' => '2023-03-24 21:42:23',
                'id' => 21,
                'name' => 'Try',
                'shopify_id' => NULL,
                'store_slug' => 'velo',
                'updated_at' => '2023-06-13 01:40:22',
            ),
            5 => 
            array (
                'code' => '123',
                'created_at' => '2023-04-03 15:55:10',
                'id' => 27,
                'name' => 'Test',
                'shopify_id' => NULL,
                'store_slug' => 'velo',
                'updated_at' => '2023-06-13 01:40:22',
            ),
            6 => 
            array (
                'code' => '33',
                'created_at' => '2023-04-04 10:49:42',
                'id' => 29,
                'name' => 'Clop',
                'shopify_id' => NULL,
                'store_slug' => 'velo',
                'updated_at' => '2023-06-13 01:40:22',
            ),
            7 => 
            array (
                'code' => 'PLSH221',
                'created_at' => '2023-04-21 00:15:27',
                'id' => 37,
                'name' => 'Polo shirt',
                'shopify_id' => NULL,
                'store_slug' => 'velo',
                'updated_at' => '2023-06-13 01:40:22',
            ),
            8 => 
            array (
                'code' => 'NIK45',
                'created_at' => '2023-04-21 00:15:27',
                'id' => 38,
                'name' => 'Running shoes',
                'shopify_id' => NULL,
                'store_slug' => 'velo',
                'updated_at' => '2023-06-13 01:40:22',
            ),
            9 => 
            array (
                'code' => '001',
                'created_at' => '2023-05-04 15:20:50',
                'id' => 43,
                'name' => 'product one',
                'shopify_id' => NULL,
                'store_slug' => 'ari',
                'updated_at' => '2023-06-13 01:40:22',
            ),
            10 => 
            array (
                'code' => '',
                'created_at' => '2023-06-14 18:13:02',
                'id' => 117,
                'name' => 'שווארמה',
                'shopify_id' => NULL,
                'store_slug' => 'velo',
                'updated_at' => '2023-06-14 18:13:02',
            ),
            11 => 
            array (
                'code' => 'VELOTSHIRTSKU',
                'created_at' => '2023-08-13 21:55:59',
                'id' => 1971,
                'name' => 'Velo T-Shirt - XL, Blue',
                'shopify_id' => NULL,
                'store_slug' => 'velo',
                'updated_at' => '2023-08-13 21:55:59',
            ),
            12 => 
            array (
                'code' => 'testl',
                'created_at' => '2023-08-17 18:29:03',
                'id' => 2058,
                'name' => 'מוצר בדיקה - L',
                'shopify_id' => NULL,
                'store_slug' => 'velo',
                'updated_at' => '2023-08-17 18:29:03',
            ),
            13 => 
            array (
                'code' => 'testm',
                'created_at' => '2023-08-17 18:29:03',
                'id' => 2059,
                'name' => 'מוצר בדיקה - M',
                'shopify_id' => NULL,
                'store_slug' => 'velo',
                'updated_at' => '2023-08-17 18:29:03',
            ),
            14 => 
            array (
                'code' => 'tests',
                'created_at' => '2023-08-17 18:29:03',
                'id' => 2060,
                'name' => 'מוצר בדיקה - S',
                'shopify_id' => NULL,
                'store_slug' => 'velo',
                'updated_at' => '2023-08-17 18:29:03',
            ),
            15 => 
            array (
                'code' => 'PRODSKU1',
                'created_at' => '2024-01-23 08:31:08',
                'id' => 3880,
                'name' => 'Blue Shirt - XL',
                'shopify_id' => NULL,
                'store_slug' => 'velo',
                'updated_at' => '2024-01-23 08:31:08',
            ),
            16 => 
            array (
                'code' => '',
                'created_at' => '2024-04-21 18:02:32',
                'id' => 7306,
                'name' => 'whatever',
                'shopify_id' => '47189269938484',
                'store_slug' => 'velo',
                'updated_at' => '2024-04-21 18:02:32',
            ),
            17 => 
            array (
                'code' => 'woo-beanie',
                'created_at' => '2024-10-20 17:38:00',
                'id' => 10477,
                'name' => 'Beanie',
                'shopify_id' => NULL,
                'store_slug' => 'velo',
                'updated_at' => '2024-10-20 17:38:00',
            ),
            18 => 
            array (
                'code' => 'woo-cap',
                'created_at' => '2024-10-20 19:24:08',
                'id' => 10479,
                'name' => 'Cap',
                'shopify_id' => NULL,
                'store_slug' => 'velo',
                'updated_at' => '2024-10-20 19:24:08',
            ),
            19 => 
            array (
                'code' => 'Woo-beanie-logo',
                'created_at' => '2024-10-27 15:29:27',
                'id' => 10549,
                'name' => 'Beanie with Logo',
                'shopify_id' => NULL,
                'store_slug' => 'velo',
                'updated_at' => '2024-10-27 15:29:27',
            ),
        ));
        
        
    }
}