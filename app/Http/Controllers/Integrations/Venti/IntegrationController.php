<?php

namespace App\Http\Controllers\Integrations\Venti;

use App\Http\Requests\Venti\FindOrderRequest;
use App\Http\Requests\Venti\CheckReturnInfoRequest;
use App\Http\Requests\Venti\SavePaymentInfoRequest;
use App\Http\Requests\Venti\CreateOrderRequest;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\ApiUser;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Polygon;
use App\Models\VentiCall;
use App\Repositories\OrderCreateRepository;
use App\Repositories\AddressesRepository;
use App\Repositories\ShippingCodesCheckRepository;
use Illuminate\Support\Str;

class IntegrationController extends Controller
{
    /**
     * Gets the ApiUser from the request header
     * @param \Illuminate\Http\Request $request
     * @return ApiUser | array(fail)
     */
    protected function getApiUser($request)
    {
        $apiKey = $request->header('X-Velo-Api-Key');
        if (!$apiKey) {
            return [
                'fail' => true,
                'message' => 'invalidApiKey',
                'code' => 401,
            ];
        }
        $apiUser = ApiUser::where('slug', 'venti')->where('key', $apiKey)->first();
        if (!$apiUser) {
            return [
                'fail' => true,
                'message' => 'notConnected',
                'code' => 403,
            ];
        }
        return $apiUser;
    }

    /**
     * Gets the VentiCall from the request header
     * @param \Illuminate\Http\Request $request
     * @return VentiCall | array(fail)
     */
    private function getCall($request)
    {
        $call = VentiCall::find($request->header('X-Velo-Returns-Call-Id'));
        if (!$call) {
            return [
                'fail' => true,
                'message' => 'callNotFound',
                'code' => 404,
            ];
        }
        if (!$call->validateNonce($request->header('X-Velo-Returns-Nonce'))) {
            return [
                'fail' => true,
                'message' => 'invalidNonce',
                'code' => 403,
            ];
        }
        return $call;
    }

    /**
     * @param string $deliveryType
     * @param Address $customerAddress
     * @return \Illuminate\Support\Collection <Polygon>
     */
    private function getAvailablePolygonsQuery($customerAddress, $deliveryType)
    {
        $polygons = new Polygon();
        return $polygons->getForAddress($customerAddress, 'pickup_', Polygon::where('active', true)
            ->whereHas('shipping_code', function ($query) use ($deliveryType) {
                if ($deliveryType === 'replacement') {
                    $query->where('is_replacement', true);
                } else {
                    $query->where('is_return', true);
                }
            }));
    }

    /**
     * loads the venti app view inside the iframe
     * @param string $apiKey
     * @return string|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function index($apiKey)
    {
        $apiUser = ApiUser::where('key', $apiKey)->first();
        if (!$apiUser || !$apiUser->active) {
            return '401 Unauthorized';
        }
        $venti = file_get_contents(public_path('/venti/index.html'));
        $venti = str_replace('###VELO_VENTI_API_KEY###', $apiKey, $venti);
        $venti = str_replace('###VELO_VENTI_SETTINGS###', json_encode($apiUser->settings), $venti);
        return $venti;
    }

    /**
     * Finds the order by name, store and customer phone number
     * Returns the order, customer, addresses and call
     * @param FindOrderRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function findOrder(FindOrderRequest $request)
    {
        $apiUser = $this->getApiUser($request);
        if (isset($apiUser['fail'])) {
            return $this->fail($apiUser);
        }

        $phone = $request->phone;
        if (str_starts_with($phone, '+972')) {
            $phone = substr($phone, 4);
        } else if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        } else if (str_starts_with($phone, '972')) {
            $phone = substr($phone, 3);
        } else if (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }

        $order = Order::where('name', $request->orderName)
            ->where('store_slug', '=', $apiUser->store_slug)
            ->whereHas('delivery', function ($query) use ($phone) {
                $query->where('shipping_address->phone', 'LIKE', "%{$phone}");
            })
            ->with('shipping_address')
            ->first();

        if (!$order) {
            return $this->respond(['message' => 'orderNotFound'], 404);
        }

        $addresses = Address::selectRaw('MIN(id) as id, phone, street, number, line2, city, zipcode, country, latitude, longitude')
            ->where('addressable_id', '=', $order->customer_id)
            ->where('addressable_type', '=', Customer::class)
            ->groupBy(['phone', 'street', 'number', 'line2', 'city', 'zipcode', 'country', 'latitude', 'longitude'])
            ->get();

        $call = VentiCall::where('original_order_id', $order->id)->first();
        if ($call && !is_null($call->new_order_id)) {
            $call->update(['nonce' => Str::random(30)]);
            return $this->respond([
                'message' => 'callAlreadyExists'
            ]);
        } else if (!$call) {
            $call = VentiCall::create([
                'nonce' => Str::random(30),
                'customer_id' => $order->customer_id,
                'original_order_id' => $order->id,
                'store_slug' => $order->store_slug,
            ]);
        }

        return $this->respond([
            'order' => $order,
            'customer' => $order->customer,
            'addresses' => $addresses,
            'call' => $call,
            'settings' => array_merge($apiUser->settings, ['currency' => $call->store->currency->toArray()]),
        ]);
    }

    /**
     * Checks the return info and updates the call
     * @param CheckReturnInfoRequest $request
     * @return \Illuminate\Http\JsonResponse <VentiCall>
     */
    public function checkReturnInfo(CheckReturnInfoRequest $request)
    {
        $call = $this->getCall($request);
        if (isset($call['fail'])) {
            return $this->fail($call);
        }

        $customerAddress = $request->input('address');
        if (isset($customerAddress['id'])) {
            $customerAddress = Address::find($customerAddress['id']);
        } else {
            $addressesRepo = new AddressesRepository();
            $customerAddress = $addressesRepo->get($customerAddress);
        }

        if (!$customerAddress instanceof Address) {
            return $this->fail($customerAddress);
        }

        $deliveryType = $request->input('deliveryType');

        $polygons = $this->getAvailablePolygonsQuery($deliveryType, $customerAddress);
        if (!$polygons->count()) {
            return $this->fail([
                'fail' => true,
                'message' => 'noServiceToAddress',
                'code' => 404,
            ]);
        }

        $updateData = [
            'description' => $request->input('description'),
            'is_replacement' => $deliveryType === 'replacement',
            'customer_address' => $customerAddress->toArray(),
            'phone' => $customerAddress->phone
        ];

        if (isset($customerAddress->email) && strlen($customerAddress->email)) {
            $updateData['email'] = $customerAddress->email;
        }

        if (!$call->update($updateData)) {
            return $this->respond([
                'message' => 'ventiCallUpdateFailed'
            ], 500);
        }

        $apiUser = $this->getApiUser($request);
        if (isset($apiUser['fail'])) {
            return $this->fail($apiUser);
        }

        return $this->respond($call);
    }

    /**
     * Saves the payment info and updates the call
     * @param SavePaymentInfoRequest $request
     * @return \Illuminate\Http\JsonResponse <VentiCall>
     */
    public function savePaymentInfo(SavePaymentInfoRequest $request)
    {
        $call = $this->getCall($request);
        if (isset($call['fail'])) {
            return $this->fail($call);
        }

        $apiUser = $this->getApiUser($request);
        if (isset($apiUser['fail'])) {
            return $this->fail($apiUser);
        }

        $inputs = $request->all();
        if (filter_var($apiUser->settings['charge'], FILTER_VALIDATE_BOOLEAN)) {
            $inputs['cost'] = floatVal($apiUser->settings[$call->is_replacement ? 'replacementRate' : 'returnRate']);
        }

        if (!$call->update($inputs)) {
            return $this->respond([
                'message' => 'ventiCallUpdateFailed'
            ], 500);
        }

        return $this->respond($call);
    }

    /**
     * Creates a draft order for the call
     * @param VentiCall $call
     * @return \Illuminate\Http\JsonResponse
     */
    public function createOrder(CreateOrderRequest $request)
    {
        $call = $this->getCall($request);
        if (isset($call['fail'])) {
            return $this->fail($call);
        }
        $deliveryType = $call->is_replacement ? 'replacement' : 'return';
        $shippingCodesCheckRepository = new ShippingCodesCheckRepository();
        $shippingOptionResult = $shippingCodesCheckRepository->optimal($call->customer_address, $call->store, $deliveryType);
        if (isset($shippingOptionResult['fail'])) {
            return $this->fail($shippingOptionResult);
        }

        if (!isset($shippingOptionResult['shippingOption']['polygon'])) {
            return $this->fail([
                'fail' => true,
                'message' => 'noServiceToAddress',
                'code' => 404,
            ]);
        }

        $orderCreateRepo = new OrderCreateRepository();
        $newOrder = $orderCreateRepo->save($orderCreateRepo->prepareRequest([
            'polygon_id' => $shippingOptionResult['shippingOption']['polygon']->id,
            'customerAddress' => $call->customer_address,
            'customer' => $call->customer,
            'note' => $call->description,
            'storeAddress' => $shippingOptionResult['address'],
            'store' => $call->store,
            'store_slug' => $call->store_slug,
            'source' => 'venti',
            'name' => $call->original_order->name . '_VNTI',
        ]), false);

        if (!$newOrder) {
            \Illuminate\Support\Facades\Mail::to('itay@veloapp.io')->send(new \App\Mail\Admin\Error([
                'message' => 'Venti createOrder failed',
                'call' => $call,
                'repoResult' => $newOrder,
            ]));
            return $this->fail([
                'fail' => true,
                'message' => 'orderCreateFailed',
                'code' => 500,
            ]);
        }

        if (!$call->update(['new_order_id' => $newOrder->id])) {
            \Illuminate\Support\Facades\Mail::to('itay@veloapp.io')->send(new \App\Mail\Admin\Error([
                'message' => 'Venti order created but call update failed',
                'call' => $call,
                'order' => $newOrder,
            ]));
            return $this->fail([
                'fail' => true,
                'message' => 'ventiCallUpdateFailed',
                'code' => 500,
            ]);
        }
        return $this->respond($call);
    }
}
