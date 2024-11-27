<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('users')->delete();
        
        \DB::table('users')->insert(array (
            0 => 
            array (
                'created_at' => NULL,
                'email' => 'itay@veloapp.io',
                'email_verified_at' => '2023-07-12 00:08:41',
                'first_name' => 'Itay',
                'id' => 1,
                'image' => NULL,
                'last_name' => 'Rijensky',
                'locale_id' => 1,
                'password' => '$2y$10$Sxk5PLEbFh5oviatGNX/pev8iT9LhSZGWtEWLRya.JtVxWsfQIe1m',
                'phone' => NULL,
                'remember_token' => NULL,
                'token' => NULL,
                'token_created_at' => NULL,
                'updated_at' => '2024-10-28 12:31:41',
            ),
            1 => 
            array (
                'created_at' => '2023-02-22 19:34:13',
                'email' => 'rombazooka@veloapp.io',
                'email_verified_at' => '2023-02-22 19:34:13',
                'first_name' => NULL,
                'id' => 2,
                'image' => NULL,
                'last_name' => NULL,
                'locale_id' => 1,
                'password' => '$2y$10$zRQcfSH2KyFgGTWY1XYuNuyuHvYImHEk7RHT7cfXNc.itJ.DWJ7Xq',
                'phone' => NULL,
                'remember_token' => NULL,
                'token' => NULL,
                'token_created_at' => NULL,
                'updated_at' => '2024-06-17 00:33:21',
            ),
            2 => 
            array (
                'created_at' => '2023-05-02 11:34:23',
                'email' => 'ari@veloapp.io',
                'email_verified_at' => '2023-07-18 12:41:41',
                'first_name' => 'Efron',
                'id' => 28,
                'image' => NULL,
                'last_name' => 'Efron',
                'locale_id' => 1,
                'password' => '$2y$10$uBPIc.aqenukyk6.WkXOI.phaTzDWEM4WhEijC6uG1iSIPdLX7K82',
                'phone' => NULL,
                'remember_token' => NULL,
                'token' => NULL,
                'token_created_at' => NULL,
                'updated_at' => '2024-10-21 14:35:48',
            ),
            3 => 
            array (
                'created_at' => '2023-07-17 19:29:05',
                'email' => 'gabriela@veloapp.io',
                'email_verified_at' => '2024-09-05 12:32:52',
                'first_name' => 'Gabriela',
                'id' => 127,
                'image' => NULL,
                'last_name' => 'Gleizer',
                'locale_id' => 1,
                'password' => '$2y$10$4lpRglW8yVYPMbVL6gQ3W.Tw.xXoFmfinTwBslS8oNO7IdE.40cV2',
                'phone' => NULL,
                'remember_token' => NULL,
                'token' => NULL,
                'token_created_at' => NULL,
                'updated_at' => '2024-10-10 14:24:39',
            ),
            4 => 
            array (
                'created_at' => '2023-08-01 06:58:37',
                'email' => 'amit@veloapp.io',
                'email_verified_at' => '2023-09-26 22:24:53',
                'first_name' => 'Bazak',
                'id' => 146,
                'image' => NULL,
                'last_name' => 'Bazak',
                'locale_id' => 1,
                'password' => '$2y$10$Wl2knLcJfMrXE9qK2vpQG.wNKwwr6/uHqFIg//zGcucuIsF8tWwzS',
                'phone' => NULL,
                'remember_token' => NULL,
                'token' => NULL,
                'token_created_at' => NULL,
                'updated_at' => '2023-10-23 19:01:24',
            ),
            5 => 
            array (
                'created_at' => '2023-08-14 13:35:29',
                'email' => 'tzah@veloapp.io',
                'email_verified_at' => '2024-03-27 13:05:46',
                'first_name' => 'bakal',
                'id' => 181,
                'image' => NULL,
                'last_name' => 'bakal',
                'locale_id' => 1,
                'password' => '$2y$10$mfq8wdw8KOoNiHJJR6ed6uTBfDr37bbYqtECu5OqYnjuVcMYwEkEO',
                'phone' => NULL,
                'remember_token' => NULL,
                'token' => NULL,
                'token_created_at' => NULL,
                'updated_at' => '2024-03-27 13:05:46',
            ),
            6 => 
            array (
                'created_at' => '2024-01-24 10:53:25',
                'email' => 'oryan@veloapp.io',
                'email_verified_at' => '2024-08-12 03:02:38',
                'first_name' => NULL,
                'id' => 430,
                'image' => NULL,
                'last_name' => NULL,
                'locale_id' => 1,
                'password' => '$2y$10$HEl8kXRB9BOUdTOHVKIiEuw/2cxUtY.KJ1izZLTSyFPFaxWCivOHG',
                'phone' => NULL,
                'remember_token' => NULL,
                'token' => '0Ag0jQx8cubFpoGOxhZyaT7BHL5WZT',
                'token_created_at' => '2024-08-12 03:02:38',
                'updated_at' => '2024-09-11 14:47:21',
            ),
            7 => 
            array (
                'created_at' => '2024-03-21 14:26:46',
                'email' => 'talr@veloapp.io',
                'email_verified_at' => '2024-04-15 15:09:59',
                'first_name' => NULL,
                'id' => 484,
                'image' => NULL,
                'last_name' => NULL,
                'locale_id' => 1,
                'password' => '$2y$10$zyx4dm1DQNucPaYHTYAEu.WlC2StWwq4T4X7aa7uAKH4VJelWoYDi',
                'phone' => NULL,
                'remember_token' => NULL,
                'token' => '1z7v4GEXwk8EGC5DZtH4eqTtPUFhzS',
                'token_created_at' => '2024-04-15 15:09:59',
                'updated_at' => '2024-06-17 15:00:06',
            ),
        ));
        
        
    }
}