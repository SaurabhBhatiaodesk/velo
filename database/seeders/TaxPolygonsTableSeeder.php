<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TaxPolygonsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('tax_polygons')->delete();
        
        \DB::table('tax_polygons')->insert(array (
            0 => 
            array (
                'active' => 1,
                'amount' => 0.0,
                'city' => NULL,
                'country' => 'Israel',
                'created_at' => '2023-04-04 01:26:12',
                'id' => 1,
                'name' => 'vat',
                'precentage' => 17.0,
                'state' => NULL,
                'updated_at' => '2023-05-17 14:59:51',
                'zipcode' => NULL,
            ),
        ));
        
        
    }
}