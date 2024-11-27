<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MigrationsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('migrations')->delete();
        
        \DB::table('migrations')->insert(array (
            0 => 
            array (
                'batch' => 0,
                'id' => 1,
                'migration' => '2024_10_28_165943_create_addresses_table',
            ),
            1 => 
            array (
                'batch' => 0,
                'id' => 2,
                'migration' => '2024_10_28_165943_create_addresses_he_table',
            ),
            2 => 
            array (
                'batch' => 0,
                'id' => 3,
                'migration' => '2024_10_28_165943_create_api_users_table',
            ),
            3 => 
            array (
                'batch' => 0,
                'id' => 4,
                'migration' => '2024_10_28_165943_create_app_model_has_permissions_table',
            ),
            4 => 
            array (
                'batch' => 0,
                'id' => 5,
                'migration' => '2024_10_28_165943_create_app_model_has_roles_table',
            ),
            5 => 
            array (
                'batch' => 0,
                'id' => 6,
                'migration' => '2024_10_28_165943_create_app_permissions_table',
            ),
            6 => 
            array (
                'batch' => 0,
                'id' => 7,
                'migration' => '2024_10_28_165943_create_app_role_has_permissions_table',
            ),
            7 => 
            array (
                'batch' => 0,
                'id' => 8,
                'migration' => '2024_10_28_165943_create_app_roles_table',
            ),
            8 => 
            array (
                'batch' => 0,
                'id' => 9,
                'migration' => '2024_10_28_165943_create_archived_orders_table',
            ),
            9 => 
            array (
                'batch' => 0,
                'id' => 10,
                'migration' => '2024_10_28_165943_create_bills_table',
            ),
            10 => 
            array (
                'batch' => 0,
                'id' => 11,
                'migration' => '2024_10_28_165943_create_couriers_table',
            ),
            11 => 
            array (
                'batch' => 0,
                'id' => 12,
                'migration' => '2024_10_28_165943_create_credit_lines_table',
            ),
            12 => 
            array (
                'batch' => 0,
                'id' => 13,
                'migration' => '2024_10_28_165943_create_currencies_table',
            ),
            13 => 
            array (
                'batch' => 0,
                'id' => 14,
                'migration' => '2024_10_28_165943_create_customers_table',
            ),
            14 => 
            array (
                'batch' => 0,
                'id' => 15,
                'migration' => '2024_10_28_165943_create_data_patches_table',
            ),
            15 => 
            array (
                'batch' => 0,
                'id' => 16,
                'migration' => '2024_10_28_165943_create_deliveries_table',
            ),
            16 => 
            array (
                'batch' => 0,
                'id' => 17,
                'migration' => '2024_10_28_165943_create_failed_jobs_table',
            ),
            17 => 
            array (
                'batch' => 0,
                'id' => 18,
                'migration' => '2024_10_28_165943_create_job_batches_table',
            ),
            18 => 
            array (
                'batch' => 0,
                'id' => 19,
                'migration' => '2024_10_28_165943_create_locales_table',
            ),
            19 => 
            array (
                'batch' => 0,
                'id' => 20,
                'migration' => '2024_10_28_165943_create_notes_table',
            ),
            20 => 
            array (
                'batch' => 0,
                'id' => 21,
                'migration' => '2024_10_28_165943_create_order_product_table',
            ),
            21 => 
            array (
                'batch' => 0,
                'id' => 22,
                'migration' => '2024_10_28_165943_create_orders_table',
            ),
            22 => 
            array (
                'batch' => 0,
                'id' => 23,
                'migration' => '2024_10_28_165943_create_password_resets_table',
            ),
            23 => 
            array (
                'batch' => 0,
                'id' => 24,
                'migration' => '2024_10_28_165943_create_payment_methods_table',
            ),
            24 => 
            array (
                'batch' => 0,
                'id' => 25,
                'migration' => '2024_10_28_165943_create_plans_table',
            ),
            25 => 
            array (
                'batch' => 0,
                'id' => 26,
                'migration' => '2024_10_28_165943_create_polygon_connections_table',
            ),
            26 => 
            array (
                'batch' => 0,
                'id' => 27,
                'migration' => '2024_10_28_165943_create_polygons_table',
            ),
            27 => 
            array (
                'batch' => 0,
                'id' => 28,
                'migration' => '2024_10_28_165943_create_prices_table',
            ),
            28 => 
            array (
                'batch' => 0,
                'id' => 29,
                'migration' => '2024_10_28_165943_create_products_table',
            ),
            29 => 
            array (
                'batch' => 0,
                'id' => 30,
                'migration' => '2024_10_28_165943_create_shipping_codes_table',
            ),
            30 => 
            array (
                'batch' => 0,
                'id' => 31,
                'migration' => '2024_10_28_165943_create_shopify_shops_table',
            ),
            31 => 
            array (
                'batch' => 0,
                'id' => 32,
                'migration' => '2024_10_28_165943_create_sms_logs_table',
            ),
            32 => 
            array (
                'batch' => 0,
                'id' => 33,
                'migration' => '2024_10_28_165943_create_store_user_table',
            ),
            33 => 
            array (
                'batch' => 0,
                'id' => 34,
                'migration' => '2024_10_28_165943_create_stores_table',
            ),
            34 => 
            array (
                'batch' => 0,
                'id' => 35,
                'migration' => '2024_10_28_165943_create_subscriptions_table',
            ),
            35 => 
            array (
                'batch' => 0,
                'id' => 36,
                'migration' => '2024_10_28_165943_create_support_systems_table',
            ),
            36 => 
            array (
                'batch' => 0,
                'id' => 37,
                'migration' => '2024_10_28_165943_create_tax_polygons_table',
            ),
            37 => 
            array (
                'batch' => 0,
                'id' => 38,
                'migration' => '2024_10_28_165943_create_transactions_table',
            ),
            38 => 
            array (
                'batch' => 0,
                'id' => 39,
                'migration' => '2024_10_28_165943_create_users_table',
            ),
            39 => 
            array (
                'batch' => 0,
                'id' => 40,
                'migration' => '2024_10_28_165943_create_venti_calls_table',
            ),
            40 => 
            array (
                'batch' => 0,
                'id' => 41,
                'migration' => '2024_10_28_165946_add_foreign_keys_to_app_model_has_permissions_table',
            ),
            41 => 
            array (
                'batch' => 0,
                'id' => 42,
                'migration' => '2024_10_28_165946_add_foreign_keys_to_app_model_has_roles_table',
            ),
            42 => 
            array (
                'batch' => 0,
                'id' => 43,
                'migration' => '2024_10_28_165946_add_foreign_keys_to_app_role_has_permissions_table',
            ),
            43 => 
            array (
                'batch' => 0,
                'id' => 44,
                'migration' => '2024_10_28_165946_add_foreign_keys_to_stores_table',
            ),
        ));
        
        
    }
}