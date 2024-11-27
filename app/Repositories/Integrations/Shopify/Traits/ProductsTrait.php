<?php

namespace App\Repositories\Integrations\Shopify\Traits;

use Illuminate\Support\Facades\Log;
use App\Models\Product;

trait ProductsTrait
{
    public function saveProduct($params, $storeSlug, $currency, $order = false)
    {
        $price = false;
        $isVariant = !!(isset($params['variant_id']) && !is_null($params['variant_id']));
        $product = Product::where('shopify_id', ($isVariant) ? $params['variant_id'] : $params['product_id'])->first();
        if (!$product) {
            $product = Product::create([
                'name' => $params['name'],
                'code' => $params['sku'],
                'shopify_id' => ($isVariant) ? $params['variant_id'] : $params['product_id'],
                'store_slug' => $storeSlug,
            ]);
        }

        $price = false;
        // find the price in the store's currency
        foreach ($params['price_set'] as $priceSet) {
            if (strtoupper($priceSet['currency_code']) === $currency->iso) {
                $price = $priceSet['amount'];
                break;
            }
        }

        if (!$price) {
            Log::debug('validation failed - invalid product price', [
                'order' => ($order) ? $order->name : 'from admin',
                'price_set' => $params['price_set'],
            ]);
            return false;
        }

        // update the saved product price if it exists
        $found = false;
        foreach ($product->prices as $productPrice) {
            if ($productPrice->currency->id === $currency->id) {
                $productPrice->update(['price' => $price]);
                $found = true;
                break;
            }
        }

        if (!$found) {
            $product->prices()->create([
                'price' => $price,
                'currency_id' => $currency->id,
            ]);
        }

        if ($order) {
            $order->products()->syncWithoutDetaching([
                $product->id => [
                    'quantity' => $params['quantity'],
                    'variation' => ($isVariant) ? $params['variant_title'] : $params['title'],
                    'total' => ($params['quantity'] * $price),
                ]
            ]);
        }

        return $product;
    }



       /**
     * Fetch product variant images from Shopify using GraphQL API.
     *
     * This method retrieves the images for all variants of a product from Shopify by making a GraphQL request.
     * It returns an array of variant details, including image URLs, or an empty array if no images are found.
     *
     * @param object $shopifyShop The Shopify shop object containing the store's API credentials.
     * @param string $shopifyProductId The Shopify product ID in the global ID (GID) format.
     * @return array|null An array of product variants with image URLs, or null if no images are found.
     * @throws \Exception Throws an exception if the GraphQL request fails or if any error occurs.
     */
    public function fetchProductVariantsImages($shopifyShop, $shopifyProductId)
    {
        try {
            // Define the GraphQL query to fetch product variant images
            $query = <<<GQL
                 query getProductVariantsImages(\$productId: ID!) {
                     product(id: \$productId) {  
                     id
                         variants(first: 100) { 
                             edges {
                                 node { 
                                     id
                                     title
                                     displayName
                                     image { 
                                         src 
                                     }
                                 }
                             }
                         }
                     }
                 }
             GQL;
 
            // Variables to be passed to the GraphQL query
            $variables = [
                'productId' => "gid://shopify/Product/{$shopifyProductId}" // Shopify Product ID in GID format
            ];
 
            // Make the GraphQL API request
            $response = $this->makeGqlApiRequest($shopifyShop, $query, $variables);
 
            $variants = []; // Initialize an empty array to store variant data
            // Check if the response contains variant data
            if (isset($response['data']['product']['variants']['edges'])) {
                // Loop through each variant edge and extract node data
                foreach ($response['data']['product']['variants']['edges'] as $variantEdge) {
                    $variants[] = $variantEdge['node']; // Add the variant node to the result array
                }
            }
            return $variants; // Return the array of variants with their images
 
        } catch (\Exception $e) {
            // Log the error in case of an exception
            Log::error("Error fetching product variant images for product ID {$shopifyProductId}: " . $e->getMessage());
 
            // Throw a new exception with a user-friendly message
            throw new \Exception("Product variant images fetch karne mein error: " . $e->getMessage());
        }
    }

    /**
     * Fetch Shopify Variant data by product ID.
     *
     * This function fetches product variants from Shopify, including their prices and selected options (like size or color).
     * It returns an organized array of variant details along with the possible option values for each variant.
     * If no variants are found, it returns an error message.
     *
     * @param string $product_id The product ID in the database for which variants are being fetched.
     * @return array An array containing variant details and available options, or an error message.
     */
    public function fetchShopifyVariant($product_id)
    {
        try {
            // Fetch product from database
            $product = Product::where('id', ($product_id))->first();
            
            if (!$product) {
                return ['error' => 'Product not found in the database.'];
            }

            $shopify_id = $product->shopify_id;
            $shopifyShop = $product->store->shopifyShop;

            // GraphQL query to get product variants and their selected options
            $query = 'query GetProductVariants($id: ID!) {
                product(id: $id) {
                    id
                    title
                    variants(first: 50) {
                        edges {
                            node {
                                id
                                title
                                price
                                availableForSale
                                selectedOptions {
                                    name
                                    value
                                }
                            }
                        }
                    }
                }
            }';

            // Prepare the variables for the query
            $variables = ['id' => "gid://shopify/Product/{$shopify_id}"];

            // Make the GraphQL request to Shopify
            $response = $this->makeGqlApiRequest($shopifyShop, $query, $variables);

            // Log the response to inspect it further
            Log::info('Shopify GraphQL Response:', ['response' => $response]);

            // Check if the response contains data
            if (isset($response['data']['product']['variants']['edges']) && !empty($response['data']['product']['variants']['edges'])) {
                $variants = $response['data']['product']['variants']['edges'];
                
                // Organize the response into a user-friendly format
                $variantOptions = [];
                foreach ($variants as $variant) {
                    $node = $variant['node']; // Each variant node
                    
                    // Ensure selectedOptions exists before processing
                    if (isset($node['selectedOptions']) && is_array($node['selectedOptions'])) {
                        foreach ($node['selectedOptions'] as $option) {
                            // Check if the option name exists before adding it
                            if (isset($option['name']) && isset($option['value'])) {
                                $variantOptions[$option['name']][] = $option['value'];
                            }
                        }
                    }
                }

                // Ensure unique options for dropdowns
                foreach ($variantOptions as $key => $values) {
                    $variantOptions[$key] = array_unique($values);
                }

                // Prepare the result to include both variants and option groupings
                return [
                    'variants' => $variants,  // Variant details
                    'options' => $variantOptions  // Grouped options for dropdowns (e.g., size, color)
                ];
            } else {
                Log::warning('No variants found for this product.', ['product_id' => $product_id]);
                return ['error' => 'No variants found for this product.'];
            }

        } catch (\Exception $e) {
            // Log any error that occurs during the GraphQL request
            Log::error("Error fetching product variants for product ID {$product_id}.", [
                'error' => $e->getMessage(),
                'product_id' => $product_id
            ]);

            // Return a friendly error message
            return ['error' => 'An error occurred while fetching variants. Please try again later.'];
        }
    }

    

    
}
