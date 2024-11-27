<?php

namespace App\Http\Controllers;
use App\Models\Order;
use App\Services\LucidService;
use Illuminate\Http\Request;

class LucidController extends Controller
{
    private $order;

    private function authenticateRequest($orderName, $hash)
    {
        $this->order = Order::findByName($orderName);
        if (!$this->order) {
            return [
                'fail' => true,
                'error' => 'order.notFound',
                'code' => 404,
            ];
        }

        $hashCheck = $this->order->checkHash($hash, 'lucid');
        if (!empty($hashCheck['fail'])) {
            return $hashCheck;
        }

        return true;
    }

    public function getOrderDetails(Request $request, $orderName, $base64hash)
    {
        $authCheck = $this->authenticateRequest($orderName, base64_decode($base64hash));
        if (!empty($isAuthenticated['fail'])) {
            return $authCheck;
        }

        return LucidService::getOrderDetails($this->order);
    }

    public function orderUrl($orderName)
    {
        $order = Order::findByName($orderName);
        if (!$order) {
            return response()->json(['error' => 'order.notFound'], 404);
        }
        return LucidService::getOrderUrl($order);
    }
}
