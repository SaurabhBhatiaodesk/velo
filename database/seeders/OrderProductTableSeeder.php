<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class OrderProductTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('order_product')->delete();
        
        \DB::table('order_product')->insert(array (
            0 => 
            array (
                'created_at' => NULL,
                'id' => 452,
                'image' => NULL,
                'order_id' => 458,
                'product_id' => 117,
                'quantity' => 1.0,
                'total' => 50.0,
                'updated_at' => NULL,
                'variation' => '',
            ),
            1 => 
            array (
                'created_at' => NULL,
                'id' => 454,
                'image' => NULL,
                'order_id' => 460,
                'product_id' => 21,
                'quantity' => 1.0,
                'total' => 2388.0,
                'updated_at' => NULL,
                'variation' => NULL,
            ),
            2 => 
            array (
                'created_at' => NULL,
                'id' => 495,
                'image' => NULL,
                'order_id' => 530,
                'product_id' => 15,
                'quantity' => 6.0,
                'total' => 300.0,
                'updated_at' => NULL,
                'variation' => '',
            ),
            3 => 
            array (
                'created_at' => NULL,
                'id' => 496,
                'image' => NULL,
                'order_id' => 554,
                'product_id' => 37,
                'quantity' => 1.0,
                'total' => 89.99,
                'updated_at' => NULL,
                'variation' => 'XL / Blue',
            ),
            4 => 
            array (
                'created_at' => NULL,
                'id' => 497,
                'image' => NULL,
                'order_id' => 554,
                'product_id' => 38,
                'quantity' => 1.0,
                'total' => 399.99,
                'updated_at' => NULL,
                'variation' => '45',
            ),
            5 => 
            array (
                'created_at' => NULL,
                'id' => 2785,
                'image' => NULL,
                'order_id' => 3268,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Blue',
            ),
            6 => 
            array (
                'created_at' => NULL,
                'id' => 2786,
                'image' => NULL,
                'order_id' => 3269,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Blue',
            ),
            7 => 
            array (
                'created_at' => NULL,
                'id' => 2787,
                'image' => NULL,
                'order_id' => 3268,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Blue',
            ),
            8 => 
            array (
                'created_at' => NULL,
                'id' => 2788,
                'image' => NULL,
                'order_id' => 3268,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Blue',
            ),
            9 => 
            array (
                'created_at' => NULL,
                'id' => 2789,
                'image' => NULL,
                'order_id' => 3268,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Blue',
            ),
            10 => 
            array (
                'created_at' => NULL,
                'id' => 2790,
                'image' => NULL,
                'order_id' => 3268,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Blue',
            ),
            11 => 
            array (
                'created_at' => NULL,
                'id' => 2791,
                'image' => NULL,
                'order_id' => 3271,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Blue',
            ),
            12 => 
            array (
                'created_at' => NULL,
                'id' => 2792,
                'image' => NULL,
                'order_id' => 3271,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Blue',
            ),
            13 => 
            array (
                'created_at' => NULL,
                'id' => 2793,
                'image' => NULL,
                'order_id' => 3268,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Blue',
            ),
            14 => 
            array (
                'created_at' => NULL,
                'id' => 2794,
                'image' => NULL,
                'order_id' => 3271,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Blue',
            ),
            15 => 
            array (
                'created_at' => NULL,
                'id' => 2821,
                'image' => NULL,
                'order_id' => 3271,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Blue',
            ),
            16 => 
            array (
                'created_at' => NULL,
                'id' => 2822,
                'image' => NULL,
                'order_id' => 3333,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Red',
            ),
            17 => 
            array (
                'created_at' => NULL,
                'id' => 2823,
                'image' => NULL,
                'order_id' => 3271,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Blue',
            ),
            18 => 
            array (
                'created_at' => NULL,
                'id' => 2824,
                'image' => NULL,
                'order_id' => 3271,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Blue',
            ),
            19 => 
            array (
                'created_at' => NULL,
                'id' => 2825,
                'image' => NULL,
                'order_id' => 3333,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Red',
            ),
            20 => 
            array (
                'created_at' => NULL,
                'id' => 2826,
                'image' => NULL,
                'order_id' => 3271,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Blue',
            ),
            21 => 
            array (
                'created_at' => NULL,
                'id' => 2827,
                'image' => NULL,
                'order_id' => 3271,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Blue',
            ),
            22 => 
            array (
                'created_at' => NULL,
                'id' => 2828,
                'image' => NULL,
                'order_id' => 3268,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Blue',
            ),
            23 => 
            array (
                'created_at' => NULL,
                'id' => 2829,
                'image' => NULL,
                'order_id' => 3333,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Red',
            ),
            24 => 
            array (
                'created_at' => NULL,
                'id' => 2830,
                'image' => NULL,
                'order_id' => 3268,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Blue',
            ),
            25 => 
            array (
                'created_at' => NULL,
                'id' => 2831,
                'image' => NULL,
                'order_id' => 3268,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Blue',
            ),
            26 => 
            array (
                'created_at' => NULL,
                'id' => 2832,
                'image' => NULL,
                'order_id' => 3333,
                'product_id' => 1971,
                'quantity' => 1.0,
                'total' => 25.0,
                'updated_at' => NULL,
                'variation' => 'Velo T-Shirt - XL, Red',
            ),
            27 => 
            array (
                'created_at' => NULL,
                'id' => 11890,
                'image' => NULL,
                'order_id' => 39925,
                'product_id' => 15,
                'quantity' => 4.0,
                'total' => 200.0,
                'updated_at' => NULL,
                'variation' => '',
            ),
            28 => 
            array (
                'created_at' => NULL,
                'id' => 15006,
                'image' => NULL,
                'order_id' => 44665,
                'product_id' => 7306,
                'quantity' => 1.0,
                'total' => 33.0,
                'updated_at' => NULL,
                'variation' => '',
            ),
            29 => 
            array (
                'created_at' => NULL,
                'id' => 15007,
                'image' => NULL,
                'order_id' => 44665,
                'product_id' => 1,
                'quantity' => 7.0,
                'total' => 3885.0,
                'updated_at' => NULL,
                'variation' => 'בדיקת פריט',
            ),
            30 => 
            array (
                'created_at' => NULL,
                'id' => 15031,
                'image' => NULL,
                'order_id' => 44669,
                'product_id' => 7306,
                'quantity' => 2.0,
                'total' => 66.0,
                'updated_at' => NULL,
                'variation' => '',
            ),
            31 => 
            array (
                'created_at' => NULL,
                'id' => 22879,
                'image' => NULL,
                'order_id' => 65866,
                'product_id' => 37,
                'quantity' => 1.0,
                'total' => 89.99,
                'updated_at' => NULL,
                'variation' => 'XL / Blue',
            ),
            32 => 
            array (
                'created_at' => NULL,
                'id' => 22880,
                'image' => NULL,
                'order_id' => 65866,
                'product_id' => 38,
                'quantity' => 1.0,
                'total' => 399.99,
                'updated_at' => NULL,
                'variation' => '45',
            ),
            33 => 
            array (
                'created_at' => NULL,
                'id' => 22881,
                'image' => NULL,
                'order_id' => 65867,
                'product_id' => 37,
                'quantity' => 1.0,
                'total' => 89.99,
                'updated_at' => NULL,
                'variation' => 'XL / Blue',
            ),
            34 => 
            array (
                'created_at' => NULL,
                'id' => 22882,
                'image' => NULL,
                'order_id' => 65867,
                'product_id' => 38,
                'quantity' => 1.0,
                'total' => 399.99,
                'updated_at' => NULL,
                'variation' => '45',
            ),
            35 => 
            array (
                'created_at' => NULL,
                'id' => 22883,
                'image' => NULL,
                'order_id' => 65869,
                'product_id' => 37,
                'quantity' => 1.0,
                'total' => 89.99,
                'updated_at' => NULL,
                'variation' => 'XL / Blue',
            ),
            36 => 
            array (
                'created_at' => NULL,
                'id' => 22884,
                'image' => NULL,
                'order_id' => 65869,
                'product_id' => 38,
                'quantity' => 1.0,
                'total' => 399.99,
                'updated_at' => NULL,
                'variation' => '45',
            ),
            37 => 
            array (
                'created_at' => NULL,
                'id' => 22885,
                'image' => NULL,
                'order_id' => 65871,
                'product_id' => 37,
                'quantity' => 1.0,
                'total' => 89.99,
                'updated_at' => NULL,
                'variation' => 'XL / Blue',
            ),
            38 => 
            array (
                'created_at' => NULL,
                'id' => 22886,
                'image' => NULL,
                'order_id' => 65871,
                'product_id' => 38,
                'quantity' => 1.0,
                'total' => 399.99,
                'updated_at' => NULL,
                'variation' => '45',
            ),
            39 => 
            array (
                'created_at' => NULL,
                'id' => 24080,
                'image' => NULL,
                'order_id' => 69422,
                'product_id' => 10477,
                'quantity' => 1.0,
                'total' => 18.0,
                'updated_at' => NULL,
                'variation' => '',
            ),
            40 => 
            array (
                'created_at' => NULL,
                'id' => 24086,
                'image' => NULL,
                'order_id' => 69434,
                'product_id' => 10479,
                'quantity' => 1.0,
                'total' => 16.0,
                'updated_at' => NULL,
                'variation' => '',
            ),
            41 => 
            array (
                'created_at' => NULL,
                'id' => 24435,
                'image' => NULL,
                'order_id' => 70234,
                'product_id' => 10549,
                'quantity' => 1.0,
                'total' => 18.0,
                'updated_at' => NULL,
                'variation' => '',
            ),
        ));
        
        
    }
}