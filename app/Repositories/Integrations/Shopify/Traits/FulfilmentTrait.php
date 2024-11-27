<?php

namespace App\Repositories\Integrations\Shopify\Traits;

use App\Enums\DeliveryStatusEnum;
use Log;

trait FulfilmentTrait
{
    public function getShopifyStatus($veloStatus)
    {
        switch ($veloStatus) {
            // before transmit
            case DeliveryStatusEnum::Placed->value:
            case DeliveryStatusEnum::Updated->value:
            case DeliveryStatusEnum::AcceptFailed->value:
            case DeliveryStatusEnum::PendingAccept->value:
            case DeliveryStatusEnum::DataProblem->value:
                return 'PENDING';

            // before pickup
            case DeliveryStatusEnum::Accepted->value:
            case DeliveryStatusEnum::PendingPickup->value:
            // in transit
            case DeliveryStatusEnum::Transit->value:
            case DeliveryStatusEnum::TransitToDestination->value:
            case DeliveryStatusEnum::TransitToWarehouse->value:
            case DeliveryStatusEnum::TransitToSender->value:
            case DeliveryStatusEnum::InWarehouse->value:
            case DeliveryStatusEnum::PendingCancel->value:
                return 'OPEN';

            case DeliveryStatusEnum::Cancelled->value:
            case DeliveryStatusEnum::ServiceCancel->value:
            case DeliveryStatusEnum::Rejected->value:
            case DeliveryStatusEnum::Refunded->value:
                return 'CANCELLED';

            case DeliveryStatusEnum::Delivered->value:
                return 'SUCCESS';

            case DeliveryStatusEnum::Failed->value:
                return 'FAILURE';
        }
    }


    /***
     * Updates the tracking status of an order on Shopify
     *
     * @param $order \App\Models\Order
     * @return boolean
     */
    public function updateFulfilmentStatus($order)
    {
        // status: $order->delivery->status->value (string)
        // courier status: $order->delivery->courier_status (string)

        // TODO:
        // Update the tracking status of an order on Shopify

        // Prepare GraphQL query to fetch the fulfillment details of the order
        $query = '
        query order($id: ID!) {
            order(id: $id) {
                id // Order ID
                fulfillments(first: 10) { // Fetch the first 10 fulfillments
                    id // Fulfillment ID
                    status // Status of the fulfillment
                    trackingInfo(first: 10) { // Fetch tracking information
                        url // Tracking URL
                    }
                }
            }
        }';

        // Prepare the variables for the GraphQL query
        $variables = [
            'id' => $orderId = $order->id
        ];

        // Send the GraphQL request to fetch fulfillment details
        $response = $this->makeGqlApiRequest($order->shopifyShop, $query, $variables);

        // Check for errors in the response and log them if any
        if (isset($response['errors']) && !empty($response['errors'])) {
            Log::error('Error fetching fulfillment details', [
                'errors' => $response['errors']
            ]);
            return false;
        }

        // Extract the first fulfillment ID if available
        $fulfillmentId = null;
        if (isset($response['data']['order']['fulfillments']) && count($response['data']['order']['fulfillments']) > 0) {
            $fulfillmentId = $response['data']['order']['fulfillments'][0]['id'];
        }

        // If no fulfillment ID was found, log an error and return false
        if (!$fulfillmentId) {
            Log::error('Fulfillment ID not found for order', [
                'order_id' => $orderId
            ]);
            return false;
        }

        // Extract the status from the order's delivery details
        $status = $this->getShopifyStatus($order->delivery->status->value);

        // Check if there is a status to update the fulfillment event
        if (!$status) {
            return false;
        }
        // Prepare GraphQL mutation for creating a fulfillment event
        $mutation = '
        mutation fulfillmentEventCreate($fulfillmentEvent: FulfillmentEventInput!) {
            fulfillmentEventCreate(fulfillmentEvent: $fulfillmentEvent) {
                fulfillmentEvent { id status message } // Details of the created event
                userErrors { field message } // Errors if the request fails
            }
        }';

        // Prepare the variables for the mutation
        $variables = [
            'fulfillmentEvent' => [
                'fulfillmentId' => $fulfillmentId,
                'status' => $status,
                'message' => 'This package is now out for delivery!',
                'happenedAt' => now()->toIso8601String(),
                'estimatedDeliveryAt' => now()->addHour()->toIso8601String(),
                'address1' => $order->delivery->address1,
                'city' => $order->delivery->city,
                'province' => $order->delivery->province,
                'country' => $order->delivery->country,
                'zip' => $order->delivery->zip,
                'latitude' => $order->delivery->latitude,
                'longitude' => $order->delivery->longitude,
            ]
        ];

        // Send the request to create the fulfillment event
        $response = $this->makeGqlApiRequest($order->shopifyShop, $mutation, $variables);

        // Check for user errors and log them if any
        if (isset($response['data']['fulfillmentEventCreate']['userErrors']) && !empty($response['data']['fulfillmentEventCreate']['userErrors'])) {
            Log::error('Error updating fulfillment event', [
                'errors' => $response['data']['fulfillmentEventCreate']['userErrors']
            ]);
            return false;
        }

        return true;
    }

    /***
     * Attaches tracking info to an order on Shopify
     *
     * @param $order \App\Models\Order
     * @return boolean
     */
    public function attachOrderMetadata($order)
    {
        // Order Number: $order->name (string | null)
        // Tracking URL: $order->delivery->external_tracking_url (string | null)
        // Delivery Number: $order->delivery->remote_id (string)
        // Barcode: $order->delivery->remote_id (string)

        // Timestamps (nullable):
        // $order->delivery->accepted_at - order approved
        // $order->delivery->pickup_at - order picked up
        // $order->delivery->delivered_at - order delivered
        // $order->delivery->cancelled_at - order cancelled
        // $order->delivery->rejected_at - order rejected

        // Prepare the GraphQL mutation string for updating order notes in Shopify.
        $query = '
        mutation($input: OrderInput!) {
            orderUpdate(input: $input) {
                order {
                    id // Returns the ID of the updated order if successful
                }
                userErrors {
                    field // Indicates the field where the error occurred
                    message // Provides the error message
                }
            }
        }';

        $variables = [
            'input' => [
                'id' => 'gid://shopify/Order/' . $order->id,
                'tags' => ["Order Number: {$order->name}\n"],
                'note' => [
                    'Created At' => $order->delivery->created_at,
                    'Accepted At' => $order->delivery->accepted_at,
                    'Barcode' => $order->delivery->remote_id,
                    'Delivery Number' => $order->delivery->remote_id,
                    'Tracking URL' => $order->delivery->external_tracking_url,
                    'Pickup At' => $order->delivery->pickup_at,
                    'Delivered At' => $order->delivery->delivered_at,
                    'Cancelled At' => $order->delivery->cancelled_at,
                    'Rejected At' => $order->delivery->rejected_at,
                ]
            ],
        ];

        // remove null values and format the note
        $variables['input']['note'] = array_filter($variables['input']['note'], fn($value) => !is_null($value));
        $variables['input']['note'] = array_map(fn($key, $value) => "$key: $value", $variables['input']['note']);
        $variables['input']['note'] = implode("\n", $variables['input']['note']);

        // Send the GraphQL request to Shopify API with the prepared mutation and variables.
        $response = $this->makeGqlApiRequest($order->shopifyShop, $query, $variables);

        // Check if there were any user errors returned by Shopify.
        if (isset($response['data']['orderUpdate']['userErrors']) && !empty($response['data']['orderUpdate']['userErrors'])) {
            // Log the errors if the update was not successful.
            Log::error('Error attaching order metadata', [
                'errors' => $response['data']['orderUpdate']['userErrors']
            ]);
            return false;
        }


        return true;
    }

    /**
     * Processes the fulfillment order of a given order
     *
     * @param $order \App\Models\Order
     * @return boolean
     */
    public function processFulfillmentOrder($order)
    {
        // Step 1: Query to get fulfillment orders for the given order ID
        $query = '
        query order($id: ID!) {
            order(id: $id) {
                id
                fulfillmentOrders(first: 10) {
                    edges {
                        node {
                            id
                            lineItems(first: 10) {
                                nodes {
                                    id
                                    sku
                                }
                            }
                        }
                    }
                }
            }
        }';

        // Prepare variables for the query
        $variables = [
            'id' => 'gid://shopify/Order/' . $order->id,
        ];

        // Send request to fetch fulfillment orders
        $response = $this->makeGqlApiRequest($order->shopifyShop, $query, $variables);

        // Check for errors in the response
        if (isset($response['errors']) && !empty($response['errors'])) {
            Log::error('Error fetching fulfillment order details', [
                'errors' => $response['errors'],
            ]);
            return false;
        }

        // Extract fulfillment orders
        $fulfillmentOrders = $response['data']['order']['fulfillmentOrders']['edges'] ?? [];
        if (empty($fulfillmentOrders)) {
            Log::info('No fulfillment orders found for the given order ID', [
                'order_id' => $order->shopify_id,
            ]);
            return false;
        }

        // Get the first fulfillment order ID
        $fulfillmentOrder = $fulfillmentOrders[0]['node'];
        $fulfillmentOrderId = $fulfillmentOrder['id'];

        // Step 2: Mutation to create fulfillment
        $mutation = '
        mutation fulfillmentCreateV2($fulfillment: FulfillmentV2Input!) {
            fulfillmentCreateV2(fulfillment: $fulfillment) {
                fulfillment {
                    id
                    status
                }
                userErrors {
                    field
                    message
                }
            }
        }';

        // Prepare variables for fulfillment creation
        $variables = [
            'fulfillment' => [
                'lineItemsByFulfillmentOrder' => [
                    'fulfillmentOrderId' => $fulfillmentOrderId,
                ],
            ],
        ];

        // Send request to create fulfillment
        $response = $this->makeGqlApiRequest($order->shopifyShop, $mutation, $variables);

        // Check for errors in the response
        if (isset($response['data']['fulfillmentCreateV2']['userErrors']) && !empty($response['data']['fulfillmentCreateV2']['userErrors'])) {
            Log::error('Error creating fulfillment', [
                'errors' => $response['data']['fulfillmentCreateV2']['userErrors'],
            ]);
            return false;
        }

        return true;
    }
}
