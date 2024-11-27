<?php

namespace App\Http\Controllers\Integrations\Couriers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Delivery;
use App\Repositories\Couriers\GetPackageRepository;
use Log;

class GetPackageController extends Controller
{
    public function __construct(GetPackageRepository $repo)
    {
        $this->repo = $repo;
    }

    public function webhook(Request $request, Courier $courier)
    {
        $payload = $request->all();
        if (
            !isset($payload['status']) ||
            !isset($payload['id']) ||
            !isset($payload['package']['packageId'])
        ) {
            Log::notice('invalid getpackage webhook - missing params', $payload);
            return $this->respond();
        }

        $order = Order::where('name', $payload['package']['packageId'])->first();
        if (!$order) {
            $order = Delivery::where('barcode', $payload['id'])->first();
            if ($order) {
                $order = $order->order;
            }
        }

        if (!$order) {
            Log::notice('invalid getpackage webhook - order not found', $payload);
            return $this->respond();
        }

        $this->repo->handleCourierResponse($order, $payload, true);

        return $this->respond();
    }
}
