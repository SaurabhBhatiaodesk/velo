<?php

namespace App\Http\Controllers\Integrations;

use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use Illuminate\Http\Request;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Controllers\Controller;
use App\Models\ApiUser;
use App\Models\Address;
use App\Models\Order;
use App\Repositories\ShippingCodesCheckRepository;
use App\Repositories\AddressesRepository;
use App\Repositories\OrderCreateRepository;

class BaseController extends Controller
{
    /**
     * Format an address to return only what's necessary
     *
     * @param Address $address
     *
     * @return array
     */
    protected function stripAddressForResponse($address)
    {
        if ($address instanceof Address) {
            return [
                'first_name' => $address->first_name,
                'last_name' => $address->last_name,
                'street' => $address->street,
                'number' => $address->number,
                'line2' => $address->line2,
                'city' => $address->city,
                'state' => $address->state,
                'zipcode' => $address->zipcode,
                'country' => $address->country,
                'phone' => $address->phone,
                'latitude' => $address->latitude,
                'longitude' => $address->longitude,
            ];
        }
        return $address;
    }

    /**
     * Authenticate the request and get the api user
     *
     * @param Request $request
     * @param bool $skipHmacValidation
     *
     * @return ApiUser|array [fail => true, error => error message, code => error code]
     */
    protected function getApiUser($request, $skipHmacValidation = false)
    {
        $apiKey = $request->header('X-Velo-Api-Key');
        $apiUser = ApiUser::where('key', $apiKey)->first();
        if (!$apiUser) {
            return [
                'fail' => true,
                'message' => 'invalidCredentials',
                'code' => 401,
            ];
        }
        if (!$apiUser->active) {
            return [
                'fail' => true,
                'message' => 'forbidden',
                'code' => 403,
            ];
        }
        if (!$skipHmacValidation) {
            if (
                !$apiUser->validateHmac(
                    $request->bearerToken() . $apiKey,
                    $request->header('X-Velo-Hmac')
                )
            ) {
                return [
                    'fail' => true,
                    'message' => 'invalidHmac',
                    'code' => 401,
                ];
            }
        }
        return $apiUser;
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {
        $apiUser = $this->getApiUser($request, true);
        if (isset($apiUser['fail'])) {
            return $this->respond($apiUser['message'], $apiUser['code']);
        }

        try {
            $credentials = [
                'email' => $request->input('email'),
                'password' => $request->input('password'),
            ];

            $token = auth()->setTTL(config('jwt.enterprise_ttl'))->attempt($credentials);
            if (!$token) {
                return response()->json(['message' => 'unauthorized'], 401);
            }

            if (is_null(auth()->user()->email_verified_at)) {
                return response()->json(['message' => 'emailUnverified'], 401);
            }

            return $this->respondWithToken($token);
        } catch (JWTException $e) {
            return response()->json(['message' => 'tokenFail'], 500);
        }
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh(Request $request)
    {
        $apiUser = $this->getApiUser($request);
        if (isset($apiUser['fail'])) {
            return $this->respond($apiUser['message'], $apiUser['code']);
        }
        $token = false;
        try {
            $token = auth()->setTTL(config('jwt.enterprise_ttl'))->refresh();
        } catch (\PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException $e) {
            return $this->respond(['message' => 'tokenExpired'], 401);
        }

        if (!$token) {
            return $this->respond(['message' => 'invalidCredentials'], 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Respond with token and user info
     * Same as normal but without $user->load('stores');
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token, $data = [])
    {
        $user = auth()->user();
        return response()->json([
            'jwt' => $token,
            'user' => $user,
            'expiry' => 1440,
            'data' => $data
        ]);
    }

    /**
     * Get available shipping services (without request validation)
     *
     * @return array
     */
    protected function getAvailableShippingMethods(Request $request)
    {
        $apiUser = $this->getApiUser($request);
        if (isset($apiUser['fail'])) {
            return $this->respond($apiUser['message'], $apiUser['code']);
        }
        app()->setLocale($apiUser->store->user->locale->iso);
        $inputs = $request->all();
        $inputs['store'] = $apiUser->store->toArray();
        $addressesRepo = new AddressesRepository();

        if (!isset($inputs['customerAddress']['addressable_type'])) {
            $inputs['customerAddress']['addressable_type'] = 'App\\Models\\Customer';
        }

        $inputs['customerAddress'] = $addressesRepo->get($inputs['customerAddress'], 'en_US', true);
        $inputs['storeAddress'] = $apiUser->store->pickup_addresses()->first();

        $shippingCodesCheckRepository = new ShippingCodesCheckRepository();
        $availableShippingCodes = $shippingCodesCheckRepository->available($inputs, true);
        $total = 0;
        if (isset($inputs['products'])) {
            foreach ($inputs['products'] as $product) {
                $total += floatVal($product['price']);
            }
        }

        $results = [];
        $rates = $apiUser->store->getRates($total);
        if (count($rates)) {
            app()->setLocale($addressesRepo->guessLocale($inputs['customerAddress']->toArray())->iso);
            $closestDeliveryDate = $apiUser->store->getClosestDeliveryDate();
            foreach ($availableShippingCodes as $shippingCodeCode => $shippingOptions) {
                foreach ($shippingOptions as $i => $shippingOption) {
                    if (isset($rates[$shippingCodeCode])) {
                        foreach ($shippingOption['prices'] as $i => $price) {
                            $shippingOption['prices'][$i] = [
                                'price' => $price['price'],
                                'slug' => $price['slug'],
                                'currency_id' => $price['currency_id'],
                            ];
                        }

                        $serviceName = __('shipping_codes.' . $shippingOption['polygon']->shipping_code->code, ['date' => $closestDeliveryDate->format('d/m/o')]);
                        if (
                            isset($shippingOption['pickup_station']) &&
                            isset($shippingOption['pickup_station']['address']) &&
                            strlen($shippingOption['pickup_station']['address'])
                        ) {
                            $serviceName .= ' - ' . (isset($shippingOption['external_service_name']) ? $shippingOption['external_service_name'] : $shippingOption['pickup_station']['name']);
                        }

                        $results[] = [
                            'polygon_id' => $shippingOption['polygon']->id,
                            'pickup_max_days' => $shippingOption['polygon']->pickup_max_days,
                            'dropoff_max_days' => $shippingOption['polygon']->dropoff_max_days,
                            'courier' => $shippingOption['polygon']->courier->name,
                            'service_name' => $serviceName,
                            'description' => __('shipping_codes.' . $shippingOption['polygon']->shipping_code->code . '_desc'),
                            'rate' => $rates[$shippingCodeCode],
                            'external_service_id' => $shippingOption['external_service_id'] ?? null,
                            'external_service_name' => $shippingOption['external_service_name'] ?? null,
                            'shipping_code' => [
                                'code' => $shippingCodeCode,
                                'initial_free_km' => $shippingOption['polygon']->shipping_code->initial_free_km,
                                'prices' => $shippingOption['prices'],
                            ]
                        ];
                    }
                }
            }
        }
        return $results;
    }

    protected function saveOrder($request, $apiUser = null, $orderData = null)
    {
        if (is_null($apiUser)) {
            $apiUser = $this->getApiUser($request);
            if (isset($apiUser['fail'])) {
                return $apiUser;
            }
        }

        if (is_null($orderData)) {
            $orderData = $request->all();
        }
        if (isset($orderData['name']) && strpos($orderData['name'], $apiUser->key . '_') !== false) {
            $search = $apiUser->key . '_';
            $position = strpos($orderData['name'], $search);
            $orderData['name'] = substr_replace($orderData['name'], $apiUser->store_slug, $position, strlen($search));
        }
        $orderData['customer'] = $apiUser->store->customers()->where('phone', $orderData['customerAddress']['phone'])->first();
        $orderData['store'] = $apiUser->store;
        if (
            !$orderData['customer'] || (
                $orderData['customer']->first_name !== $orderData['customerAddress']['first_name'] &&
                $orderData['customer']->last_name !== $orderData['customerAddress']['last_name']
            )
        ) {
            $orderData['customer'] = $apiUser->store->customers()->create([
                'first_name' => $orderData['customerAddress']['first_name'],
                'last_name' => $orderData['customerAddress']['last_name'],
                'phone' => $orderData['customerAddress']['phone'],
                'email' => isset($orderData['customerAddress']['email']) ? $orderData['customerAddress']['email'] : null,
            ]);
        }

        $addressesRepo = new AddressesRepository();
        // Find the existing customer's matching address, or create it if it doesn't exist.
        $orderData['customerAddress'] = $addressesRepo->get(array_merge($orderData['customerAddress'], [
            'addressable_type' => 'App\\Models\\Customer',
            'addressable_id' => $orderData['customer']->id,
        ]), 'en_US');
        if (!$orderData['customerAddress'] instanceof Address) {
            return $orderData['customerAddress'];
        }

        // If store address is provided but not an Address instance
        if (!empty($orderData['storeAddress']) && !$orderData['storeAddress'] instanceof Address) {
            // Add name fallback for store address name
            if (!isset($orderData['storeAddress']['first_name']) || !isset($orderData['storeAddress']['last_name'])) {
                $orderData['storeAddress']['first_name'] = (!is_null($apiUser->store->first_name)) ? $apiUser->store->first_name : $apiUser->store->pickup_addresses()->first()->first_name;
                $orderData['storeAddress']['last_name'] = (!is_null($apiUser->store->last_name)) ? $apiUser->store->last_name : $apiUser->store->pickup_addresses()->first()->last_name;
            }
            // Find the existing store's matching address
            $orderData['storeAddress'] = $addressesRepo->get(array_merge($orderData['storeAddress'], [
                'addressable_type' => 'App\\Models\\Store',
                'addressable_slug' => $apiUser->store->slug,
            ]), 'en_US', true, true);

            // If the store address is not found, unset it
            if (isset($orderData['storeAddress']['fail'])) {
                unset($orderData['storeAddress']);
            }
        }

        foreach ($orderData['products'] as $i => $productData) {
            // find or create the matching product
            $product = $apiUser->store->products()->where('code', $productData['code'])->first();
            if (!$product) {
                $product = $apiUser->store->products()->create([
                    'name' => $productData['name'],
                    'code' => $productData['code'],
                ]);
            }
            // add the product id to the data
            $orderData['products'][$i]['id'] = $product->id;

            // find the matching price
            $price = $product->prices()->where('currency_id', $apiUser->store->currency_id)->first();
            if (!$price) {
                $price = $product->prices()->create([
                    'price' => $productData['price'],
                    'currency_id' => $apiUser->store->currency_id,
                ]);
            }
            if ($price->price !== floatVal($productData['price'])) {
                $price->update([
                    'price' => $productData['price'],
                ]);
            }
            $orderData['products'][$i]['prices'] = [$price->toArray()];
        }

        if ($apiUser) {
            $orderData['source'] = $apiUser->slug;
        }

        if (
            !isset($orderData['storeAddress']) ||
            (
                !isset($orderData['polygon']) &&
                !isset($orderData['polygon_id'])
            )
        ) {
            $shippingCodesCheckRepository = new ShippingCodesCheckRepository();
            $optimalResult = $shippingCodesCheckRepository->optimal($orderData['customerAddress'], $apiUser->store);
            if (isset($optimalResult['shippingOption']['polygon'])) {
                $orderData['storeAddress'] = $optimalResult['address'];
                $orderData['polygon'] = $optimalResult['shippingOption']['polygon'];
                $orderData['polygon_id'] = $optimalResult['shippingOption']['polygon']->id;
            }
        }

        $orderCreateRepo = new OrderCreateRepository();
        $orderData = $orderCreateRepo->prepareRequest($orderData);
        return $orderCreateRepo->save($orderData);
    }

    protected function saveMultipleOrders($request)
    {
        $apiUser = $this->getApiUser($request);
        if (isset($apiUser['fail'])) {
            return $apiUser;
        }
        $inputs = $request->all();
        $orders = [
            'new' => [],
            'existing' => [],
            'failed' => []
        ];

        if (isset($inputs['orders']) && count($inputs['orders'])) {
            foreach ($inputs['orders'] as $i => $order) {
                $order['store_slug'] = $apiUser->store_slug;
                $existing = (isset($order['external_id']) && Order::where('external_id', $order['external_id'])->count() > 0);
                $order = $this->saveOrder($request, $apiUser, $order);
                if (isset($order['fail'])) {
                    $orders['failed'][] = $order;
                } else {
                    $orders[$existing ? 'existing' : 'new'][] = [
                        'name' => $order->name,
                        'total' => $order->total,
                        'status' => $order->delivery ? $order->delivery->status : 'placed',
                        'shipping_code' => $order->delivery ? $order->delivery->polygon->shipping_code->code : null,
                        'courier' => $order->delivery ? $order->delivery->polygon->courier->name : null,
                        'external_id' => $order->external_id,
                        'shipping_address' => $this->stripAddressForResponse($order->shipping_address),
                        'pickup_address' => $this->stripAddressForResponse($order->pickup_address),
                        'billing_address' => $this->stripAddressForResponse($order->billing_address),
                    ];
                }
            }
        }

        return $orders;
    }
}
