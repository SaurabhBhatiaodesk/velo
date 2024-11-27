<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $this->call(AddressesTableSeeder::class);
        $this->call(AddressesHeTableSeeder::class);
        $this->call(ApiUsersTableSeeder::class);
        $this->call(AppModelHasRolesTableSeeder::class);
        $this->call(AppPermissionsTableSeeder::class);
        $this->call(AppRoleHasPermissionsTableSeeder::class);
        $this->call(AppRolesTableSeeder::class);
        $this->call(ArchivedOrdersTableSeeder::class);
        $this->call(BillsTableSeeder::class);
        $this->call(CouriersTableSeeder::class);
        $this->call(CreditLinesTableSeeder::class);
        $this->call(CurrenciesTableSeeder::class);
        $this->call(CustomersTableSeeder::class);
        $this->call(DataPatchesTableSeeder::class);
        $this->call(DeliveriesTableSeeder::class);
        $this->call(LocalesTableSeeder::class);
        $this->call(MigrationsTableSeeder::class);
        $this->call(OrderProductTableSeeder::class);
        $this->call(OrdersTableSeeder::class);
        $this->call(PaymentMethodsTableSeeder::class);
        $this->call(PlansTableSeeder::class);
        $this->call(PolygonsTableSeeder::class);
        $this->call(PricesTableSeeder::class);
        $this->call(ProductsTableSeeder::class);
        $this->call(ShippingCodesTableSeeder::class);
        $this->call(ShopifyShopsTableSeeder::class);
        $this->call(StoreUserTableSeeder::class);
        $this->call(StoresTableSeeder::class);
        $this->call(SubscriptionsTableSeeder::class);
        $this->call(SupportSystemsTableSeeder::class);
        $this->call(TaxPolygonsTableSeeder::class);
        $this->call(TransactionsTableSeeder::class);
        $this->call(UsersTableSeeder::class);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
