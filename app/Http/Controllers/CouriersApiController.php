<?php

namespace App\Http\Controllers;

use App\Models\Courier;
use Illuminate\Validation\Rules\Enum;
use App\Enums\DeliveryStatusEnum;
use App\Http\Requests\CouriersApi\UpdateTrackingRequest;
use Carbon\Carbon;

class CouriersApiController extends Controller
{
    public function checkAuth($courier, $request)
    {
        if (
            ($request->header('Authorization') && $request->header('Authorization') === 'Bearer ' . $courier->secret) ||
            ($request->header('X-Velo-Courier-Secret') && $request->header('X-Velo-Courier-Secret') === $courier->secret)
        ) {
            return true;
        }
        return false;
    }

    public function updateTracking(Courier $courier, UpdateTrackingRequest $request)
    {
        if (!$this->checkAuth($courier, $request)) {
            return $this->respond(['message' => 'invalidCredentials'], 401);
        }

        return $this->respond(DeliveryStatusEnum::cases(), 200);
        $statuses = [];
        foreach (DeliveryStatusEnum::cases() as $case) {
            $statuses[$case->value] = true;
        }

        $requestDeliveries = $request->input('deliveries');
        $courierDeliveries = $courier->deliveries()->whereIn('remote_id', array_keys($requestDeliveries))->get();
        $res = [];
        foreach ($requestDeliveries as $remoteId => $status) {
            $delivery = $courierDeliveries->where('remote_id', $remoteId)->first();

            if (!isset($statuses[$status])) {
                $res['errors'][$remoteId] = 'invalid status';
                continue;
            }

            if (!$delivery) {
                $res['errors'][$remoteId] = 'not found';
                continue;
            }

            if (!$delivery->update(['status' => $status])) {
                $res['errors'][$remoteId] = 'status update failed';
            } else {
                $res['success'][] = $remoteId;
            }
        }

        return $this->respond($res);
    }

    public function getWeekDeliveries($courierName)
    {
        $courier = Courier::where('name', $courierName)->first();
        if (!$courier) {
            return redirect('/');
        }
        $res = [];
        foreach ($courier->deliveries()->whereDate('deliveries.created_at', '>=', Carbon::now()->subDays(7))->whereNotNull('remote_id')->with('order')->get() as $delivery) {
            $res[$delivery->getOrder()->name] = $delivery->remote_id;
        }
        return view('admin.data', ['data' => $res]);
    }
}
