<?php

namespace App\Http\Controllers\Integrations\Couriers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Delivery;
use App\Repositories\Couriers\DoneRepository;
use Log;

class DoneController extends Controller
{
    public function __construct(DoneRepository $repo)
    {
        $this->repo = $repo;
    }

    public function webhook(Request $request, Courier $courier)
    {
        $payload = $request->all();
        if (
            !isset($payload['orderNumber']) ||
            !isset($payload['packageNumber']) ||
            !isset($payload['orderStatus'])
        ) {
            Log::notice('invalid done webhook - missing params', $payload);
            return $this->respond();
        }

        $order = Order::where('name', $payload['packageNumber'])->first();
        if (!$order) {
            $order = Delivery::where('remote_id', $payload['orderNumber'])->first();
            if ($order) {
                $order = $order->order;
            }
        }

        if (!$order) {
            Log::notice('invalid done webhook - order not found', $payload);
            $this->respond();
        }

        $this->repo->handleCourierResponse($order, $payload, true);

        return $this->respond();
    }
}
