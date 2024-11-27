<?php

namespace App\Http\Controllers\Integrations\Couriers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Courier;
use App\Models\Order;
use App\Repositories\Couriers\WoltRepository;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Log;

class WoltController extends Controller
{
    public function __construct(WoltRepository $repo)
    {
        $this->repo = $repo;
    }

    public function webhook(Request $request, Courier $courier)
    {
        $payload = $request->input('token');
        if (!$payload) {
            Log::info('Wolt invalid webhook payload', $request->all());
            return $this->respond();
        }
        $payload = json_decode(json_encode(JWT::decode($request->input('token'), new Key($courier->secret, 'HS256'))), true);

        $order = Order::where('name', $payload['details']['order_number'])->first();
        if (!$order) {
            Log::info('Wolt webhook: order not found', $payload);
            return $this->respond();
        }

        $this->repo->handleCourierResponse($order, $payload, true);

        return $this->respond();
    }
}
