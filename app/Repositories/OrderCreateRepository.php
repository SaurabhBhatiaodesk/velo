<?php

namespace App\Repositories;

use App\Repositories\BaseRepository;
use App\Repositories\ShippingCodesCheckRepository;
use App\Repositories\AddressesRepository;
use App\Events\Models\Order\Saved as OrderSaved;
use App\Models\Address;
use App\Models\Customer;
use App\Models\Store;
use App\Models\Order;
use App\Models\Polygon;
use App\Traits\SavesFiles;
use App\Enums\DeliveryStatusEnum;
use Log;

class OrderCreateRepository extends BaseRepository
{
    use SavesFiles;

    private $addressesRepo;

    public function __construct()
    {
        $this->addressesRepo = new AddressesRepository();
    }

    /*
     * Prepare the request for saving a new order
     *
     * @param array $inputs
     *
     * @return array
     */
    public function prepareRequest($inputs)
    {
        if (!isset($inputs['delivery'])) {
            $inputs['delivery'] = [];
        }

        if (isset($inputs['store']) && $inputs['store'] instanceof Store) {
            $inputs['store_slug'] = $inputs['store']->slug;
        } else if (isset($inputs['store_slug'])) {
            $inputs['store'] = Store::where('slug', $inputs['store_slug'])->first();
        } else if (isset($inputs['storeAddress']['addressable_slug'])) {
            $inputs['store'] = Store::where('slug', $inputs['storeAddress']['addressable_slug'])->first();
            $inputs['store_slug'] = $inputs['store']->slug;
        }

        $inputs['delivery']['store_slug'] = $inputs['store']->slug;
        $inputs['currency_id'] = $inputs['store']->currency_id;

        if (!isset($inputs['delivery']['polygon_id']) && isset($inputs['polygon_id'])) {
            $inputs['delivery']['polygon_id'] = $inputs['polygon_id'];
        }

        if (isset($inputs['storeAddress'])) {
            if ($inputs['storeAddress'] instanceof Address) {
                $inputs['pickup_address'] = $inputs['storeAddress'];
                $inputs['pickup_address_id'] = $inputs['storeAddress']->id;
                $inputs['delivery']['pickup_address'] = $inputs['storeAddress'];
            } else if (isset($inputs['storeAddress']['id'])) {
                $inputs['pickup_address_id'] = $inputs['storeAddress']['id'];
                $inputs['delivery']['pickup_address'] = $inputs['storeAddress'];
            }
        }

        if (auth()->check()) {
            $inputs['user_id'] = auth()->id();
        }

        if (isset($inputs['customerAddress'])) {
            $inputs['delivery']['shipping_address'] = $inputs['customerAddress'];
            if ($inputs['customerAddress'] instanceof Address) {
                $inputs['shipping_address_id'] = $inputs['customerAddress']->id;
                $inputs['shipping_address'] = $inputs['customerAddress'];
                $inputs['delivery']['shipping_address'] = $inputs['customerAddress'];
            } else if (isset($inputs['customerAddress']['id'])) {
                $inputs['shipping_address'] = $inputs['customerAddress']['id'];
                $inputs['shipping_address_id'] = $inputs['customerAddress']['id'];
                $inputs['delivery']['shipping_address'] = $inputs['customerAddress'];
            }
        }

        if (!isset($inputs['customer'])) {
            $inputs['customer'] = [
                'store_slug' => $inputs['store']->slug,
                'first_name' => $inputs['customerAddress']['first_name'],
                'last_name' => $inputs['customerAddress']['last_name'],
                'phone' => $inputs['customerAddress']['phone'],
            ];

            if (isset($inputs['customerAddress']['email']) && strlen($inputs['customerAddress']['email'])) {
                $inputs['customer']['email'] = $inputs['customerAddress']['email'];
            }

            $customer = Customer::where('store_slug', $inputs['store_slug'])
                ->where('email', $inputs['customer']['email'])
                ->where('phone', $inputs['customer']['phone'])
                ->first();
            if ($customer) {
                $inputs['customer'] = $customer;
            } else {
                $inputs['customer'] = Customer::create($inputs['customer']);
            }
        }

        if (!$inputs['customer'] instanceof Customer && isset($inputs['customer']['id'])) {
            $inputs['customer'] = Customer::find($inputs['customer']['id']);
        }

        foreach (['external_courier_name', 'external_service_id', 'external_service_name', 'dimensions', 'weight'] as $column) {
            if (
                isset($inputs[$column]) && (
                    (
                        is_array($inputs[$column]) &&
                        count($inputs[$column])
                    ) ||
                    strlen(strval($inputs[$column]))
                )
            ) {
                $inputs['delivery'][$column] = $inputs[$column];
            }
        }

        if (isset($inputs['polygon_id'])) {
            $inputs['delivery']['polygon_id'] = $inputs['polygon_id'];
        } else if (isset($inputs['polygon']) && $inputs['polygon'] instanceof Polygon) {
            $inputs['delivery']['polygon'] = $inputs['polygon'];
            $inputs['delivery']['polygon_id'] = $inputs['polygon']->id;
        }

        return $inputs;
    }

    /*
     * Save a new or existing order
     *
     * @param Store|string $store
     * @param array $orderData - an array of \App\Models\Order attributes
     * @param array $deliveryData - an array of \App\Models\Delivery attributes
     * @param array|string $productsData - an array of \App\Models\Products or 'smBag/'mdBag'
     * @param bool $api
     *
     * @return Order
     */
    public function save($orderData = [], $api = false)
    {
        // deliveryData fallback from orderData
        $deliveryData = (isset($orderData['delivery'])) ? $orderData['delivery'] : [];
        $productsData = (isset($orderData['products'])) ? $orderData['products'] : 'smBag';

        // make sure we have a delivery address
        if (!isset($deliveryData['shipping_address'])) {
            if (isset($orderData['shipping_address'])) {
                $deliveryData['shipping_address'] = $orderData['shipping_address'];
            } else if (isset($orderData['customerAddress'])) {
                $deliveryData['shipping_address'] = $orderData['customerAddress'];
            } else {
                return $this->fail('delivery.customerAddressRequired', 422);
            }
        }

        if (!isset($orderData['store'])) {
            if (isset($orderData['store_slug'])) {
                $orderData['store'] = $orderData['store_slug'];
            } else if (isset($deliveryData['store'])) {
                $orderData['store'] = $deliveryData['store'];
            } else if (isset($deliveryData['store_slug'])) {
                $orderData['store'] = $deliveryData['store_slug'];
            }
        }

        // get the store
        if (!$orderData['store'] instanceof Store) {
            $orderData['store'] = Store::where('slug', $orderData['store'])->first();
            if (!$orderData['store']) {
                return $this->fail('store.notFound', 404);
            }
        }

        $orderData['store_slug'] = $orderData['store']->slug;
        $orderData['currency_id'] = $orderData['store']->currency_id;
        $deliveryData['store_slug'] = $orderData['store']->slug;

        // set the source if it's not set
        if (!isset($orderData['source'])) {
            $orderData['source'] = 'manual';
        }

        // get the delivery type
        if (isset($deliveryData['type'])) {
            if ($deliveryData['type'] === 'return') {
                $deliveryData['is_return'] = true;
            } else if ($deliveryData['type'] === 'replacement') {
                $deliveryData['is_replacement'] = true;
            }
        } else {
            $deliveryData['type'] = 'normal';
            if (isset($deliveryData['is_return']) && $deliveryData['is_return']) {
                $deliveryData['type'] = 'return';
            } else if (isset($deliveryData['is_replacement']) && $deliveryData['is_replacement']) {
                $deliveryData['type'] = 'replacement';
            }
        }

        // find existing order
        $order = null;
        foreach (['id', 'name', 'external_id'] as $column) {
            if (!$order && isset($orderData[$column])) {
                $order = Order::where($column, $orderData[$column])->first();
                // if the order exists
                if ($order) {
                    // if the order is still a draft
                    if (
                        !$order->delivery ||
                        $order->delivery->status->value === DeliveryStatusEnum::Placed->value ||
                        $order->delivery->status->value === DeliveryStatusEnum::Updated->value ||
                        $order->delivery->status->value === DeliveryStatusEnum::AcceptFailed->value
                    ) {
                        // use it to fill missing columns on orderData and deliveryData
                        $orderData = array_merge($order->toArray(), $orderData);
                        if ($order->delivery) {
                            $deliveryData = array_merge($order->delivery->toArray(), $deliveryData);
                        }
                    } else {
                        // if the order is no longer a draft, start a new order
                        $order = null;
                    }
                }
            }

            // existing order found
            if ($order) {
                break;
            }
        }

        // get the addresses
        foreach (['pickup_address', 'shipping_address'] as $addressColumn) {
            // if the address is set on orderData but not on deliveryData
            if (isset($orderData[$addressColumn . '_id']) && !isset($deliveryData[$addressColumn])) {
                // get it as an array for $deliveryData
                $orderData[$addressColumn] = Address::find($orderData[$addressColumn . '_id']);
                $orderData[$addressColumn . '_id'] = $orderData[$addressColumn]->id;
                $deliveryData[$addressColumn] = $orderData[$addressColumn]->toArray();
            }
            // if the address is set on deliveryData but not on orderData
            else if (
                !isset($orderData[$addressColumn . '_id']) &&
                isset($deliveryData[$addressColumn]) &&
                isset($deliveryData[$addressColumn]['id'])
            ) {
                // get its id for $orderData
                $orderData[$addressColumn . '_id'] = $deliveryData[$addressColumn]['id'];
                $orderData[$addressColumn] = Address::find($orderData[$addressColumn . '_id']);
            }

            // get the validated version of the address
            if (isset($deliveryData[$addressColumn])) {
                $orderData[$addressColumn] = $this->addressesRepo->get($deliveryData[$addressColumn]);
                if (!$orderData[$addressColumn] instanceof Address) {
                    return $orderData[$addressColumn];
                }
                $orderData[$addressColumn . '_id'] = $orderData[$addressColumn]->id;
            }
        }

        // if $productsData is an array of products
        if (is_array($productsData)) {
            // calculate the total
            $orderData['total'] = 0;
            foreach ($productsData as $product) {
                if (isset($product['prices'][0])) {
                    $orderData['total'] += $product['prices'][0]['price'];
                }
            }
            // if $productsData is a string
        } else {
            if (!isset($orderData['total']) || is_null($orderData['total'])) {
                $orderData['total'] = 500;
            }
            // get the default values for smBag and mdBag
            switch ($productsData) {
                case 'smBag':
                    $deliveryData['weight'] = config('measurments.smBag.' . (($orderData['store']->imperial_units) ? 'imperial' : 'metric') . '.weight');
                    $deliveryData['dimensions'] = config('measurments.smBag.' . (($orderData['store']->imperial_units) ? 'imperial' : 'metric') . '.dimensions');
                    break;
                case 'mdBag':
                    $deliveryData['weight'] = config('measurments.mdBag.' . (($orderData['store']->imperial_units) ? 'imperial' : 'metric') . '.weight');
                    $deliveryData['dimensions'] = config('measurments.mdBag.' . (($orderData['store']->imperial_units) ? 'imperial' : 'metric') . '.dimensions');
                    break;
            }
        }

        // weight fallback
        if (!isset($deliveryData['weight'])) {
            $deliveryData['weight'] = config('measurments.smBag.' . (($orderData['store']->imperial_units) ? 'imperial' : 'metric') . '.weight');
        }
        // dimensions fallback
        if (!isset($deliveryData['dimensions'])) {
            $deliveryData['dimensions'] = config('measurments.smBag.' . (($orderData['store']->imperial_units) ? 'imperial' : 'metric') . '.dimensions');
        }

        $shippingCodesCheckRepository = new ShippingCodesCheckRepository();
        // if a pickup_address wasn't selected
        if (
            !isset($deliveryData['pickup_address']) ||
            is_null($deliveryData['pickup_address']) ||
            (
                (
                    is_array(!$deliveryData['pickup_address']) &&
                    !isset($deliveryData['pickup_address']['id'])
                ) ||
                (
                    $deliveryData['pickup_address'] instanceof Address &&
                    is_null($deliveryData['pickup_address']->id)
                )
            )
        ) {
            // get the optimal store address / service combination
            $optimalResults = $shippingCodesCheckRepository->optimal($deliveryData['shipping_address'], $orderData['store'], 'normal', $api);

            // if an optimal result was found
            if (isset($optimalResults['shippingOption']['polygon'])) {
                $orderData['pickup_address_id'] = $optimalResults['address']->id;
                $orderData['pickup_address'] = $optimalResults['address'];

                $deliveryData['pickup_address'] = $optimalResults['address']->toArray();
                $deliveryData['pickup_address']['id'] = $optimalResults['address']->id;
                $deliveryData['polygon'] = $optimalResults['shippingOption']['polygon'];
                $deliveryData['polygon_id'] = $optimalResults['shippingOption']['polygon']->id;
            }
        }

        if (isset($orderData['polygon_id']) && intval($orderData['polygon_id']) !== 0) {
            unset($deliveryData['polygon']);
            $deliveryData['polygon_id'] = $orderData['polygon_id'];
            unset($orderData['polygon_id']);
        }

        // make sure we have the polygon
        if (!isset($deliveryData['polygon']) && isset($deliveryData['polygon_id'])) {
            $deliveryData['polygon'] = Polygon::find($deliveryData['polygon_id']);
        }

        // get the customer's id
        if (!isset($orderData['customer_id']) || is_null($orderData['customer_id'])) {
            if ($orderData['customer'] instanceof Customer) {
                $orderData['customer_id'] = $orderData['customer']->id;
            } else if (isset($orderData['customer']['id'])) {
                $orderData['customer_id'] = $orderData['customer']['id'];
            } else {
                $orderData['customer_id'] = $deliveryData['shipping_address']['addressable_id'];
            }
        }

        // set declared value
        if (!isset($orderData['declared_value'])) {
            if ($order && !is_null($order->declared_value)) {
                $orderData['declared_value'] = $order->declared_value;
            }
        }

        if (isset($orderData['declared_value']) && !is_numeric($orderData['declared_value'])) {
            unset($orderData['declared_value']);
        }

        // get the user id
        if ((!isset($orderData['user_id']) || is_null($orderData['user_id'])) && auth()->check()) {
            $orderData['user_id'] = auth()->id();
        }

        foreach (['shipping', 'pickup'] as $addressType) {
            if (!isset($order[$addressType . '_address_id'])) {
                if (
                    isset($orderData[$addressType . '_address']) &&
                    $orderData[$addressType . '_address'] instanceof Address
                ) {
                    $orderData[$addressType . '_address_id'] = $orderData[$addressType . '_address']->id;
                } else if (
                    isset($deliveryData[$addressType . '_address']) &&
                    isset($deliveryData[$addressType . '_address']['id'])
                ) {
                    $orderData[$addressType . '_address_id'] = $deliveryData[$addressType . '_address']['id'];
                }
            }
        }

        // create or update order
        if (!$order) {
            // get the unique order name
            $order = new Order($orderData);
            $order->fillName($deliveryData['type']);
            $order->save();
        } else {
            $order->update($orderData);
        }

        if (isset($orderData['invoice'])) {
            $invoiceUrl = $this->saveFile($order->commercialInvoicePath, $orderData['invoice']);
            if (isset($invoiceUrl['fail'])) {
                return $this->fail($invoiceUrl);
            }
            $deliveryData['commercial_invoice_uploaded_at'] = now();
        }

        // if we're trying to change an existing delivery's polygon
        if (
            isset($deliveryData['polygon']) &&
            isset($deliveryData['polygon_id']) &&
            intval($deliveryData['polygon_id']) !== $deliveryData['polygon']->id
        ) {
            // remove the polygon so it would be replaced
            unset($deliveryData['polygon']);
        }

        // if the polygon is not set on the deliveryData
        if (!isset($deliveryData['polygon']) || !$deliveryData['polygon'] instanceof Polygon) {
            // check if an id is set
            if (isset($deliveryData['polygon_id']) && !is_null($deliveryData['polygon_id'])) {
                // try to get the polygon from id
                $deliveryData['polygon'] = Polygon::find($deliveryData['polygon_id']);
                if (!$deliveryData['polygon']) {
                    Log::info('Polygon not found by ID (OrderCreateRepository)', [
                        'id' => $deliveryData['polygon_id'],
                        'order' => $orderData['name'],
                    ]);
                    $deliveryData['polygon_id'] = null;
                }
                // validate the order with the polygon's thresholds
                if (!$deliveryData['polygon']->checkThresholds($deliveryData['weight'], $deliveryData['dimensions'])) {
                    return $this->fail('polygon.invalidWeightOrDimensions', 422);
                }
            }
            // no polygon id was set
            else {
                $deliveryData['polygon_id'] = null;
                // Organize the data into inputs structure
                $inputs = [
                    'store' => $orderData['store'],
                    'storeAddress' => $deliveryData['pickup_address'],
                    'customerAddress' => $deliveryData['shipping_address'],
                    'deliveryType' => $deliveryData['type'],
                    'weight' => $deliveryData['weight'],
                    'dimensions' => $deliveryData['dimensions'],
                ];

                // If no pickup address was selected
                if (!isset($deliveryData['pickup_address'])) {
                    // find the optimal shipping option (polygon/address)
                    $optimalShippingOption = $shippingCodesCheckRepository->optimal($inputs, $api);
                    if (isset($optimalShippingOption['fail'])) {
                        return $optimalShippingOption;
                    }
                    // update the delivery data
                    if (isset($optimalShippingOption['shippingOption']['polygon'])) {
                        $deliveryData['pickup_address'] = $optimalShippingOption['address'];
                        $deliveryData['polygon'] = $optimalShippingOption['shippingOption']['polygon'];
                    }
                } else {
                    // find the best available polygon between the store and the customer addresses
                    $deliveryData['polygon'] = $shippingCodesCheckRepository->bestAvailablePolygon($inputs, $api);
                }

                // if a polygon was found, update the delivery data
                if (!empty($deliveryData['polygon'])) {
                    $deliveryData['polygon_id'] = $deliveryData['polygon']->id;
                }
            }
        }

        // if a polygon was found, create/update the order's delivery
        if (!is_null($deliveryData['polygon_id'])) {
            // set return/replacement flags if not already set
            if ($deliveryData['polygon']->shipping_code->is_return) {
                $deliveryData['is_return'] = true;
                $deliveryData['is_replacement'] = false;
                $deliveryData['type'] = 'return';

                if ($order->pickup_address->addressable_type === 'App\Models\Store') {
                    // switch addresses for returns
                    $addressHelper = $orderData['pickup_address_id'];
                    $order->update([
                        'pickup_address_id' => $order->shipping_address_id,
                        'shipping_address_id' => $order->pickup_address_id,
                    ]);
                }
            } else if ($deliveryData['polygon']->shipping_code->is_replacement) {
                $deliveryData['is_replacement'] = true;
                $deliveryData['is_return'] = false;
                $deliveryData['type'] = 'replacement';
            } else {
                $deliveryData['is_replacement'] = false;
                $deliveryData['is_return'] = false;
                $deliveryData['type'] = 'normal';
            }

            if (!$order->delivery) {
                $order->deliveries()->create($deliveryData);
            } else {
                $order->delivery->update($deliveryData);
            }
        }

        if (isset($productsData) && is_array($productsData)) {
            foreach ($productsData as $product) {
                if (!isset($product['quantity'])) {
                    $product['quantity'] = 1;
                }
                $order->products()->attach($product['id'], [
                    'quantity' => $product['quantity'],
                    'total' => (isset($product['prices'][0])) ? ($product['quantity'] * $product['prices'][0]['price']) : 0,
                    'variation' => isset($product['variation']) ? $product['variation'] : null,
                    'image' => isset($product['image']) ? $product['image'] : null,
                ]);
            }
        }

        OrderSaved::dispatch($order);
        return $order->load(['delivery', 'delivery.polygon', 'delivery.polygon.courier']);
    }

    public function createReturn($inputs)
    {
        $originalOrder = Order::find($inputs['id']);

        if (!$originalOrder) {
            return $this->fail('order.notFound', 404);
        }

        if (!$originalOrder->delivery) {
            return $this->fail('order.noDelivery', 404);
        }

        if ($originalOrder->delivery->status->value !== DeliveryStatusEnum::Delivered->value) {
            return $this->fail('order.returnNotDelivered', 404);
        }

        $originalPickupAddress = $this->addressesRepo->get($originalOrder->pickup_address);
        $originalShippingAddress = $this->addressesRepo->get($originalOrder->shipping_address);

        $name = $originalOrder->name . '_RET';
        $name .= Order::where('name', 'like', $name . '%')->count();

        $order = Order::create([
            'name' => $name,
            'total' => $originalOrder->total,
            'currency_id' => $originalOrder->currency_id,
            'source' => $originalOrder->source,
            'customer_id' => $originalOrder->customer_id,
            'store_slug' => $originalOrder->store_slug,
            'shipping_address_id' => $originalPickupAddress['id'],
            'pickup_address_id' => $originalShippingAddress['id'],
        ]);

        // get the available return polygons for addresses
        $shippingCodesCheckRepository = new ShippingCodesCheckRepository();
        $availableShippingCodes = $shippingCodesCheckRepository->available([
            'store' => ['slug' => $order->store->slug],
            'storeAddress' => $originalShippingAddress,
            'customerAddress' => $originalPickupAddress,
            'deliveryType' => 'return',
        ]);

        if (!count($availableShippingCodes)) {
            return $this->fail('order.noReturnForAddress', 422);
        }

        $order->deliveries()->create([
            'polygon_id' => array_values($availableShippingCodes)[0][0]['polygon']['id'],
            'store_slug' => $order->store_slug,
            'pickup_address' => $originalShippingAddress,
            'shipping_address' => $originalPickupAddress,
            'is_return' => true,
        ]);

        return $order->load(['delivery', 'delivery.polygon']);
    }

    public function replace($inputs)
    {
        $originalOrder = Order::find($inputs['id']);

        if (!$originalOrder) {
            return $this->fail('order.notFound', 404);
        }

        if (!$originalOrder->delivery) {
            return $this->fail('order.noDelivery', 404);
        }

        if ($originalOrder->delivery->status->value !== DeliveryStatusEnum::Delivered->value) {
            return $this->fail('order.returnNotDelivered', 404);
        }

        $originalPickupAddress = $this->addressesRepo->get($originalOrder->pickup_address);
        $originalShippingAddress = $this->addressesRepo->get($originalOrder->shipping_address);

        $name = $originalOrder->name . '_REP';
        $name .= Order::where('name', 'like', $name . '%')->count();

        $order = Order::create([
            'name' => $name,
            'total' => $originalOrder->total,
            'currency_id' => $originalOrder->currency_id,
            'source' => $originalOrder->source,
            'customer_id' => $originalOrder->customer_id,
            'store_slug' => $originalOrder->store_slug,
            'shipping_address_id' => $originalShippingAddress['id'],
            'pickup_address_id' => $originalPickupAddress['id'],
        ]);

        // get the available return polygons for addresses
        $shippingCodesCheckRepository = new ShippingCodesCheckRepository();
        $availableShippingCodes = $shippingCodesCheckRepository->available([
            'store' => ['slug' => $order->store->slug],
            'storeAddress' => $originalPickupAddress,
            'customerAddress' => $originalShippingAddress,
            'deliveryType' => 'replacement',
        ]);

        if (!count($availableShippingCodes)) {
            return $this->fail('order.noReturnForAddress', 422);
        }

        $order->deliveries()->create([
            'polygon_id' => array_values($availableShippingCodes)[0][0]['polygon']['id'],
            'store_slug' => $order->store_slug,
            'pickup_address' => $originalPickupAddress,
            'shipping_address' => $originalShippingAddress,
            'is_replacement' => true,
        ]);

        return $order->load(['delivery', 'delivery.polygon']);
    }
}
