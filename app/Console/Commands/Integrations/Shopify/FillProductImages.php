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
        
        $this->info('Starting to process empty product images...');

        // Fetch products with empty images from order_product table
        $orderProducts = OrderProduct::whereNull('image')->get();

        if ($orderProducts->isEmpty()) {
            $this->info('No products found with empty images.');
            return Command::SUCCESS;
        }

        foreach ($orderProducts as $orderProduct) {

            // Get associated product from the products table
            $product = $orderProduct->product;

            if (!$product) {
                $this->warn("No associated product found for order product ID {$orderProduct->id}");
                continue;
            }

            // Get Shopify Product ID and shop information
            $shopifyShop = $product->store->shopifyShop ?? null;
            $shopifyProductId = $product->shopify_id ?? null;

            if (!$shopifyShop || !$shopifyProductId) {
                $this->warn("Shopify shop ya product ID missing hai order product ID {$orderProduct->id} ke liye");
                continue;
            }

            try {
                // Fetch all variants of the product using Shopify GraphQL API
                $variants = $this->integrationRepository->fetchProductVariantsImages($shopifyShop, $shopifyProductId);

                // Match the order product variation with Shopify variant
                $matchedVariant = null;

                foreach ($variants as $variant) {
            
                    // Match title (variation) with order product's variation field
                    if (stripos($variant['title'], $orderProduct->variation) !== false || stripos($variant['displayName'], $orderProduct->variation) !== false) {
                        $matchedVariant = $variant;
                        break;
                    }
                }

                if ($matchedVariant && isset($matchedVariant['image']['src'])) {
                    // Update the image URL for the matched variant in the order product table
                    $orderProduct->update(['image' => $matchedVariant['image']['src']]);

                    $this->info("Updated image for order product ID {$orderProduct->id}");
                } else {
                    $this->warn("No matching variant found for order product ID {$orderProduct->id}");
                }
            } catch (\Exception $e) {
                // Handle any errors when fetching image
                $this->error("Error fetching image for order product ID {$orderProduct->id}: " . $e->getMessage());
            }
        }

        $this->info('Processing completed.');
        return Command::SUCCESS;
    }



}
