<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\CheckPaymeReportRequest;
use App\Imports\PaymeReportImport;
use App\Models\ShippingCode;
use App\Repositories\LateOrdersRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;

use App\Enums\DeliveryStatusEnum;
use App\Models\Store;
use App\Models\Polygon;
use App\Models\Order;
use App\Models\Courier;
use App\Models\CreditLine;
use App\Models\Bill;
use App\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReadRequest;
use App\Http\Requests\Admin\CreateCreditLineRequest;
use App\Http\Requests\Admin\CreateBillRequest;
use App\Http\Requests\Admin\CreateTransactionRequest;
use App\Http\Requests\Admin\GetStoreBooksRequest;
use App\Http\Requests\Admin\UpdateStoreRequest;
use App\Http\Requests\Admin\CheckCourierReportRequest;
use App\Http\Requests\Admin\GetStoreEnterpriseBillingReportRequest;
use App\Http\Requests\Admin\LoadStoreDataRequest;
use App\Traits\SavesFiles;
use App\Repositories\BillingRepository;
use App\Exports\Billing\KartesetExport;
use App\Exports\Billing\StoreBillingReportExport;
use App\Imports\Couriers\ZigzagReportImport;
use App\Imports\Couriers\GetpackageReportImport;
use App\Imports\Couriers\BaldarReportImport;
use App\Imports\Couriers\ShippingToGoReportImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class MainController extends Controller
{
    use SavesFiles;

    public function __construct()
    {
        $this->middleware('role:super_admin|admin|developer|support');
    }

    public function stores()
    {
        return $this->respond(Store::all());
    }

    public function updateStore(UpdateStoreRequest $request, Store $store)
    {
        if (
            !$store->update([
                'enterprise_billing' => !!$request->input('enterprise_billing'),
                'suspended' => !!$request->input('suspended'),
                'blocked_at' => !!$request->input('blocked_at') ? Carbon::now() : null,
                'blocked_by' => !!$request->input('blocked_at') ? auth()->id() : null,
            ])
        ) {
            return $this->respond(['error' => 'store.updateFailed'], 500);
        }

        return $this->respond($store);
    }

    public function polygons()
    {
        $polygons = Polygon::selectRaw("polygons.id as id, polygons.*, couriers.name courier ")
            ->join("couriers", "polygons.courier_id", "=", "couriers.id")
            ->where("active", "=", "1")
            ->get()->each->makeVisible(Polygon::first()->getHidden())->toArray();
        return $this->respond($polygons);
    }

    public function shippingCodes()
    {
        $polygons = ShippingCode::get()->each->makeVisible(Polygon::first()->getHidden())->toArray();
        return $this->respond($polygons);
    }




    function sendEmail(Request $request)
    {
        $emailAddress = $request->post('emailAddress');
        $message = $request->post('message');
        return response()->json([
            'success' => Mail::to($emailAddress)->send(new \App\Mail\BasicMail($request->post('subject'), $message))
        ]);
    }

    public function stats(Request $request)
    {
        $inputs = $this->validateRequest($request);
        $orders = Order::whereHas('delivery', function ($q) use ($inputs) {
            $q->whereBetween('accepted_at', [
                Carbon::parse($inputs['from'])->startOfDay(),
                Carbon::parse($inputs['to'])->endOfDay(),
            ]);
            $q->where('status', '!=', 'rejected');
        })
            ->with('delivery', 'delivery.bill')
            ->get();

        return $this->respond([
            'orders' => $orders
        ]);
    }

    public function order(ReadRequest $request, $orderName)
    {
        $order = Order::with('delivery', 'delivery.bill', 'customer', 'products')->where('name', $orderName)->first();
        if (!$order) {
            return $this->respond([
                'fail' => true,
                'message' => 'order.notFound',
                'code' => 404,
            ], 404);
        }
        return $this->respond($order);
    }

    public function orders(Request $request)
    {
        $activeStatuses = [
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
        ];


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
            ->orderBy('orders.created_at', 'desc');

        if (isset($queryParams['status'])) {
            if (is_array($queryParams['status'])) {
                $orders->whereIn('deliveries.status', $queryParams['status']);
            } else {
                $orders->where('deliveries.status', $queryParams['status']);
            }
        } else {
            $orders->whereIn('deliveries.status', $activeStatuses);
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

    public function couriers()
    {
        return Courier::all();
    }

    public function login_as(Store $store)
    {
        $user = $store->user;
        $token = auth()->login($user);
        return $this->respondWithToken($token);
    }

    public function createCreditLine(CreateCreditLineRequest $request)
    {
        return $this->respond(CreditLine::create($request->all()));
    }

    public function createBill(CreateBillRequest $request)
    {
        $store = Store::where('slug', $request->input('store_slug'))->first();
        return $this->respond(Bill::create(array_merge($request->all(), [
            'billable_type' => 'App\Models\Store',
            'currency_id' => $store->currency_id,
        ])));
    }

    public function createTransaction(CreateTransactionRequest $request)
    {
        $inputs = $request->all();
        $transaction = Transaction::create($inputs);
        if (!$transaction) {
            return $this->respond(['error' => 'transaction.createFailed'], 500);
        }

        $bills = [];
        foreach ($inputs['bills'] as $billData) {
            if ($billData['total'] < 0) {
                $creditLine = CreditLine::find($billData['id']);
                if ($creditLine) {
                    $creditLine->update(['transaction_id' => $transaction->id]);
                }
            } else {
                $bills[] = $billData['id'];
            }
        }
        $billingRepository = new BillingRepository;
        $bills = $billingRepository->addTaxes(Bill::whereIn('id', $bills)->get(), ['transaction_id' => $transaction->id]);
        return $this->respond($transaction->load('bills'));
    }

    public function getStoreBooks(GetStoreBooksRequest $request)
    {
        $store = Store::where('slug', $request->input('slug'))->first();
        if (!$store) {
            return $this->respond([
                'error' => 'store.notFound',
                'code' => 422,
            ], 404);
        }
        $response = (new KartesetExport($store))->download($store->slug . '.xlsx');
        return $response;
    }

    public function checkPaymeReport(CheckPaymeReportRequest $request)
    {


        $file = $this->stringToUploadedFile($request->input('file'));
        try {
            (new PaymeReportImport(auth()->user()))->import($file);
            return $this->respond(['message' => 'courier.reportUploaded'], 200);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failsResults = [];
            $failures = $e->failures();

            foreach ($failures as $failure) {
                $failsResults = [
                    'row' => $failure->row(), // row that went wrong
                    'attribute' => $failure->attribute(), // either heading key (if using heading row concern) or column index
                    'errors' => $failure->errors(), // Actual error messages from Laravel validator
                    'values' => $failure->values(), // The values of the row that has failed.
                ];
            }

            return $this->respond([
                'message' => 'courier.reportFailed',
                'errors' => $failsResults
            ], 500);
        }
    }




    public function checkCourierReport(CheckCourierReportRequest $request)
    {
        $courier = Courier::find($request->input('courierId'));
        if (!$courier) {
            return $this->respond(['error' => 'courier.notFound'], 404);
        }

        $file = $this->stringToUploadedFile($request->input('file'));

        try {
            $api = explode(':', $courier->api)[0];
            switch (strtolower($api)) {
                case 'baldar':
                    (new BaldarReportImport(auth()->user(), $courier))->import($file);
                    return $this->respond(['message' => 'courier.reportUploaded'], 200);
                case 'zigzag':
                    (new ZigzagReportImport(auth()->user(), $courier))->import($file);
                    return $this->respond(['message' => 'courier.reportUploaded'], 200);
                case 'getpackage':
                    (new GetpackageReportImport(auth()->user(), $courier))->import($file);
                    return $this->respond(['message' => 'courier.reportUploaded'], 200);
                case 'shippingtogo':
                    (new ShippingToGoReportImport(auth()->user(), $courier))->import($file);
                    return $this->respond(['message' => 'courier.reportUploaded'], 200);
                default:
                    return $this->respond(['error' => 'courier.notSupported'], 422);
            }
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failsResults = [];
            $failures = $e->failures();

            foreach ($failures as $failure) {
                $failsResults = [
                    'row' => $failure->row(), // row that went wrong
                    'attribute' => $failure->attribute(), // either heading key (if using heading row concern) or column index
                    'errors' => $failure->errors(), // Actual error messages from Laravel validator
                    'values' => $failure->values(), // The values of the row that has failed.
                ];
            }

            return $this->respond([
                'message' => 'courier.reportFailed',
                'errors' => $failsResults
            ], 500);
        }
    }

    function lateOrdersHistory($fromDate, $toDate)
    {
        return LateOrdersRepository::lateOrdersHistory($fromDate, $toDate);
    }

    function dailyLateOrdersReport()
    {
        return LateOrdersRepository::dailyLateOrdersReport();
    }

    function loadStoreData(LoadStoreDataRequest $request, Store $store)
    {
        $store->load('user');
        return $this->respond([
            'user' => $store->user,
        ]);
    }

    function getEnterpriseBillingReport(GetStoreEnterpriseBillingReportRequest $request)
    {
        $store = Store::where('slug', $request->input('slug'))->first();
        if (!$store) {
            return $this->respond([
                'error' => 'store.notFound',
                'code' => 422,
            ], 404);
        }
        $response = (new StoreBillingReportExport($store))->download($store->slug . '.xlsx');
        return $response;
    }
}
