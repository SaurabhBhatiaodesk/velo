<?php

namespace App\Repositories\Integrations\Shopify\Traits;

use App\Models\ShopifyShop;
use App\Models\Address;
use Log;

trait InventoryTrait
{
    /**
     * Get shopify locations
     * @param ShopifyShop $shopifyShop
     *
     * @return array
     */
    public function getLocations(ShopifyShop $shopifyShop)
    {
        $response = $this->makeRestApiRequest($shopifyShop, 'locations.json', [], 'get');
        if (!isset($response['locations'])) {
            return $this->fail('shopify.inventory.noLocations');
        }
        if (!$this->validateAccessScopes($shopifyShop)) {
            $response['scopes'] = $this->shopifyInstall($shopifyShop->domain);
        }
        return $response;
    }

    /**
     * Assign shopify locations to store addresses
     * @param array $inputs [[addressId1 => shopifyLocation1], [addressId2 => shopifyLocation2], ...]
     * @return array
     */
    public function assignLocations($user, $addressesLocations)
    {
        foreach ($addressesLocations as $addressId => $shopifyLocation) {
            $address = Address::find($addressId);
            if (!$address) {
                return $this->fail('address.notFound', 404, ['addressId' => $addressId]);
            }
            if (!$user->can('update', $address)) {
                return $this->fail('auth.unauthorized', 401, ['addressId' => $addressId]);
            }
            if (!$address->update(['shopify_id' => $shopifyLocation['id'], 'shopify_location' => $shopifyLocation])) {
                return $this->fail('address.updateFailed', 500, ['addressId' => $addressId]);
            }
        }
        return [
            'success' => true,
        ];
    }

    /**
     * Get inventory levels for a shopify shop's store's pickup addresses
     * @param ShopifyShop $shopifyShop
     * @return array
     */
    public function getInventoryLevels($shopifyShop)
    {
        $locations = $shopifyShop->store
            ->pickup_addresses()
            ->whereNotNull('shopify_id')
            ->pluck('shopify_id')
            ->toArray();

        return $this->makeRestApiRequest($shopifyShop, 'inventory_levels.json?location_ids=' . implode(',', $locations), [], 'get');
    }

    /**
     * Check if a variant has enough stock
     * @param ShopifyShop $shopifyShop
     * @param int $quantity
     * @param int $variantId
     *
     * @return bool
     */
    public function validateVariantStock($shopifyShop, $variantId, $quantity)
    {
        $restApiClient = $this->restApiClient($shopifyShop);
        $response = json_decode($restApiClient->get('variants/' . $variantId . '.json')->body(), true)['variant'];
        return (floatVal($quantity) <= floatval($response['inventory_quantity']));
    }

    /**
     * Subtracts inventory from Shopify
     *
     * @param $order \App\Models\Order
     * @param $excludedProducts array of product IDs to exclude (optional)
     * @return bool
     */
    public function subtractInventory($order, $excludedProducts = [])
    {
        // Iterate over each product in the order to adjust inventory
        foreach ($order->products as $product) {
            // Skip products that are in the excluded list
            if (in_array($product->id, $excludedProducts)) {
                continue;
            }

            // Get the product variant ID and quantity to subtract from inventory
            $variantId = $product->shopify_id;
            $quantityToSubtract = $product->pivot->quantity;

            // Get the inventory location ID from the order's pickup address
            $locationId = $order->pickup_address->shopify_location['id'];

            // Prepare the GraphQL query to fetch the inventory item ID for the product variant
            $query = '
            query {
                productVariant(id: "gid://shopify/ProductVariant/' . $variantId . '") {
                    inventoryItem {
                        id // The inventory item ID associated with the product variant
                    }
                }
            }';

            // Send the GraphQL request to get the inventory item ID
            $response = $this->makeGqlApiRequest($order->shopifyShop, $query);

            // Check if there were any errors in fetching the inventory item ID
            if (isset($response['errors']) && !empty($response['errors'])) {
                Log::error('Error fetching inventory item ID', [
                    'errors' => $response['errors']
                ]);
                return false;
            }

            // Extract the inventory item ID from the response
            $inventoryItemId = $response['data']['productVariant']['inventoryItem']['id'] ?? null;

            // Log an error and skip the product if the inventory item ID is not found
            if (!$inventoryItemId) {
                Log::error('Inventory item ID not found for variant', [
                    'variant_id' => $variantId
                ]);
                continue;
            }

            // Prepare the GraphQL mutation to adjust the inventory quantity
            $mutation = '
            mutation inventoryAdjustQuantities($input: InventoryAdjustQuantitiesInput!) {
                inventoryAdjustQuantities(input: $input) {
                    userErrors {
                        field // Field where the error occurred
                        message // Error message
                    }
                    inventoryAdjustmentGroup {
                        createdAt // Time when the adjustment was created
                        reason // Reason for the adjustment
                        referenceDocumentUri // Reference document URI for tracking
                        changes {
                            name // Name of the inventory change
                            delta // Change in inventory quantity
                        }
                    }
                }
            }';

            // Prepare the input variables for the mutation
            $variables = [
                'input' => [
                    'reason' => 'correction',
                    'name' => 'available',
                    'referenceDocumentUri' => 'logistics://some.warehouse/take/' . now()->format('Y-m-d'),
                    'changes' => [
                        [
                            'delta' => -$quantityToSubtract,
                            'inventoryItemId' => $inventoryItemId,
                            'locationId' => $locationId,
                        ],
                    ],
                ],
            ];

            // Send the GraphQL request to adjust the inventory quantity
            $response = $this->makeGqlApiRequest($order->shopifyShop, $mutation, $variables);

            // Check for any user errors in the response and log them if present
            if (isset($response['data']['inventoryAdjustQuantities']['userErrors']) && !empty($response['data']['inventoryAdjustQuantities']['userErrors'])) {
                Log::error('Error adjusting inventory quantity', [
                    'errors' => $response['data']['inventoryAdjustQuantities']['userErrors'] // Log the error details
                ]);
                return false;
            }
        }
        return true;
    }
}
