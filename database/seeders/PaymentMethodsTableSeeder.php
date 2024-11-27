<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PaymentMethodsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        \DB::table('payment_methods')->delete();

        \DB::table('payment_methods')->insert(array(
            0 =>
                array(
                    'card_type' => NULL,
                    'created_at' => '2023-02-22 19:34:13',
                    'default' => 1,
                    'email' => 'itay@veloapp.io',
                    'expiry' => '1225',
                    'holder_name' => 'Itay Rijensky',
                    'id' => 1,
                    'mask' => '458045******4580',
                    'name' => NULL,
                    'phone' => '0545445412',
                    'social_id' => '300654522',
                    'store_slug' => 'velo',
                    'token' => 'BUYER163-4195933N-VDSVMQBP-UAXSBKIN',
                    'updated_at' => '2023-06-13 01:40:21',
                    'user_id' => 1,
                ),
            1 =>
                array(
                    'card_type' => 'mastercard',
                    'created_at' => '2023-05-02 11:48:08',
                    'default' => 1,
                    'email' => 'ari@veloapp.io',
                    'expiry' => '0229',
                    'holder_name' => 'Ari Efron',
                    'id' => 16,
                    'mask' => '555650******8728',
                    'name' => NULL,
                    'phone' => '0543095056',
                    'social_id' => '308575091',
                    'store_slug' => 'ari',
                    'token' => 'BUYER163-3016623R-C7JFQ98Q-MFVX1HG6',
                    'updated_at' => '2023-11-13 18:59:58',
                    'user_id' => 28,
                ),
            2 =>
                array(
                    'card_type' => NULL,
                    'created_at' => '2023-05-16 12:34:04',
                    'default' => 1,
                    'email' => 'itay@veloapp.io',
                    'expiry' => '1128',
                    'holder_name' => 'fuck mylife',
                    'id' => 25,
                    'mask' => '458027******2481',
                    'name' => NULL,
                    'phone' => '0505050505',
                    'social_id' => '300654522',
                    'store_slug' => 'fmlstore',
                    'token' => 'BUYER163-4218844G-7LMFXCR4-BJKLRT0Y',
                    'updated_at' => '2023-06-13 01:40:21',
                    'user_id' => 1,
                ),
            3 =>
                array(
                    'card_type' => NULL,
                    'created_at' => '2023-07-17 19:36:54',
                    'default' => 1,
                    'email' => 'nachshon1992@gmail.com',
                    'expiry' => '1126',
                    'holder_name' => 'nachshon hertz',
                    'id' => 85,
                    'mask' => '458041******1767',
                    'name' => NULL,
                    'phone' => '972528310508',
                    'social_id' => '203966551',
                    'store_slug' => 'nachshon',
                    'token' => 'BUYER163-9601013G-XVL0FN81-GIZ5FJL2',
                    'updated_at' => '2023-07-17 19:36:54',
                    'user_id' => 127,
                ),
            4 =>
                array(
                    'card_type' => NULL,
                    'created_at' => '2023-08-01 07:09:27',
                    'default' => 1,
                    'email' => 'Abazak22@gmail.com',
                    'expiry' => '1127',
                    'holder_name' => 'amit bazak',
                    'id' => 100,
                    'mask' => '458004******2248',
                    'name' => NULL,
                    'phone' => '7323369206',
                    'social_id' => '311231104',
                    'store_slug' => 'liage',
                    'token' => 'BUYER163-08521656-8SB3BDVY-RSJY23KQ',
                    'updated_at' => '2023-08-01 07:09:27',
                    'user_id' => 146,
                ),
            5 =>
                array(
                    'card_type' => NULL,
                    'created_at' => '2023-08-14 21:37:04',
                    'default' => 1,
                    'email' => 'tzah@veloapp.io',
                    'expiry' => '0824',
                    'holder_name' => 'tzah bakal',
                    'id' => 125,
                    'mask' => '532611******1761',
                    'name' => NULL,
                    'phone' => '0528212178',
                    'social_id' => '036329365',
                    'store_slug' => 'tzah',
                    'token' => 'BUYER163-20274234-EJPYVC7Y-N5CG7NZG',
                    'updated_at' => '2023-08-14 21:37:04',
                    'user_id' => 181,
                ),
        ));


    }
}
