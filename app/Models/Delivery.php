<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\DeliveryStatusEnum;
use App\Traits\Polymorphs\Billable;
use App\Models\TaxPolygon;
use Carbon\Carbon;

class Delivery extends Model
{
    use Billable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'remote_id',
        'polygon_id',
        'courier_responses',
        'pickup_address',
        'shipping_address',
        'billing_address',
        'status',
        'accepted_at',
        'accepted_by',
        'ready_at',
        'ready_by',
        'pickup_at',
        'pickup_by',
        'delivered_at',
        'delivered_by',
        'cancelled_at',
        'cancelled_by',
        'rejected_at',
        'rejected_by',
        'is_return',
        'is_replacement',
        'store_slug',
        'weight',
        'dimensions',
        'barcode',
        'courier_status',
        'line_number',
        'external_service_id',
        'external_service_name',
        'external_courier_name',
        'scheduled_pickup_starts_at',
        'scheduled_pickup_ends_at',
        'estimated_dropoff_starts_at',
        'estimated_dropoff_ends_at',
        'commercial_invoice_transmitted_at',
        'd_status',
        'has_push',
        'commercial_invoice_uploaded_at',
        'courier_name',
        'courier_phone',
        'receiver_name',
        'external_tracking_url',
        'pickup_images',
        'dropoff_images',
    ];

    protected $hidden = [
        'accepted_by',
        'ready_by',
        'pickup_by',
        'delivered_by',
        'cancelled_by',
        'rejected_by',
    ];

    protected $dates = [
        'accepted_at',
        'ready_at',
        'pickup_at',
        'delivered_at',
        'cancelled_at',
        'rejected_at',
        'scheduled_pickup_starts_at',
        'scheduled_pickup_ends_at',
        'estimated_dropoff_starts_at',
        'estimated_dropoff_ends_at',
        'commercial_invoice_transmitted_at',
        'commercial_invoice_uploaded_at',
    ];

    protected $casts = [
        'dimensions' => 'array',
        'courier_responses' => 'array',
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'pickup_address' => 'array',
        'pickup_images' => 'array',
        'dropoff_images' => 'array',
        'status' => DeliveryStatusEnum::class,
    ];

    /**
     *
     * Get the polygon's pickup and dropoff deadlines
     *
     * @return int
     *
     */
    public function getDeadlines()
    {
        $dates = [
            'pickup' => is_null($this->polygon->pickup_max_days) ? null : Carbon::now()->addDays($this->polygon->pickup_max_days),
            'dropoff' => is_null($this->polygon->dropoff_max_days) ? null : Carbon::now()->addDays($this->polygon->dropoff_max_days),
        ];

        foreach ($dates as $column => $date) {
            if (is_null($date) || is_null($this->accepted_at)) {
                $date = $this->polygon->shipping_code->{$column . '_max_days'};
            } else {
                $dates[$column] = $this->accepted_at->addDays($date);
            }
        }

        return $dates;
    }

    /**
     *
     * Get the average delivery days for the delivery's addresses
     *
     * @return int
     *
     */
    public function estimatedDeliveryDate()
    {
        $averageDeliveryDays = intval(
            Delivery::selectRaw('AVG(date(delivered_at) - date(accepted_at)) avg_delivery_days')
                ->whereRaw('accepted_at < delivered_at')
                ->where('accepted_at', '>', Carbon::now()->subMonths(3))
                ->where('status', '=', 'delivered')
                ->whereNotNull('accepted_at')
                ->whereNotNull('delivered_at')
                ->whereRaw('JSON_EXTRACT(pickup_address, "$.city") = "' . $this->pickup_address['city'] . '"
                       AND JSON_EXTRACT(shipping_address, "$.city") = "' . $this->shipping_address['city'] . '"')
                ->first()
                ->avg_delivery_days
        );

        if (!$averageDeliveryDays && !is_null($this->polygon->dropoff_max_days)) {
            $averageDeliveryDays = $this->polygon->dropoff_max_days;
        }

        if (!$averageDeliveryDays) {
            return -1;
        }

        return $this->accepted_at ? $this->accepted_at->addDays($averageDeliveryDays) : Carbon::now()->addDays($averageDeliveryDays + 1);
    }

    public function getLocalPrice($estimate = true, $store = false)
    {
        if (!$store) {
            $store = $this->store;
        }
        $prices = $this->polygon->getPrices($store);

        if (!$prices->count()) {
            return false;
        }

        $price = $prices;
        $price->where('slug', '!=', 'shield');
        if ($prices->where('slug', 'modular')->count()) {
            $price->where('slug', 'modular');
        } else if ($prices->whereNull('slug')->count()) {
            $price->whereNull('slug');
        }

        $price = $price->first();

        if (!$price) {
            \Log::info('polygon without price', [
                'polygon' => $this->polygon->id,
                'store' => $this->store_slug,
            ]);
            return false;
        }
        if ($price['slug'] === 'modular') {
            if (isset($price['data']['prices']['total_deliveries_count'])) {
                // redundancy fallback
                $totalDeliveriesCount = 0;
                // if estimate, the count is the store volume estimation
                if ($estimate) {
                    $totalDeliveriesCount = $store->volume;
                } else {
                    $date = is_null($this->accepted_at) ? Carbon::now() : $this->accepted_at;
                    $courierId = $this->polygon->courier_id;
                    $pickupAddressId = $this->polygon->courier_id;
                    $order = $this->getOrder();
                    if ($order) {
                        $pickupAddressId = $order->pickup_address_id;
                    } else {
                        if (isset($this->pickup_address['id'])) {
                            $pickupAddressId = $this->pickup_address['id'];
                            \Log::info('delivery without order', [
                                'delivery' => $this->toArray(),
                            ]);
                        } else {
                            \Log::info('delivery without pickup address', [
                                'delivery' => $this->toArray(),
                            ]);
                        }
                    }

                    // this gets the actual count
                    $totalDeliveriesCount = $this
                        ->store
                        ->orders()
                        ->where(function ($query) use ($date, $courierId, $order) {
                            $query->where('pickup_address_id', $order ? $order->pickup_address_id : $this->pickup_address['id']);
                            $query->whereHas('delivery', function ($query) use ($date, $courierId) {
                                $query->whereBetween('created_at', [
                                    $date->clone()->startOfMonth(),
                                    $date,
                                ]);
                                $query->whereNotIn('status', [
                                    DeliveryStatusEnum::Placed,
                                    DeliveryStatusEnum::Updated,
                                    DeliveryStatusEnum::AcceptFailed,
                                    DeliveryStatusEnum::PendingAccept,
                                    DeliveryStatusEnum::Rejected,
                                    DeliveryStatusEnum::Refunded,
                                ]);
                                $query->whereHas('polygon', function ($query) use ($courierId) {
                                    $query->where('courier_id', $courierId);
                                });
                            });
                        })
                        ->count();
                }

                $price['original_price'] = $price['price'];
                // iterate the thresholds to get the price
                foreach ($price['data']['prices']['total_deliveries_count'] as $threshold => $thresholdPrice) {
                    if ($totalDeliveriesCount >= $threshold) {
                        $price['price'] = $thresholdPrice;
                    }
                }
            }
        }

        $price['shield'] = $this->polygon->getShield($store);

        $perKg = $prices;
        if ($perKg->where('slug', 'like', 'per_kg%')->count()) {
            $perKgMultiplier = $perKg->where('slug', 'like', 'per_kg:%')->first()->slug;
            $perKgMultiplier = explode(':', $perKgMultiplier);
            $perKgMultiplier = intval(end($perKgMultiplier));
            $price['price'] += $price['price'] * intval($this->weight / $perKgMultiplier);
        }

        return $price;
    }

    public function getCost()
    {
        if (!is_null($this->cost)) {
            return $this->cost;
        }
        if ($this->polygon->external_pricing) {
            $price = $this->getPrice(false);
            if (!isset($price['fail'])) {
                $profitMargin = $this->getProfitMargin();
                $price->price -= $profitMargin->price;

                if (!empty($profitMargin->data)) {
                    if (!empty($profitMargin->data['multiplier'])) {
                        $price->price /= $profitMargin->data['multiplier'];
                    }
                }

                // double check before updating
                if (is_null($this->cost)) {
                    $this->update(['cost' => $price->price]);
                }
            }
            return $price;
        }
        return $this->polygon->getCost();
    }

    public function getPrice($estimate = true, $order = false, $store = false)
    {
        if (!$store) {
            $store = $this->store;
        }
        $shieldPrice = $this->polygon->getShield($store);
        $shieldPrice = $shieldPrice ? $shieldPrice->price : false;

        if ($this->polygon->external_pricing || ($estimate && $this->polygon->external_availability_check)) {
            $repo = $this->polygon->courier->getRepo();
            if (!$order) {
                $order = $this->getOrder();
            }
            if (!$estimate) {
                $price = $repo->getPrice($this);
                return (isset($price['fail'])) ? $price : new Price($price);
            } else {
                if ($this->polygon->shipping_code->is_international) {
                    if (!method_exists($repo, 'getRatesInternational')) {
                        return [];
                    }
                    $availableOptions = $repo->getRatesInternational($order);
                } else if ($this->polygon->is_collection) {
                    if (!method_exists($repo, 'getRatesCollection')) {
                        return [];
                    }
                    $availableOptions = $repo->getRatesCollection($order);
                } else {
                    if (!method_exists($repo, 'getRate')) {
                        return [];
                    }
                    $availableOptions = $repo->getRate($order, false);
                }
            }

            if (count($availableOptions) && !isset($availableOptions['fail'])) {
                if (!isset($availableOptions[0])) {
                    $availableOptions = [['prices' => [$availableOptions]]];
                }

                foreach ($availableOptions as $i => $availableOption) {
                    $price = $availableOption;
                    if (!is_numeric($availableOption)) {
                        $availableOption = isset($availableOption['prices'][0]['price']) ? $availableOption['prices'][0]['price'] : $availableOption;
                    }

                    $availableOption = is_numeric($availableOption) ? $availableOption : $availableOption['prices'][0]['price'];
                    if ($order->delivery->polygon->tax_included) {
                        $taxPolygons = new TaxPolygon();
                        foreach ($taxPolygons->getForAddress($order->store->getBillingAddress()) as $taxPolygon) {
                            $availableOption = $taxPolygon->removeTax($availableOption);
                        }
                    }

                    // TODO
                    $availableOptions[$i]['prices'][0]['price'] = round($availableOption + $order->delivery->calculateProfitMargin($availableOption));

                    if ($shieldPrice) {
                        $availableOptions[$i]['prices'][0]['shield'] = $shieldPrice;
                    }
                }
            }

            return $availableOptions;
        }

        return $this->getLocalPrice($estimate, $store);
    }

    public function calculateProfitMargin($price = false, $polygon = false)
    {
        if (!$price) {
            $price = $this->getPrice(false, false, $this->store);
            if (!isset($price['price'])) {
                return false;
            }
            $price = $price['price'];
        }

        $profitMargin = $this->getProfitMargin(false, $polygon);
        if (!$profitMargin) {
            return false;
        }

        $result = 0;

        if (!empty($profitMargin->data)) {
            if (!empty($profitMargin->data['multiplier'])) {
                $result += $price * $profitMargin->data['multiplier'];
            }
        }

        $result += $profitMargin->price;

        return false;
    }

    public function getProfitMargin($store = false, $polygon = false)
    {
        if (!$store) {
            $store = $this->store;
        }
        if (!$polygon) {
            $polygon = $this->polygon;
        }
        return $polygon->getProfitMargin($store);
    }

    public function getOrder()
    {
        $order = $this->order;
        if (!$order && !is_null($this->status)) {
            switch ($this->status->value) {
                case DeliveryStatusEnum::ServiceCancel->value:
                case DeliveryStatusEnum::Cancelled->value:
                case DeliveryStatusEnum::Delivered->value:
                case DeliveryStatusEnum::Rejected->value:
                case DeliveryStatusEnum::Refunded->value:
                case DeliveryStatusEnum::Failed->value:
                    $order = Order::find($this->order_id);
                    break;
                default:
                    $order = ArchivedOrder::find($this->order_id);
            }
        }
        return $order;
    }

    public function order()
    {
        if ($this->status && strlen($this->status->value)) {
            switch ($this->status->value) {
                case DeliveryStatusEnum::ServiceCancel->value:
                case DeliveryStatusEnum::Cancelled->value:
                case DeliveryStatusEnum::Delivered->value:
                case DeliveryStatusEnum::Rejected->value:
                case DeliveryStatusEnum::Refunded->value:
                case DeliveryStatusEnum::Failed->value:
                    return $this->belongsTo(ArchivedOrder::class);
            }
        }
        return $this->belongsTo(Order::class);
    }

    function canBeAccepted()
    {
        if (is_null($this->barcode)) {
            switch ($this->status->value) {
                case DeliveryStatusEnum::Placed->value:
                case DeliveryStatusEnum::Updated->value:
                case DeliveryStatusEnum::AcceptFailed->value:
                case DeliveryStatusEnum::PendingAccept->value:
                    return true;
            }
        }
        return false;
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_slug', 'slug');
    }

    public function accepted_by_user()
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function ready_by_user()
    {
        return $this->belongsTo(User::class, 'ready_by');
    }

    public function pickup_by_user()
    {
        return $this->belongsTo(User::class, 'pickup_by');
    }

    public function delivered_by_user()
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function cancelled_by_user()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function rejected_by_user()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function polygon()
    {
        return $this->belongsTo(Polygon::class);
    }

    public function courier()
    {
        return $this->belongsToThrough(Courier::class, Polygon::class);
    }

    public function shipping_code()
    {
        return $this->polygon->shipping_code();
    }
}
