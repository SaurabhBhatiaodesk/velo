<?php

namespace App\Console\Commands\Integrations\Shopify;

use Illuminate\Console\Command;
use App\Models\ShopifyShop;
use Carbon\Carbon;

class ClearUnusedShopifyShops extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:shopify:clearUnusedShopifyShops';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Removes ShopifyShops that are older than 3 days and haven\'t been connected to a store';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        ShopifyShop::whereNull('store_slug')
            ->where('created_at', '<', Carbon::now()->subDays(3))
            ->delete();
    }
}
