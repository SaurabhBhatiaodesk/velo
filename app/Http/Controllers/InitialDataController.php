<?php

namespace App\Http\Controllers;

use App\Enums\DeliveryStatusEnum;
use App\Models\Locale;
use App\Models\Currency;
use App\Models\Plan;
use App\Models\Courier;
use App\Models\Polygon;
use App\Models\ShippingCode;
use Illuminate\Http\Request;

class InitialDataController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $plans = Plan::where('is_public', true)
            ->with('prices', 'pricings')
            ->get();

        $user = auth()->user();


        if ($user->isElevated()) {
            $statuses = array_column(DeliveryStatusEnum::cases(), 'value');
            $polygons = Polygon::all();
            $couriers = Courier::all();
        } else {
            $statuses = [];
            $polygons = Polygon::where('active', true)->get(['id', 'shipping_code_id', 'courier_id', 'title', 'description', 'scheduled_pickup']);
            $couriers = Courier::whereHas('polygons', function ($query) use ($request) {
                $query->where('active', true);
            })->get();
        }

        foreach ($couriers as $i => $courier) {
            $repo = $courier->getRepo();
            if ($repo && method_exists($repo, 'getStations')) {
                $courier->stations = $repo->getStations(true);
            }
        }

        // if ($store->plan_subscription) {
        //     $polygonsQuery->where(function ($query) use ($store) {
        //         $query->whereNull('plan_id');
        //         $query->orWhere('plan_id', $store->plan_subscription->subscribable_id);
        //     });
        // }
        // $polygonsQuery->where(function ($query) use ($store) {
        //     $query->whereNull('store_slug');
        //     $query->orWhere('store_slug', $store->slug);
        // });

        return $this->respond([
            'currencies' => Currency::all(),
            'plans' => $plans,
            'couriers' => $couriers,
            'statuses' => $statuses,
            'shippingCodes' => ShippingCode::with('prices')->get(),
            'locales' => Locale::all(),
            'polygons' => $polygons,
            'measurements' => config('measurments'),
        ]);
    }
}
