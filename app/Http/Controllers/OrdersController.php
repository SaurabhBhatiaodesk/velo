<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

use App\Http\Requests\Models\Orders\BrowseRequest;
use App\Http\Requests\Models\Orders\ReadRequest;
use App\Http\Requests\Models\Orders\StoreRequest;
use App\Http\Requests\Models\Orders\UpdateRequest;
use App\Http\Requests\Models\Orders\DeleteRequest;
use App\Http\Requests\Models\Orders\PrintRequest;
use App\Http\Requests\Models\Orders\PrintDateRequest;
use App\Http\Requests\Models\Orders\PrintMultiRequest;
use App\Http\Requests\Models\Orders\SaveCommercialInvoiceRequest;
use App\Http\Requests\Models\Orders\SchedulePickupRequest;
use App\Http\Requests\Models\Orders\ImportRequest;
use App\Http\Requests\Models\Orders\GetDeliveryRequest;
use App\Http\Requests\Models\Orders\NudgeRequest;

use App\Traits\SavesFiles;
use App\Models\Order;
use App\Models\Delivery;
use App\Models\Store;
use App\Models\Polygon;
use App\Enums\DeliveryStatusEnum;
use App\Events\Models\Delivery\Updated as DeliveryUpdated;
use App\Repositories\OrderCreateRepository;
use App\Repositories\OrderStatusRepository;
use App\Repositories\DeliveriesRepository;
use App\Repositories\StickersRepository;
use App\Imports\OrdersImport;
use App\Jobs\Models\Order\AcceptJob;
use Illuminate\Support\Facades\Log;


use Carbon\Carbon;

class OrdersController extends Controller
{
    use SavesFiles;

    

    public function __construct()
    {
        $this->middleware('role:super_admin|developer|support')->only('markServiceCancel', 'markPendingCancel', 'changeStatus');
    }

    private function checkPermissions($store)
    {
        $user = auth()->user();
        if ($user->isElevated()) {
            return true;
        }
        $allowed = false;
        if ($store->user_id === $user->id) {
            $allowed = true;
        } else {
            $storeUsers = $store->users;
            if (!is_null($storeUsers)) {
                foreach ($store->users as $storeUser) {
                    if ($storeUser->id === $user->id) {
                        $allowed = true;
                        break;
                    }
                }
            }
        }
        return $allowed;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function index(BrowseRequest $request, Store $store)
    {
        if (!$this->checkPermissions($store)) {
            return $this->respond(['message' => 'auth.forbidden'], 403);
        }

        $queryParams = $request->query();
        foreach ($queryParams as $key => $value) {
            if (!strlen($value)) {
                unset($queryParams[$key]);
            } else {
                if ($key !== 'search' && strpos($value, ',') !== false) {
                    $queryParams[$key] = explode(',', $value);
                }
            }
        }

        $orders = Order::join('deliveries', 'orders.id', '=', 'deliveries.order_id')
            ->where('orders.store_slug', $store->slug)
            ->orderBy('orders.created_at', 'desc');

        if (isset($queryParams['status'])) {
            if (is_array($queryParams['status'])) {
                $orders->whereIn('deliveries.status', $queryParams['status']);
            } else {
                $orders->where('deliveries.status', $queryParams['status']);
            }
        } else {
            $orders->whereIn('deliveries.status', [
                DeliveryStatusEnum::PendingCancel,
                DeliveryStatusEnum::ServiceCancel,
                DeliveryStatusEnum::DataProblem,
                DeliveryStatusEnum::Cancelled,
                DeliveryStatusEnum::Delivered,
                DeliveryStatusEnum::Rejected,
                DeliveryStatusEnum::Refunded,
                DeliveryStatusEnum::Failed
            ]);
        }

        if (isset($queryParams['source'])) {
            if (is_array($queryParams['source'])) {
                $orders->whereIn('orders.source', $queryParams['source']);
            } else {
                $orders->where('orders.source', $queryParams['source']);
            }
        }

        if (isset($queryParams['courier'])) {
            $polygons = null;
            if (is_array($queryParams['courier'])) {
                $polygons = Polygon::whereHas('courier', function ($query) use ($queryParams) {
                    $query->whereIn('couriers.name', $queryParams['courier']);
                })->pluck('id')->toArray();
            } else {
                $polygons = Polygon::whereHas('courier', function ($query) use ($queryParams) {
                    $query->where('couriers.name', $queryParams['courier']);
                })->pluck('id')->toArray();
            }
            if ($polygons && count($polygons)) {
                $orders->whereIn('deliveries.polygon_id', $polygons);
            }
        }

        if (isset($queryParams['dates'])) {
            if (strpos($queryParams['dates'], '-') !== false) {
                $queryParams['dates'] = explode('-', $queryParams['dates']);
                $orders->whereBetween('orders.created_at', [
                    Carbon::createFromFormat('Y/m/d', $queryParams['dates'][0])->startOfDay(),
                    Carbon::createFromFormat('Y/m/d', end($queryParams['dates']))->endOfDay(),
                ]);
            } else {
                $orders->whereBetween('orders.created_at', [
                    Carbon::createFromFormat('Y/m/d', $queryParams['dates'])->startOfDay(),
                    Carbon::createFromFormat('Y/m/d', $queryParams['dates'])->endOfDay(),
                ]);
            }
        }

        if (isset($queryParams['search'])) {
            $orders->where(function ($query) use ($queryParams) {
                $query->where('name', 'LIKE', "%{$queryParams['search']}%");
                $query->orWhere('external_id', 'LIKE', "%{$queryParams['search']}%");
                $query->orWhere('remote_id', 'LIKE', "%{$queryParams['search']}%");
                $query->orWhere('barcode', 'LIKE', "%{$queryParams['search']}%");
            });
        }

        $orders->select('orders.*', 'deliveries.*');

        return $this->respond($orders->paginate(20, ['*'], 'page', $queryParams['page'] ?? 1));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCustomer(Store $store, $customerId)
    {
        if (!$this->checkPermissions($store)) {
            return $this->respond(['message' => 'auth.forbidden'], 403);
        }

        return $store->orders()
            ->where('customer_id', $customerId)
            ->with('delivery')
            ->get();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function autocomplete(Store $store, $autocomplete)
    {
        return $this->respond(
            $store
                ->orders()
                ->with('delivery')
                ->where('name', 'LIKE', "%{$autocomplete}%")
                ->orWhere('shopify_id', 'LIKE', "%{$autocomplete}%")
                ->orWhere('external_id', 'LIKE', "%{$autocomplete}%")
                ->orWhereHas('delivery', function ($query) use ($autocomplete) {
                    $query->where('remote_id', 'LIKE', "%{$autocomplete}%");
                    $query->orWhere('barcode', 'LIKE', "%{$autocomplete}%");
                })
                ->get()
        );
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function multiple(Store $store, $orderNames)
    {
        return $store
            ->orders()
            ->whereIn('name', explode(',', $orderNames))
            ->with('delivery')
            ->get();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function active(BrowseRequest $request, Store $store)
    {
        if (!$this->checkPermissions($store)) {
            return $this->respond(['message' => 'auth.forbidden'], 403);
        }

        $orders = Order::join('deliveries', 'orders.id', '=', 'deliveries.order_id')
            ->where('orders.store_slug', '=', $store->slug)
            ->whereIn('deliveries.status', [
                DeliveryStatusEnum::Placed,
                DeliveryStatusEnum::Updated,
                DeliveryStatusEnum::AcceptFailed,
                DeliveryStatusEnum::PendingAccept,
                DeliveryStatusEnum::DataProblem,
                DeliveryStatusEnum::Accepted,
                DeliveryStatusEnum::PendingPickup,
                DeliveryStatusEnum::PendingCancel,
                DeliveryStatusEnum::Transit,
                DeliveryStatusEnum::TransitToDestination,
                DeliveryStatusEnum::TransitToWarehouse,
                DeliveryStatusEnum::TransitToSender,
                DeliveryStatusEnum::InWarehouse,
            ])
            ->orderBy('orders.created_at', 'desc')
            ->get();


        return $this->respond($orders);
    }

    public function activeForPickupAddress(Store $store, $addressId)
    {
        if (!$this->checkPermissions($store)) {
            return $this->respond(['message' => 'auth.forbidden'], 403);
        }

        $orders = $store->orders()
            ->where('pickup_address_id', $addressId)
            ->with('delivery')
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($orders as $i => $order) {
            if ($order->delivery) {
                switch ($order->delivery->status->value) {
                    case DeliveryStatusEnum::Placed->value:
                    case DeliveryStatusEnum::Updated->value:
                    case DeliveryStatusEnum::AcceptFailed->value:
                    case DeliveryStatusEnum::PendingAccept->value:
                    case DeliveryStatusEnum::DataProblem->value:
                    case DeliveryStatusEnum::Accepted->value:
                    case DeliveryStatusEnum::PendingPickup->value:
                    case DeliveryStatusEnum::PendingCancel->value:
                    case DeliveryStatusEnum::Transit->value:
                    case DeliveryStatusEnum::TransitToDestination->value:
                    case DeliveryStatusEnum::TransitToWarehouse->value:
                    case DeliveryStatusEnum::TransitToSender->value:
                    case DeliveryStatusEnum::InWarehouse->value:
                        break;
                    default:
                        $orders->forget($i);
                }
            }
        }

        return $this->respond($orders);
    }


    public function nudgePending(NudgeRequest $request, Store $store)
    {
        foreach ($store->orders()->whereIn('name', $request->orders)->with('delivery')->get() as $order) {
            if (!str_starts_with($order->delivery->status->value, 'pending_')) {
                DeliveryUpdated::dispatch($order->delivery);
            }
        }
        return $this->respond();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\Models\Orders\StoreRequest $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRequest $request)
    {
        $repo = new OrderCreateRepository();
        $inputs = $this->validateRequest($request);
        $inputs = $repo->prepareRequest($inputs);
        $order = $repo->save($inputs);
        if (isset($order['fail'])) {
            return $this->fail($order);
        }

        if (isset($inputs['tax_id']) && $inputs['tax_id'] && strlen($inputs['tax_id'])) {
            $order->store->update([
                'tax_id' => $inputs['tax_id'],
            ]);
        }

        return $this->respond($order, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Http\Requests\Models\Orders\ReadRequest $request
     * @param  \App\Models\Store  $store
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     *
     */
    public function byName(ReadRequest $request, Store $store, $orderName)
    {
        $order = Order::where('name', $orderName)->first();
        if (!$order) {
            return $this->respond(['message' => 'order.notFound'], 404);
        }
        return $this->respond($order->load('delivery', 'products'));
    }

    /**
     * Get order from delivery Id
     *
     * @param  \App\Models\Store  $store
     * @param  \App\Models\Delivery  $delivery
     * @return \Illuminate\Http\Response
     *
     */
    public function fromDelivery(Store $store, Delivery $delivery)
    {
        if (!$delivery || $delivery->store_slug !== $store->slug) {
            return $this->respond(['message' => 'order.notFound'], 404);
        }

        $order = $delivery->getOrder();

        if (!$order || $order->store_slug !== $store->slug) {
            return $this->respond(['message' => 'order.notFound'], 404);
        }

        return $this->respond($order->load('delivery'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRequest $request, Order $order)
    {
        $inputs = $this->validateRequest($request);
        if (
            (isset($inputs['store_slug']) && $order->store_slug !== $inputs['store_slug']) ||
            (isset($inputs['store']) && (!isset($inputs['store']['slug']) || $order->store_slug !== $inputs['store']['slug']))
        ) {
            return $this->respond(['message' => 'order.notFound'], 422);
        }

        $repo = new OrderCreateRepository();
        $inputs = $repo->prepareRequest($inputs);
        $result = $repo->save($inputs);
        if (isset($result['fail'])) {
            return $this->fail($result);
        }

        return $this->respond($result->load('delivery'), 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Order  $order
     * @return \Illuminate\Http\Response
     */
    public function destroy(DeleteRequest $request, Order $order)
    {
        return $this->respond([
            'error' => 'forbidden',
            'code' => 401,
        ], 401);
    }

    public function accept(Request $request)
    {
        $inputs = $this->validateRequest($request);
        $order = Order::find($inputs['id']);
        if ($order->store->pending_charges()->count()) {
            return [
                'fail' => true,
                'error' => 'store.pendingCharges',
                'code' => 422,
            ];
        }
        if (!$order) {
            return [
                'fail' => true,
                'error' => 'order.notFound',
                'code' => 404,
            ];
        }
        if (!$order->delivery) {
            return [
                'fail' => true,
                'error' => 'order.noDelivery',
                'code' => 404,
            ];
        }

        if ($order->delivery->canBeAccepted()) {
            $order->delivery->update([
                'status' => 'pending_accept',
            ]);
            DeliveryUpdated::dispatch($order->delivery);
            AcceptJob::dispatch($order, (isset($inputs['skipTransmit'])) ? $inputs['skipTransmit'] : false, auth()->user());
        }

        return $this->respond($order);
    }

    public function acceptMulti(Request $request)
    {
        $inputs = $this->validateRequest($request);
        $results = [];

        foreach ($inputs['ids'] as $orderId) {
            $order = Order::find($orderId);
            if ($order->store->pending_charges()->count()) {
                $results[$order->id] = [
                    'fail' => true,
                    'error' => 'store.pendingCharges',
                    'code' => 422,
                ];
            }
            if (!$order) {
                $results[$order->id] = [
                    'fail' => true,
                    'error' => 'order.notFound',
                    'code' => 404,
                ];
            }
            if (!$order->delivery) {
                $results[$order->id] = [
                    'fail' => true,
                    'error' => 'order.noDelivery',
                    'code' => 404,
                ];
            }
            if ($order->delivery->canBeAccepted()) {
                $order->delivery->update([
                    'status' => 'pending_accept',
                ]);
                $results[$order->id] = $order;
                DeliveryUpdated::dispatch($order->delivery);
                AcceptJob::dispatch($order, false, auth()->user());
            }
        }

        return $this->respond($results);
    }


    public function transmit(Request $request)
    {
        $inputs = $this->validateRequest($request);
        $order = Order::find($inputs['id']);
        if (!$order) {
            return $this->responsd([
                'fail' => true,
                'error' => 'order.notFound',
                'code' => 404,
            ], 404);
        }

        $repo = new DeliveriesRepository();
        $result = $repo->transmit($order);
        if (isset($result['fail'])) {
            return $this->fail($result);
        }
        return $this->respond([
            'order' => $order,
            'response' => $result
        ]);
    }

    public function pickup(Request $request)
    {
        $repo = new OrderStatusRepository();
        $result = $repo->pickup($request);
        if (isset($result['fail'])) {
            return $this->fail($result);
        }
        return $this->respond(['order' => $result->load('delivery')]);
    }

    public function reject(Request $request)
    {
        $inputs = $this->validateRequest($request);
        $repo = new OrderStatusRepository();
        $result = $repo->reject($inputs);
        if (isset($result['fail'])) {
            return $this->fail($result);
        }
        return $this->respond(['order' => is_bool($result) ? false : $result->load('delivery')]);
    }

    public function printDate(PrintDateRequest $request)
    {
        $inputs = $this->validateRequest($request);

        $store = Store::where('slug', $inputs['store_slug'])->first();
        if (!$store) {
            return $this->respond(['message' => 'store.notFound'], 422);
        }

        // $inputs['date'] = Carbon::now($store->timezone)->subDay(2);
        // $inputs['date'] = explode('/', $inputs['date']);
        // $inputs['date'] = Carbon::create($inputs['date'][2], $inputs['date'][1], $inputs['date'][0], 0, 0, 0, $store->timezone);

        $orders = Order::where('store_slug', $store->slug)
            ->whereHas('delivery', function (Builder $query) use ($inputs) {
                $query->whereNot('is_return', true);
                $query->where(function (Builder $query) {
                    $query->where('status', DeliveryStatusEnum::Accepted->value);
                });
                // $query->whereDate('accepted_at', $inputs['date']);
            });

        if (!$orders->count()) {
            return $this->respond(['message' => 'order.noAcceptedFound'], 422);
        }

        $orders = $orders->get();

        $repo = new StickersRepository();
        return [
            'nextPageUrl' => $orders->nextPageUrl(),
            'stickers' => $repo->getMulti($orders),
        ];
    }

    public function printMulti(PrintMultiRequest $request)
    {
        $inputs = $this->validateRequest($request);

        $store = Store::where('slug', $inputs['store_slug'])->first();
        if (!$store) {
            return $this->respond(['message' => 'store.notFound'], 422);
        }

        $orders = Order::where('store_slug', $store->slug)
            ->whereIn('name', $inputs['names'])
            ->whereHas('delivery', function (Builder $query) {
                $query->whereNotNull('barcode');
            });

        if (!$orders->count()) {
            return $this->respond(['message' => 'order.noAcceptedFound'], 422);
        }

        $repo = new StickersRepository();
        return [
            'stickers' => $repo->getMulti($orders->get(), isset($inputs['size']) ? $inputs['size'] : null),
        ];
    }

    public function print(PrintRequest $request)
    {
        $inputs = $this->validateRequest($request);
        $order = Order::find($inputs['id']);
        if (!$order) {
            return [
                'fail' => true,
                'error' => 'order.notFound',
                'code' => 404,
            ];
        }

        $repo = new StickersRepository();
        $sticker = $repo->get($order);
        if (isset($sticker['fail'])) {
            return $this->fail($sticker);
        }
        return $this->respond(['sticker' => $sticker]);
    }

    public function showSticker($orderName)
    {
        $order = Order::where('name', $orderName)->first();
        if (!$order) {
            return 'order ' . $orderName . ' not found';
        }
        $repo = new StickersRepository();
        return $repo->getStickerHtml($order);
    }

    public function track(Request $request)
    {
        $inputs = $this->validateRequest($request);
        $repo = new DeliveriesRepository();
        $result = $repo->track($inputs);
        if (isset($result['fail'])) {
            return $this->fail($result);
        }
        return $this->respond($result);
    }

    public function trackActive(Store $store)
    {
        if (!$this->checkPermissions($store)) {
            return $this->respond(['message' => 'auth.forbidden'], 403);
        }

        $statusesToInclude = [
            DeliveryStatusEnum::Accepted->value,
            DeliveryStatusEnum::PendingPickup->value,
            DeliveryStatusEnum::Transit->value,
            DeliveryStatusEnum::TransitToDestination->value,
            DeliveryStatusEnum::TransitToWarehouse->value,
            DeliveryStatusEnum::TransitToSender->value,
            DeliveryStatusEnum::InWarehouse->value,
        ];

        $orders = $store->orders()
            ->with('delivery')
            ->get();

        foreach ($orders as $i => $order) {
            if (!$order->delivery || !in_array($order->delivery->status->value, $statusesToInclude)) {
                $orders->forget($i);
            }
        }

        if ($orders->count()) {
            $repo = new DeliveriesRepository();
            $orders = $repo->trackMany($orders);
        }

        return $orders;
    }

    public function returnOrder(Request $request)
    {
        $inputs = $this->validateRequest($request);

        $repo = new OrderCreateRepository();
        $result = $repo->createReturn($inputs);
        if (isset($result['fail'])) {
            return $this->fail($result);
        }

        return $this->respond($result, 201);
    }

    public function replace(Request $request)
    {
        $inputs = $this->validateRequest($request);

        $repo = new OrderCreateRepository();
        $result = $repo->replace($inputs);
        if (isset($result['fail'])) {
            return $this->fail($result);
        }

        return $this->respond($result, 201);
    }

    public function changeStatus(Request $request)
    {
        $inputs = $this->validateRequest($request);

        $repo = new OrderStatusRepository();
        $order = Order::find($inputs['id']);
        if (!$order) {
            return [
                'fail' => true,
                'error' => 'order.notFound',
                'code' => 404,
            ];
        }
        if (!$order->delivery) {
            return [
                'fail' => true,
                'error' => 'order.noDelivery',
                'code' => 404,
            ];
        }

        // remove velosupport: to make sure it's not duplicated
        $inputs['statusText'] = explode('velosupport:', $inputs['statusText']);
        $inputs['statusText'] = implode('', $inputs['statusText']);

        // add it once if the statusText is not empty
        if (strlen($inputs['statusText'])) {
            $inputs['statusText'] = 'velosupport:' . $inputs['statusText'];
        }

        $result = $repo->manuallyChangeStatus($order, $inputs['status'], $inputs['statusText']);
        if (isset($result['fail'])) {
            return $this->fail($result);
        }

        return $this->respond($result, 200);
    }

    public function markServiceCancel(Request $request)
    {
        $inputs = $this->validateRequest($request);

        $repo = new OrderStatusRepository();
        $result = $repo->markServiceCancel($inputs);
        if (isset($result['fail'])) {
            return $this->fail($result);
        }

        return $this->respond($result, 200);
    }

    public function markPendingCancel(Request $request)
    {
        $inputs = $this->validateRequest($request);

        $repo = new OrderStatusRepository();
        $result = $repo->markPendingCancel($inputs);
        if (isset($result['fail'])) {
            return $this->fail($result);
        }

        return $this->respond($result, 200);
    }

    public function saveCommercialInvoice(SaveCommercialInvoiceRequest $request, Order $order)
    {
        $repo = new DeliveriesRepository();
        $inputs = $request->all();
        $order->store->update([
            'tax_id' => $inputs['tax_id'],
        ]);
        $invoiceUrl = $this->saveFile($order->commercialInvoicePath, $request->input('invoice'));
        if (isset($invoiceUrl['fail'])) {
            return $this->fail($invoiceUrl);
        }
        $order->delivery->update(['commercial_invoice_uploaded_at' => Carbon::now()]);
        DeliveryUpdated::dispatch($order->delivery);
        return $this->respond(['invoice' => $invoiceUrl], 201);
    }

    public function getPickupsWindows(Store $store, Order $order)
    {
        $repo = new DeliveriesRepository();
        $repoResult = $repo->getScheduledPickupOptions($order);
        if (isset($repoResult['fail'])) {
            return $this->fail($repoResult);
        }
        return $this->respond($repoResult);
    }

    public function schedulePickup(Store $store, Order $order, SchedulePickupRequest $request)
    {
        $inputs = $request->all();
        $repo = new DeliveriesRepository();
        $repoResult = $repo->schedulePickup($order->delivery, $inputs);
        if (isset($repoResult['fail'])) {
            return $this->fail($repoResult);
        }
        return $this->respond(['order' => $order]);
    }

    public function import(Store $store, ImportRequest $request)
    {
        $failsResults = [];
        $file = $this->stringToUploadedFile($request->input('file'));
        try {
            (new OrdersImport($store))->queue($file);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();

            foreach ($failures as $failure) {
                $failsResults = [
                    'row' => $failure->row(), // row that went wrong
                    'attribute' => $failure->attribute(), // either heading key (if using heading row concern) or column index
                    'errors' => $failure->errors(), // Actual error messages from Laravel validator
                    'values' => $failure->values(), // The values of the row that has failed.
                ];
            }
        }
        return $this->respond([
            'fails' => $failsResults
        ]);
    }

    public function getDelivery(Store $store, GetDeliveryRequest $request)
    {
        return Order::whereIn('id', $request->input('order_ids'))
            ->with('delivery')
            ->get();
    }

    public function products(Order $order)
    {
        return $this->respond($order->products);
    }
}
