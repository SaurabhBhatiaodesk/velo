<?php

namespace App\Console\Commands\Integrations\Shopify;

use Illuminate\Console\Command;
use App\Repositories\Integrations\Shopify\IntegrationRepository;
use App\Models\OrderProduct;
use Illuminate\Support\Facades\Log;

class FillProductImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'velo:shopify:fillProductImages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'saves product image urls to the database';

    /**
     * The IntegrationRepository instance.
     *
     * @var IntegrationRepository
     */
    protected $integrationRepository;

    /**
     * Create a new command instance.
     *
     * @param IntegrationRepository $integrationRepository
     * @return void
     */
    public function __construct(IntegrationRepository $integrationRepository)
    {
        parent::__construct();
        $this->integrationRepository = $integrationRepository;
    }

    /**
 * Handle the command to update product images for order products with missing images.
 *
 * This method processes all order products with empty image fields in the `order_product` table.
 * It fetches the associated product's Shopify variants and updates the image URL in the `image` column.
 * The Shopify variant is matched by comparing the product's variation with the variant's title or display name.
 *
 * @return int The exit status of the command.
 */
public function handle()
{
    $repo = new IntegrationRepository();
    // iterate order_product table
    // find lines where `image` is empty
    // save image urls to the database in the `image` column
    // the shopify variation id is in the `products` table, in the `shopify_id` column
    // the $shopifyShop object is in $product->store->shopifyShop

    // Fetch order products with empty image fields
    $orderProducts = OrderProduct::whereNull('image')->get();

    if ($orderProducts->isEmpty()) {
        return Command::SUCCESS;
    }

    foreach ($orderProducts as $orderProduct) {

        // Get associated product from the products table
        $product = $orderProduct->product;
        if (!$product) continue;

        // Retrieve Shopify shop details and product ID
        $shopifyShop = $product->store->shopifyShop ?? null;
        $shopifyProductId = $product->shopify_id ?? null;

        if (!$shopifyShop || !$shopifyProductId) continue;

        try {
            // Check if the order product is a variant or a main product
            $isVariant = false;
            $variantId = $orderProduct->shopify_id ?? null; // Use the shopify_id stored in OrderProduct for variants

            if ($variantId) {
                $isVariant = true;
                $shopifyProductId = $variantId; // If it's a variant, use the variant ID
            }

            // Fetch variant images using the integration repository
            $images = $this->integrationRepository->fetchProductVariantsImages($shopifyShop, $shopifyProductId, $isVariant);

            if (!empty($images)) {
                // If a variant image is found, use the first image
                $orderProduct->update(['image' => $images[0]]);
            } else {
                // If no variant image, fallback to the main product image
                $fallbackImages = $this->integrationRepository->fetchProductVariantsImages($shopifyShop, $shopifyProductId);
                if (!empty($fallbackImages)) {
                    $orderProduct->update(['image' => $fallbackImages[0]]);
                }
            }

        } catch (\Exception $e) {
            // Log the error and return failure status
            $this->error("Error fetching image for order product ID {$orderProduct->id}: " . $e->getMessage());
            return $this->fail("Error fetching image for order product ID {$orderProduct->id}");
        }
    }

    return Command::SUCCESS;
}


}
