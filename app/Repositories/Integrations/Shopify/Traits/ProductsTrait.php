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
 * This method retrieves the images for either a product or its variants from Shopify.
 * It uses a GraphQL request to fetch image URLs and returns an array of image URLs,
 * or an empty array if no images are found.
 *
 * @param object $shopifyShop The Shopify shop object containing the store's API credentials.
 * @param string $shopifyId The Shopify product or variant ID in the global ID (GID) format.
 * @param bool $isVariant Flag to indicate if the request is for a product variant (defaults to false).
 * 
 * @return array|null An array of image URLs or an empty array if no images are found.
 * @throws \Exception Throws an exception if the GraphQL request fails or any error occurs.
 */
    public function fetchProductVariantsImages($shopifyShop, $shopifyId, $isVariant = false)
    {
        try {
            // Define the GraphQL query to fetch images for either a product or a product variant
            $query = <<<GQL
                query getProductImages(\$id: ID!) {
                    node(id: \$id) {
                        ... on Product {
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
                            images(first: 1) {
                                edges {
                                    node {
                                        src
                                    }
                                }
                            }
                        }
                        ... on ProductVariant {
                            id
                            title
                            displayName
                            image {
                                src
                            }
                        }
                    }
                }
            GQL;

            // Define the variables for the GraphQL query
            $variables = [
                'id' => "gid://shopify/" . ($isVariant ? 'ProductVariant/' : 'Product/') . $shopifyId
            ];

            // Make the GraphQL API request using the given shop and product/variant ID
            $response = $this->makeGqlApiRequest($shopifyShop, $query, $variables);

            // Initialize an array to store the images
            $images = [];

            // Check if the response contains the node data
            if (isset($response['data']['node'])) {
                $node = $response['data']['node'];

                // If it's a product (not a variant), fetch the product image and variant images
                if (!$isVariant) {
                    // Fetch the product image if available
                    if (isset($node['images']['edges'][0]['node']['src'])) {
                        $images[] = $node['images']['edges'][0]['node']['src'];
                    }

                    // Check the variants of the product and fetch their images
                    if (isset($node['variants']['edges'])) {
                        foreach ($node['variants']['edges'] as $variantEdge) {
                            // If a variant image exists, add it to the images array
                            if (isset($variantEdge['node']['image']['src'])) {
                                $images[] = $variantEdge['node']['image']['src'];
                            }
                        }
                    }
                } else {
                    // If it's a product variant, fetch the variant image
                    if (isset($node['image']['src'])) {
                        $images[] = $node['image']['src'];
                    }
                }
            }

            // Return the list of images found (either product or variant images)
            return $images;

        } catch (\Exception $e) {
            // Log any errors that occur during the fetch process
            Log::error("Product variant images fetch error: " . $e->getMessage());

            // Return a failure message in case of an error
            return $this->fail("Error fetching product variant images");
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
                return $this->fail("No variants found for this product.");
                
            }

        } catch (\Exception $e) {
            // Log any error that occurs during the GraphQL request
            Log::error("Error fetching product variants for product ID {$product_id}.", [
                'error' => $e->getMessage(),
                'product_id' => $product_id
            ]);
            // Return a friendly error message
            return $this->fail("An error occurred while fetching variants. Please try again later.");
        }
    }
  
}
