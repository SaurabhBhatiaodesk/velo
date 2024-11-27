<?php

namespace Database\Seeders;
use Illuminate\Database\Seeder;
class ShopifyShopsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('shopify_shops')->delete();
        
        \DB::table('shopify_shops')->insert(array (
            0 => 
            array (
                'active' => 1,
                'created_at' => '2023-05-30 12:23:53',
                'domain' => 'ari-velo.myshopify.com',
                'email' => 'ari@veloapp.io',
                'id' => 46,
                'name' => 'Ari Velo',
                'refresh_token' => NULL,
                'shopify_id' => '76592841001',
                'store_slug' => 'ari',
                'token' => 'null',
                'updated_at' => '2023-06-28 16:19:42',
            ),
            1 => 
            array (
                'active' => 1,
                'created_at' => '2023-07-26 01:40:18',
                'domain' => 'velotest87@gmail.com.myshopify.com',
                'email' => NULL,
                'id' => 75,
                'name' => NULL,
                'refresh_token' => NULL,
                'shopify_id' => NULL,
                'store_slug' => 'velotest',
                'token' => NULL,
                'updated_at' => '2023-07-27 00:47:01',
            ),
            2 => 
            array (
                'active' => 1,
                'created_at' => '2024-09-02 00:08:51',
                'domain' => 'velo-originals.myshopify.com',
                'email' => 'itay@veloapp.io',
                'id' => 176,
                'name' => 'Velo Originals',
                'refresh_token' => NULL,
                'shopify_id' => '69408391476',
                'store_slug' => 'velo',
                'token' => 'null',
                'updated_at' => '2024-09-02 00:08:51',
            ),
        ));
        
        
    }
}