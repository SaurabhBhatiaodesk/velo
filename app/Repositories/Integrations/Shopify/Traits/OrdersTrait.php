<?php

namespace App\Repositories\Integrations\Shopify\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Enums\DeliveryStatusEnum;
use App\Models\Address;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Polygon;
use App\Models\Store;
use App\Models\Currency;
use App\Jobs\Integrations\Shopify\ImportJob;
use App\Repositories\ShippingCodesCheckRepository;

trait OrdersTrait
{
    private function getAllPhoneAlternatives($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        $phones = [$phone];

        if (str_starts_with($phone, '972')) {
            $phone = '+' . $phone;
        }

        if (str_starts_with($phone, '+972')) {
            $phones[] = substr($phone, 1);
            $phones[] = '0' . substr($phone, strlen('+972'));
        } else if (str_starts_with($phone, '0')) {
            $phones[] = '972' . substr($phone, 1);
            $phones[] = '+972' . substr($phone, 1);
        }

        return $phones;
    }

    public function prepareOrderData($shopifyOrder, $shopifyShop, $isWebhook = false)
    {
        $shopifyOrder['source'] = 'shopify';
        $phones = [];
        if (is_null($shopifyOrder['shipping_address'])) {
            $shopifyOrder['shipping_address'] = $shopifyOrder['billing_address'];
        }
        if (!isset($shopifyOrder['customer'])) {
            $shopifyOrder['customer'] = [
                'first_name' => $shopifyOrder['shipping_address']['first_name'],
                'last_name' => $shopifyOrder['shipping_address']['last_name'],
                'phone' => $shopifyOrder['shipping_address']['phone'],
                'email' => $shopifyOrder['email'],
            ];
        }

        foreach ($this->getAllPhoneAlternatives($shopifyOrder['customer']['phone']) as $phone) {
            $customer = Customer::where('first_name', 'LIKE', '%' . $shopifyOrder['customer']['first_name'] . '%')
                ->where('last_name', 'LIKE', '%' . $shopifyOrder['customer']['last_name'] . '%')
                ->where('phone', $phone)
                ->where('store_slug', $shopifyShop->store_slug)
                ->first();

            if ($customer) {
                $shopifyOrder['customer'] = $customer;
                break;
            }
        }

        if (!$shopifyOrder['customer'] instanceof Customer) {
            $shopifyOrder['customer'] = Customer::create([
                'first_name' => $shopifyOrder['customer']['first_name'],
                'last_name' => $shopifyOrder['customer']['last_name'],
                'phone' => $shopifyOrder['customer']['phone'],
                'store_slug' => $shopifyShop->store_slug,
            ]);
        }

        $shopifyOrder['customer_id'] = $shopifyOrder['customer']->id;

        foreach (['shipping_address', 'billing_address'] as $addressCol) {
            // skip if no addressd
            if (!isset($shopifyOrder[$addressCol]) || is_null($shopifyOrder[$addressCol]) || $shopifyOrder[$addressCol] instanceof Address) {
                continue;
            }
            // move shopify_id to the correct column
            if (isset($shopifyOrder[$addressCol]['id'])) {
                $shopifyOrder[$addressCol]['shopify_id'] = $shopifyOrder[$addressCol]['id'];
                unset($shopifyOrder[$addressCol]['id']);
            }
            // get the address
            $shopifyOrder[$addressCol]['addressable_type'] = 'App\\Models\\Customer';
            $shopifyOrder[$addressCol]['addressable_id'] = $shopifyOrder['customer_id'];
            $shopifyOrder[$addressCol] = $this->addressesRepo->get($shopifyOrder[$addressCol]);
            if ($shopifyOrder[$addressCol] instanceof Address) {
                $shopifyOrder[$addressCol . '_id'] = $shopifyOrder[$addressCol]->id;

                if (is_null($shopifyOrder['customer']->phone)) {
                    $shopifyOrder['customer']->update([
                        'phone' => $shopifyOrder[$addressCol]->phone
                    ]);
                }
            }
        }

        // get the polygon
        if (!isset($shopifyOrder['polygon'])) {
            $shopifyOrder['polygon'] = null;
        }

        // if a polygon was selected
        if (
            isset($shopifyOrder['shipping_lines']) &&
            count($shopifyOrder['shipping_lines']) &&
            isset($shopifyOrder['shipping_lines'][0]['code']) &&
            strpos($shopifyOrder['shipping_lines'][0]['code'], 'VELOAPPIO_') !== false &&
            strpos($shopifyOrder['shipping_lines'][0]['code'], '_POL_') !== false
        ) {
            // get the selected polygon
            if (empty($shopifyOrder['polygon']) && strpos($shopifyOrder['shipping_lines'][0]['code'], '_POL_') !== false) {
                // Regular expression to match _POL_ followed by digits
                preg_match('/_POL_(\d+)/', $shopifyOrder['shipping_lines'][0]['code'], $shopifyOrder['polygon_id']);
                $shopifyOrder['polygon_id'] = isset($shopifyOrder['polygon_id'][1]) ? $shopifyOrder['polygon_id'][1] : null;
                if (is_null($shopifyOrder['polygon_id'])) {
                    return $this->fail('Invalid Shopify shipping code', 422, [
                        'code' => $shopifyOrder['shipping_lines'][0]['code'],
                        'store' => $shopifyShop->store_slug,
                        'order' => $shopifyOrder['order_number'],
                    ]);
                }
                $shopifyOrder['polygon'] = Polygon::find($shopifyOrder['polygon_id']);
            }

            // get external service id
            $shopifyOrder['external_service_id'] = null;
            if (!empty($shopifyOrder['polygon']) && strpos($shopifyOrder['shipping_lines'][0]['code'], '_EXT_')) {
                // Regular expression to match _EXT_ followed by digits
                preg_match('/_EXT_(\d+)/', $shopifyOrder['shipping_lines'][0]['code'], $shopifyOrder['external_service_id']);
                $shopifyOrder['external_service_id'] = $shopifyOrder['external_service_id'][1] ?? null;
            }
        }

        if (is_null($shopifyOrder['polygon'])) {
            // if no polygon was selected
            if ($isWebhook) {
                return false;
            }
            // Send to Velo - get optimal polygon
            $shippingCodesCheckRepository = new ShippingCodesCheckRepository();
            $optimalResult = $shippingCodesCheckRepository->optimal($shopifyOrder['shipping_address'], $shopifyShop->store);
            if (isset($optimalResult['fail'])) {
                return false;
            }
            if (isset($optimalResult['shippingOption']['polygon'])) {
                $shopifyOrder['pickup_address'] = $optimalResult['address'];
                $shopifyOrder['polygon'] = $optimalResult['shippingOption']['polygon'];
            }
        }

        // find the closest shipping address in the selected polygon
        if (!isset($shopifyOrder['pickup_address'])) {
            if (!is_null($shopifyOrder['polygon'])) {
                foreach ($shopifyOrder['shipping_address']->organizeByDistance($shopifyShop->store->pickup_addresses) as $pickupAddress) {
                    if ($shopifyOrder['polygon']->checkAddress($pickupAddress, 'pickup_', $shopifyOrder['shipping_address'])) {
                        $shopifyOrder['pickup_address'] = $pickupAddress;
                        break;
                    }
                }
            } else {
                $shopifyOrder['pickup_address'] = $shopifyShop->store->pickup_addresses()->first();
            }
        }

        // products
        /*
        "line_items": [
            {
              "id": 14329880412468,
              "admin_graphql_api_id": "gid://shopify/LineItem/14329880412468",
              "fulfillable_quantity": 1,
              "fulfillment_service": "manual",
              "fulfillment_status": null,
              "gift_card": false,
              "grams": 2000,
              "name": "whatever",
              "price": "33.00",
              "price_set": {
                "shop_money": {
                  "amount": "33.00",
                  "currency_code": "ILS"
                },
                "presentment_money": {
                  "amount": "33.00",
                  "currency_code": "ILS"
                }
              },
              "product_exists": true,
              "product_id": 8807283392820,
              "properties": [],
              "quantity": 1,
              "requires_shipping": true,
              "sku": "",
              "taxable": true,
              "title": "whatever",
              "total_discount": "0.00",
              "total_discount_set": {
                "shop_money": {
                  "amount": "0.00",
                  "currency_code": "ILS"
                },
                "presentment_money": {
                  "amount": "0.00",
                  "currency_code": "ILS"
                }
              },
              "variant_id": 47189269938484,
              "variant_inventory_management": "shopify",
              "variant_title": null,
              "vendor": "Velo Originals",
              "tax_lines": [
                {
                  "channel_liable": false,
                  "price": "5.61",
                  "price_set": {
                    "shop_money": {
                      "amount": "Over 9 levels deep, aborting normalization",
                      "currency_code": "Over 9 levels deep, aborting normalization"
                    },
                    "presentment_money": {
                      "amount": "Over 9 levels deep, aborting normalization",
                      "currency_code": "Over 9 levels deep, aborting normalization"
                    }
                  },
                  "rate": 0.17,
                  "title": "VAT"
                }
              ],
              "duties": [],
              "discount_allocations": []
            }
          ],
        */

        return $shopifyOrder;
    }


    public function cancelOrder($params)
    {
        $order = Order::where('shopify_id', $params['id'])
            ->where('store_slug', $params['store_slug'])
            ->first();

        if ($order) {
            switch ($order->delivery->status) {
                case DeliveryStatusEnum::Placed->value:
                case DeliveryStatusEnum::Updated->value:
                case DeliveryStatusEnum::AcceptFailed->value:
                case DeliveryStatusEnum::PendingAccept->value:
                    if (!$order->delivery->update(['status' => DeliveryStatusEnum::Cancelled])) {
                        return ['success' => false];
                    }
                    ;
                    break;
            }
        }

        return ['success' => true];
    }

    // JOB JOB JOB JOB JOB JOB JOB JOB  JOB JOB JOB JOB JOB JOB JOB JOB
    public function saveOrder($params, $status = DeliveryStatusEnum::Placed, $isWebhook = false)
    {
        // get store
        $store = Store::where('slug', $params['store_slug'])->first();
        // update customer info
        $params = $this->prepareOrderData($params, $store->shopifyShop, $isWebhook);
        if (!$params) {
            return false;
        }

        if (!$params['polygon']) {
            Log::debug('validation failed - no polygon found', [
                'shipping_code' => $params['shipping_lines'][0]['code'],
            ]);
            return false;
        }

        if (!isset($params['pickup_address']) && isset($params['pickup_address_id'])) {
            $params['pickup_address'] = Address::where('id', $params['pickup_address_id'])->first();
        }

        if (
            !isset($params['pickup_address']) ||
            is_null($params['pickup_address']) ||
            !$params['polygon']->checkAddress($params['shipping_address'], 'dropoff_', $params['pickup_address'])
        ) {
            $params['pickup_address'] = false;
            foreach ($params['shipping_address']->organizeByDistance($store->addresses) as $pickupAddress) {
                if ($params['polygon']->checkAddress($pickupAddress, 'pickup_', $params['shipping_address'])) {
                    $params['pickup_address'] = $pickupAddress;
                    break;
                }
            }

            if (!$params['pickup_address']) {
                Log::debug('validation failed - out of specified polygon service area', [
                    'polygon' => $params['polygon']->toArray(),
                    'shippingAddress' => $params['shipping_address'],
                    'order' => $params['name']
                ]);
                return false;
            }
        }

        // validate line items
        if (!isset($params['line_items'])) {
            // if no items were ordered
            Log::debug('validation failed - no line items', [
                'store_slug' => $params['store_slug'],
                'order' => $params['name']
            ]);
            return false;
        }

        if ($store->validate_inventory) {
            foreach ($params['line_items'] as $i => $lineItem) {
                // if an item is out of stock
                if ($lineItem['fulfillable_quantity'] < $lineItem['quantity']) {
                    Log::debug('validation failed - line item out of stock', [
                        'store_slug' => $params['store_slug'],
                        'order' => $params['name'],
                        'lineItem' => $lineItem,
                    ]);
                    return false;
                }
            }
        }

        // preparer order data
        $orderCurrency = Currency::where('iso', strtoupper($params['currency']))->first();
        if (!$orderCurrency) {
            $orderCurrency = $store->currency;
        }

        $orderData = [
            'total' => $params['total_price'],
            'currency_id' => $orderCurrency->id,
            'pickup_address_id' => $params['pickup_address']->id,
            'name' => 'V' . Str::snake(strtolower($store->slug)) . 'S' . $params['order_number'],
            'note' => $params['note'],
            'source' => 'shopify',
            'billing_address_id' => (!$params['billing_address']) ? null : $params['billing_address']->id,
            'shipping_address_id' => $params['shipping_address']->id,
            'customer_id' => $params['customer']->id,
            'user_id' => $store->user->id,
            'store_slug' => $store->slug,
            'shopify_id' => $params['id'],
            'external_id' => $params['name'],
        ];

        $deliveryData = [
            'pickup_address' => $params['pickup_address']->toArray(),
            'shipping_address' => $params['shipping_address']->toArray(),
            'billing_address' => (!$params['billing_address']) ? null : $params['billing_address']->toArray(),
            'external_service_id' => isset($params['external_service_id']) ? $params['external_service_id'] : null,
            'status' => $status,
            'cancelled_at' => ($params['cancelled_at'] && strlen($params['cancelled_at'])) ? $params['cancelled_at'] : null,
            'ready_at' => ($params['processed_at'] && strlen($params['processed_at'])) ? $params['processed_at'] : null,
            'store_slug' => $store->slug,
        ];

        if (!is_null($params['polygon'])) {
            $deliveryData['polygon_id'] = $params['polygon']->id;
        }

        // save order
        $order = Order::where('shopify_id', $orderData['shopify_id'])->first();
        // if the order doesn't exist
        if (!$order) {
            // create the order
            $order = Order::create($orderData);
            if (isset($deliveryData['polygon_id'])) {
                // create the delivery
                $order->deliveries()->create($deliveryData);
            }
            // if the order exists
        } else {
            // if the order has a delivery that's already transmitted
            if (!is_null($order->delivery) && !is_null($order->delivery->remote_id)) {
                // return the order
                return $order;
            }

            // otherwise, update the order
            $order->update($orderData);
            // if the order has a delivery and a polygon was selected
            if (!$order->delivery && isset($deliveryData['polygon_id'])) {
                // create the delivery
                $order->deliveries()->create($deliveryData);
                // if the order has a delivery (before transmit)
            } else {
                // don't update the status
                unset($deliveryData['status']);
                // update the delivery
                $order->delivery->update($deliveryData);
            }
        }

        foreach ($params['line_items'] as $i => $lineItem) {
            $this->saveProduct($lineItem, $order->store_slug, $order->currency, $order);
        }

        return $order->load('delivery', 'customer', 'pickup_address', 'shipping_address');
    }


    public function getOrdersInfo($shopifyShop, $orderIds)
    {
        if (!count($orderIds)) {
            return false;
        }

        $results = [];

        $orderIds = array_values($orderIds);
        if (!count($orderIds)) {
            // all orders already imported
            return $results;
        }

        $restApiClient = $this->restApiClient($shopifyShop);
        $ordersResponse = json_decode($restApiClient->get((count($orderIds) === 1) ? 'orders/' . $orderIds[0] . '.json' : 'orders.json?ids=' . implode(',', $orderIds))->body(), true);

        if (isset($ordersResponse['errors'])) {
            return $this->fail($ordersResponse['errors'], 404);
        }
        if (!isset($ordersResponse['orders'])) {
            if (isset($ordersResponse['order'])) {
                $ordersResponse['orders'] = [$ordersResponse['order']];
                unset($ordersResponse['order']);
            } else {
                return $this->fail('invalid response from Shopify API', 404);
            }
        }

        foreach ($ordersResponse['orders'] as $shopifyOrder) {
            $orderData = $this->prepareOrderData($shopifyOrder, $shopifyShop);
            if ($orderData) {
                ImportJob::dispatch($orderData, $shopifyShop);
            }
        }
        return $results;
    }

    public function fulfillOrder($params)
    {
        $order = Order::where('store_slug', $params['store_slug'])
            ->where('shopify_id', $params['id'])
            ->first();
        //
        // if ($order) {
        //   $order->delivery->update([
        //     'status' => DeliveryStatusEnum::Delivered->value
        //   ]);
        // }

        return $order;
    }


    public function addFromAdmin($shopifyShop, $orderIds)
    {
        if (!count($orderIds)) {
            return false;
        }

        if (count($orderIds) === 1) {
            $url = 'orders/' . $orderIds[0] . '.json';
            $resultsField = 'order';
        } else {
            $url = 'orders.json?ids=' . implode(',', $orderIds);
            $resultsField = 'orders';
        }

        $restApiClient = $this->restApiClient($shopifyShop);
        $ordersResponse = json_decode($restApiClient->get($url)->body(), true);
        if (isset($ordersResponse['errors'])) {
            return $this->fail($ordersResponse['errors'], 404);
        }
        if (!isset($ordersResponse['orders'])) {
            if (isset($ordersResponse['order'])) {
                $ordersResponse['orders'] = [$ordersResponse['order']];
            } else {
                return $this->fail('invalid response from Shopify API', 404);
            }
        }
        $orders = [];
        $polygons = Polygon::with('courier', 'shipping_code')->where('active', true)->get();

        foreach ($polygons as $i => $polygon) {
            if (!is_null($polygon->store_slug) && $polygon->store_slug !== $shopifyShop->store->slug) {
                $polygons->forget($i);
            }
            if (
                $shopifyShop->store->plan_subscription &&
                !is_null($polygon->plan_id) &&
                $polygon->plan_id !== $shopifyShop->store->plan_subscription->subscribable_id
            ) {
                $polygons->forget($i);
            }
        }

        foreach ($ordersResponse['orders'] as $shopifyOrder) {
            // saves customer/address or gets existing from db
            $shopifyOrder = $this->prepareOrderData($shopifyOrder, $shopifyShop);
            if (!$shopifyOrder) {
                continue;
            }
            foreach ($shopifyShop->store->addresses as $storeAddress) {
                $polygon = new Polygon();
                $polygon = $polygon->getBetweenAddresses($storeAddress, $shopifyOrder['shipping_address'], $polygons)->first();
                if ($polygon) {
                    $orders[] = $this->saveOrder(array_merge($shopifyOrder, [
                        'store_slug' => $shopifyShop->store_slug,
                        'pickup_address_id' => $storeAddress->id,
                        'polygon' => $polygon,
                        // shipping lines is a redundancy for polygon here.
                        'shipping_lines' => [['code' => 'VELOAPPIO_STANDARD_POL_' . $polygon->id]],
                    ]));
                    break;
                }
            }
        }
        return $orders;
    }
    
        /**
     * Replace an order by applying store credit via Shopify API.
     *
     * This function fetches customer and order data, ensures the customer exists in Shopify (by ID),
     * and applies store credit to the customer's account via a GraphQL mutation. 
     * If the customer is not found, a new Shopify customer is created based on the email.
     *
     * @param int $order_id The ID of the order to replace.
     * @param float $price The amount of store credit to apply (default is 0).
     * @return \Illuminate\Http\JsonResponse The response containing success or error message.
     */
    public function orderReplace($order_id, $price = 0, $variants = [])
    {
        try {

            // Eager load customer, currency, store, and products to avoid multiple queries
            $order = Order::with(['customer', 'currency', 'store', 'products'])->findOrFail($order_id);

            $shopifyShop = $order->store->shopifyShop;
            $currency = $order->currency->iso;
            $orderCustomer = $order->customer;


            // Fetch Shopify customer ID or create one if not present
            $customer_id = $orderCustomer->shopify_id ?? $this->getShopifyCustomerId($shopifyShop, $orderCustomer->email);
            if (!$customer_id) {
                return $this->fail("Customer ID not found.");
                //return response()->json(['error' => 'Customer ID not found.'], 404);
            }

            // Update the customer with Shopify ID if not already set
            if (empty($orderCustomer->shopify_id)) {
                $orderCustomer->update(['shopify_id' => $customer_id]);
            }

            // Step 1: Handle store credit (only if price is greater than 0)
            if ($price > 0) {
                $storeCreditMutation = $this->generateStoreCreditMutation();
                $storeCreditVariables = $this->prepareCreditVariables($customer_id, $currency, $price);

                // Execute GraphQL API request for store credit
                $storeCreditResponse = $this->makeGqlApiRequest($shopifyShop, $storeCreditMutation, $storeCreditVariables);
                if (isset($storeCreditResponse['errors']) && !empty($storeCreditResponse['errors'])) {
                    return $this->fail("Error processing store credit.");
                    //return response()->json(['error' => 'Error processing store credit.'], 500);
                }
            }

            // Step 2: Handle order creation
            $orderCreateMutation = $this->orderCreateMutation();
            $orderCreateVariables = $this->orderCreateVariables($customer_id, $currency, $order_id, $variants);


            // Execute GraphQL API request for order creation
            $orderCreateResponse = $this->makeGqlApiRequest($shopifyShop, $orderCreateMutation, $orderCreateVariables);
            if (isset($orderCreateResponse['errors']) && !empty($orderCreateResponse['errors'])) {
                return $this->fail("Error processing order creation.");
                //return response()->json(['error' => 'Error processing order creation.'], 500);
            }

            // Return combined response or success message
            return response()->json(['success' => 'Order replacement and store credit processed successfully.'], 200);
        } catch (ModelNotFoundException $e) {
            Log::error("Error: Customer, Order, or Currency not found. Order ID: {$order_id}.", [
                'error' => $e->getMessage()
            ]);
            return $this->fail("Data not found.");
            //return response()->json(['error' => 'Data not found.'], 404);
        } catch (\Exception $e) {
            Log::error("Unexpected error in orderReplace for Order ID: {$order_id}.", [
                'error' => $e->getMessage()
            ]);
            return $this->fail("An error occurred while processing the request.");
            //return response()->json(['error' => 'An error occurred while processing the request.'], 500);
        }
    }

    // Helper function to get Shopify Customer ID via GraphQL if not found in local DB
    private function getShopifyCustomerId($shopifyShop, $email)
    {
        $query = 'query GetCustomerId($email: String!) {
            customers(first: 5, query: $email) {
                edges {
                    node {
                        id
                    }
                }
            }
        }';

        // Structure the variables as an associative array
        $variables = [
            'email' => $email, // Pass the email variable here
        ];

        // Execute the GraphQL request
        $response = $this->makeGqlApiRequest($shopifyShop, $query, $variables);
        
        // Check if the customer was found and return the Shopify customer ID
        return optional($response['data']['customers']['edges'][0]['node']['id'])->replace("gid://shopify/Customer/", null);
    }

    // Helper function to generate the store credit mutation query string
    private function generateStoreCreditMutation()
    {
        return '
            mutation storeCreditAccountCredit($id: ID!, $creditInput: StoreCreditAccountCreditInput!) {
                storeCreditAccountCredit(id: $id, creditInput: $creditInput) {
                    storeCreditAccountTransaction {
                        amount {
                            amount
                            currencyCode
                        }
                        account {
                            id
                            balance {
                                amount
                                currencyCode
                            }
                        }
                    }
                    userErrors {
                        message
                        field
                    }
                }
            }';
    }

    // Helper function to prepare variables for the store credit mutation
    private function prepareCreditVariables($customerId, $currency, $price)
    {
        return [
            'id' => "gid://shopify/Customer/{$customerId}",
            'creditInput' => [
                'creditAmount' => [
                    'amount' => $price,
                    'currencyCode' => $currency
                ]
            ]
        ];
    }

    // Helper function to create the order mutation query string
    private function orderCreateMutation()
    {
        return '
            mutation OrderCreate($order: OrderCreateOrderInput!, $options: OrderCreateOptionsInput) {
            orderCreate(order: $order, options: $options) {
                userErrors {
                field
                message
                }
                order {
                id
                displayFinancialStatus
                shippingAddress {
                    lastName
                    address1
                    city
                    provinceCode
                    countryCode
                    zip
                }
                billingAddress {
                    lastName
                    address1
                    city
                    provinceCode
                    countryCode
                    zip
                }
                customer {
                    id
                }
                }
            }
            }';
    }

    // Helper function to prepare the variables for the order creation mutation
    private function orderCreateVariables($customerId, $currency, $order_id, $variants)
    {
        // Get the order's products and customer data from the $order object
        $order = Order::with(['products', 'customer'])->findOrFail($order_id);
        
        // Debugging: Check the order's data (if needed)

        // Prepare the lineItems array with the selected variants for replacement
        $lineItems = [];

        // Loop through the provided variants and create a line item for each one
        foreach ($variants as $variant) {
            // Ensure variant details (e.g., ID, quantity, price) are correctly passed
            $lineItems[] = [
                'variantId' => "gid://shopify/ProductVariant/{$variant['variant_id']}",  // Pass the variant_id
                'quantity' => $variant['quantity'],  // Quantity of the selected variant
                'priceSet' => [
                    'shopMoney' => [
                        'amount' => $variant['price'],  // Price for the replacement item
                        'currencyCode' => $currency,   // Currency for the item
                    ]
                ],
            ];
        }

        // Add customer information to the order variables
        $customer = $order->customer;

        // Return the updated order variables with dynamic lineItems and customer info
        return [
            'order' => [
                'currency' => $currency,
                'tags' => 'Replacement for order ' . $order->shopify_id,  // Add the original order ID as a tag
                'lineItems' => $lineItems,  // Include the prepared line items for replacements
                "customerId"=> "gid://shopify/Customer/{$order->customer->shopify_id}",
            ]
        ];
    }




    
}
