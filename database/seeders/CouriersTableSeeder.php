<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CouriersTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('couriers')->delete();
        
        \DB::table('couriers')->insert(array (
            0 => 
            array (
                'api' => 'yango',
                'barcode_format' => NULL,
                'created_at' => '2023-02-22 19:34:13',
                'id' => 1,
                'key' => NULL,
                'locale_id' => 2,
                'name' => 'Yango',
                'secret' => NULL,
                'updated_at' => '2023-03-23 20:19:58',
            ),
            1 => 
            array (
                'api' => 'baldar:danino',
                'barcode_format' => NULL,
                'created_at' => '2023-03-13 23:11:00',
                'id' => 2,
                'key' => NULL,
                'locale_id' => 2,
                'name' => 'IDD',
                'secret' => NULL,
                'updated_at' => '2023-07-18 05:09:43',
            ),
            2 => 
            array (
                'api' => 'doordash',
                'barcode_format' => NULL,
                'created_at' => '2023-04-17 12:26:00',
                'id' => 3,
                'key' => NULL,
                'locale_id' => 1,
                'name' => 'DoorDash',
                'secret' => NULL,
                'updated_at' => '2023-04-17 09:26:46',
            ),
            3 => 
            array (
                'api' => 'done',
                'barcode_format' => NULL,
                'created_at' => '2023-06-14 21:47:39',
                'id' => 4,
                'key' => 'ZlyIIxrbf1VyAHjY1AbM',
                'locale_id' => 2,
                'name' => 'done',
                'secret' => 'XxxWLHvQVy6vuziUCUIh',
                'updated_at' => '2023-06-14 21:47:39',
            ),
            4 => 
            array (
                'api' => 'baldar:dsd',
                'barcode_format' => NULL,
                'created_at' => '2023-06-20 01:58:16',
                'id' => 5,
                'key' => NULL,
                'locale_id' => 2,
                'name' => 'dsd',
                'secret' => NULL,
                'updated_at' => '2023-07-18 05:09:43',
            ),
            5 => 
            array (
                'api' => 'baldar:ab',
                'barcode_format' => NULL,
                'created_at' => '2023-06-20 01:58:16',
                'id' => 6,
                'key' => NULL,
                'locale_id' => 2,
                'name' => 'ab',
                'secret' => NULL,
                'updated_at' => '2023-07-18 05:09:43',
            ),
            6 => 
            array (
                'api' => 'zigzag',
                'barcode_format' => NULL,
                'created_at' => '2023-07-28 15:24:54',
                'id' => 7,
                'key' => NULL,
                'locale_id' => 2,
                'name' => 'zigzag',
                'secret' => NULL,
                'updated_at' => '2023-07-28 15:24:54',
            ),
            7 => 
            array (
                'api' => 'shippingToGo',
                'barcode_format' => NULL,
                'created_at' => '2023-09-04 21:48:33',
                'id' => 8,
                'key' => NULL,
                'locale_id' => 1,
                'name' => 'shippingToGo',
                'secret' => NULL,
                'updated_at' => '2023-09-04 21:48:33',
            ),
            8 => 
            array (
                'api' => 'run:cheetah',
                'barcode_format' => NULL,
                'created_at' => '2023-11-13 18:59:58',
                'id' => 9,
                'key' => 'prUM44rHmOhk8QQQAMMu',
                'locale_id' => 2,
                'name' => 'cheetah',
                'secret' => 'XW4g0zMPeAPeuCOIesxH',
                'updated_at' => '2023-11-13 18:59:58',
            ),
            9 => 
            array (
                'api' => 'lionwheel:mahirli',
                'barcode_format' => NULL,
                'created_at' => '2023-12-20 17:56:27',
                'id' => 10,
                'key' => 'HExN73uZBPqf7ndrkXvG',
                'locale_id' => 2,
                'name' => 'mahirli',
                'secret' => 'M8ibKQWvWKOUjVMWf9o4',
                'updated_at' => '2024-01-08 15:39:18',
            ),
            10 => 
            array (
                'api' => 'ups',
                'barcode_format' => NULL,
                'created_at' => '2023-12-20 17:56:27',
                'id' => 11,
                'key' => '3IxMxtSWm0bPzmRjxmNp',
                'locale_id' => 1,
                'name' => 'ups',
                'secret' => '0mSIVGo3pNyZHXaGJzAF',
                'updated_at' => '2024-01-03 23:20:22',
            ),
            11 => 
            array (
                'api' => 'getpackage',
                'barcode_format' => NULL,
                'created_at' => '2024-03-17 19:22:11',
                'id' => 12,
                'key' => 'BtintmavS6Taipxc20fF',
                'locale_id' => 2,
                'name' => 'getpackage',
                'secret' => 'evvpaPGhmBwjiMbnSOgk',
                'updated_at' => '2024-03-17 19:22:11',
            ),
            12 => 
            array (
                'api' => 'wolt',
                'barcode_format' => NULL,
                'created_at' => '2024-05-26 18:50:47',
                'id' => 13,
                'key' => 'MPMs88xWRYOvHRbEqrT3',
                'locale_id' => 2,
                'name' => 'wolt',
                'secret' => 'kS67P2kEd5dnhDMJWmN5',
                'updated_at' => '2024-05-26 18:50:47',
            ),
            13 => 
            array (
                'api' => 'baldar:tamnoon',
                'barcode_format' => NULL,
                'created_at' => '2024-06-30 20:25:27',
                'id' => 14,
                'key' => NULL,
                'locale_id' => 2,
                'name' => 'tamnoon',
                'secret' => NULL,
                'updated_at' => '2024-06-30 20:25:27',
            ),
            14 => 
            array (
                'api' => 'baldar:negev',
                'barcode_format' => NULL,
                'created_at' => '2024-10-22 17:57:07',
                'id' => 15,
                'key' => NULL,
                'locale_id' => 2,
                'name' => 'negev',
                'secret' => NULL,
                'updated_at' => '2024-10-22 17:57:07',
            ),
        ));
        
        
    }
}