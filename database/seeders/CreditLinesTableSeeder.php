<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CreditLinesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('credit_lines')->delete();
        
        \DB::table('credit_lines')->insert(array (
            0 => 
            array (
                'created_at' => '2023-08-02 20:28:39',
                'creditable_id' => 964,
                'creditable_type' => 'App\\Models\\Delivery',
                'currency_id' => 2,
                'description' => 'Credit for delivery Vari8',
                'id' => 81,
                'store_slug' => 'ari',
                'total' => 33.93,
                'transaction_id' => 411,
                'updated_at' => '2023-09-01 09:00:25',
            ),
            1 => 
            array (
                'created_at' => '2023-08-02 20:28:39',
                'creditable_id' => 965,
                'creditable_type' => 'App\\Models\\Delivery',
                'currency_id' => 2,
                'description' => 'Credit for delivery Vari9',
                'id' => 82,
                'store_slug' => 'ari',
                'total' => 33.93,
                'transaction_id' => 762,
                'updated_at' => '2023-12-13 08:40:13',
            ),
            2 => 
            array (
                'created_at' => '2023-08-02 20:28:39',
                'creditable_id' => 966,
                'creditable_type' => 'App\\Models\\Delivery',
                'currency_id' => 2,
                'description' => 'Credit for delivery Vari10',
                'id' => 83,
                'store_slug' => 'ari',
                'total' => 33.93,
                'transaction_id' => 762,
                'updated_at' => '2023-12-13 08:40:13',
            ),
            3 => 
            array (
                'created_at' => '2023-08-02 20:28:39',
                'creditable_id' => 1038,
                'creditable_type' => 'App\\Models\\Delivery',
                'currency_id' => 2,
                'description' => 'Credit for delivery Vari11',
                'id' => 93,
                'store_slug' => 'ari',
                'total' => 33.93,
                'transaction_id' => 762,
                'updated_at' => '2023-12-13 08:40:13',
            ),
            4 => 
            array (
                'created_at' => '2023-08-02 20:28:42',
                'creditable_id' => 1535,
                'creditable_type' => 'App\\Models\\Delivery',
                'currency_id' => 2,
                'description' => 'Credit for delivery Vari12',
                'id' => 234,
                'store_slug' => 'ari',
                'total' => 33.93,
                'transaction_id' => 762,
                'updated_at' => '2023-12-13 08:40:13',
            ),
            5 => 
            array (
                'created_at' => '2023-08-02 20:28:42',
                'creditable_id' => 1894,
                'creditable_type' => 'App\\Models\\Delivery',
                'currency_id' => 2,
                'description' => 'Credit for delivery Vari13',
                'id' => 272,
                'store_slug' => 'ari',
                'total' => 33.93,
                'transaction_id' => 762,
                'updated_at' => '2023-12-13 08:40:13',
            ),
            6 => 
            array (
                'created_at' => '2023-08-02 20:28:42',
                'creditable_id' => 1938,
                'creditable_type' => 'App\\Models\\Delivery',
                'currency_id' => 2,
                'description' => 'Credit for delivery Vari14',
                'id' => 275,
                'store_slug' => 'ari',
                'total' => 33.93,
                'transaction_id' => 762,
                'updated_at' => '2023-12-13 08:40:13',
            ),
        ));
        
        
    }
}