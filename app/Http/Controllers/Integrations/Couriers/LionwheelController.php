<?php

namespace App\Http\Controllers\Integrations\Couriers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Courier;
use Carbon\Carbon;
use App\Models\Order;
use App\Repositories\Couriers\LionwheelRepository;
use Log;

class LionwheelController extends Controller
{
    public function __construct(LionwheelRepository $repo)
    {
        $this->repo = $repo;
    }

    public function webhook(Request $request, Courier $courier)
    {
        $payload = $request->all();
        if (
            !isset($payload['task']) ||
            !isset($payload['task']['status']) ||
            (
                !isset($payload['task']['order_id']) &&
                !isset($payload['task']['id']) &&
                !isset($payload['task']['barcode'])
            )
        ) {
            Log::notice('invalid lionwheel webhook - missing params', $payload);
            return $this->respond();
        }

        $order = null;
        if (isset($payload['task']['order_id'])) {
            $order = Order::where('name', $payload['task']['order_id'])->first();
        }

        if (
            is_null($order) &&
            (
                isset($payload['task']['id']) ||
                isset($payload['task']['barcode'])
            )
        ) {
            $barcode = isset($payload['task']['id']) ? strval($payload['task']['id']) : str_replace(':', '', $payload['task']['barcode']);
            $order = Order::whereHas('delivery', function ($query) use ($barcode) {
                $query->where('deliveries.barcode', $barcode);
            })->first();

            if (!is_null($order) && $order->delivery->polygon->courier_id !== $courier->id) {
                $order = null;
            }
        }

        if (is_null($order)) {
            Log::notice('invalid lionwheel webhook - order not found', $payload);
            return $this->respond();
        }

        $this->repo->handleCourierResponse($order, $payload['task'], true);

        return $this->respond();
    }
}


/*

{
    "task": {
        "id": 112647,
        "barcode": "112647:",
        "cartons_quantity": null,
        "company_id": 41,
        "custom_link": null,
        "delivery_latitude": null,
        "delivery_longitude": null,
        "delivery_method": null,
        "destination_apartment": null,
        "destination_city": "אשדוד",
        "destination_email": null,
        "destination_floor": null,
        "destination_notes": null,
        "destination_number": "7",
        "destination_phone": "0504225229",
        "destination_phone2": null,
        "destination_recipient_name": "אריאל קסנצובסקי",
        "destination_street": "HaNurit Street",
        "driver_id": null,
        "driver_note": null,
        "failure_reason": null,
        "gifter_name": null,
        "gifter_phone": null,
        "is_roundtrip": false,
        "money_collect": null,
        "order_id": "Vboeichallah321",
        "order_items": [],
        "order_total": null,
        "packages_quantity": 1,
        "payment_method": null,
        "photos": [],
        "public_id": "G35DYJ7DNQ",
        "signature": null,
        "signed_document": false,
        "signee_name": null,
        "source_apartment": null,
        "source_city": "רמת גן",
        "source_email": null,
        "source_floor": null,
        "source_notes": null,
        "source_number": "8",
        "source_phone": "0507488565",
        "source_recipient_name": "רויטל כדורי",
        "source_street": "ברוש",
        "status": "UNASSIGNED",
        "surfaces_quantity": null,
        "target_partner_task_id": null,
        "urgency": "REGULAR",
        "visits": [
            {
                "id": 131277,
                "delivered_at": null,
                "driver_id": null,
                "eta_at": null,
                "is_done": false,
                "kind": "DELIVERY",
                "visit_at": "2023-12-29T12:00:00.000+02:00"
            },
            {
                "id": 131278,
                "delivered_at": null,
                "driver_id": null,
                "eta_at": null,
                "is_done": false,
                "kind": "PICKUP",
                "visit_at": "2023-12-29T12:00:00.000+02:00"
            }
        ],
        "wait_time": null
    },
    "trigger_field": "task_created"
}

*/
