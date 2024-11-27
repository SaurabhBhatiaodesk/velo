<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Enums\DeliveryStatusEnum;
use App\Repositories\BaseRepository;
use App\Models\Order;
use Carbon\Carbon;
use App\Events\Models\Delivery\Updated as DeliveryUpdated;
use Log;

class DeliveriesRepository extends BaseRepository
{
    public function confirm($order, $skipTransmit = false)
    {
        $repo = $order->delivery->polygon->courier->getRepo();
        $isInternational = $order->delivery->polygon->shipping_code->is_international;
        if (!$order->delivery->remote_id) {
            if ($isInternational && is_null($order->getCommercialInvoice())) {
                return $this->fail('delivery.noCommercialInvoice');
            }
            $repoResult = $repo->createClaim($order);
            if (isset($repoResult['fail'])) {
                return $repoResult;
            }
        }

        if ($isInternational && !is_null($order->delivery->remote_id) && is_null($order->delivery->commercial_invoice_transmitted_at)) {
            $transmitResult = $this->transmit($order);
            if (isset($transmitResult['fail'])) {
                if ($order->delivery->status !== DeliveryStatusEnum::Placed->value) {
                    $order->delivery->update([
                        'status' => DeliveryStatusEnum::Placed,
                        'courier_status' => 'transmit_fail'
                    ]);
                }
                return $transmitResult;
            }
            $order = $transmitResult;
        }

        if (method_exists($repo, 'schedulePickup')) {
            if (
                is_null($order->delivery->scheduled_pickup_starts_at) ||
                is_null($order->delivery->scheduled_pickup_ends_at)
            ) {
                $repoResult = $repo->schedulePickup($order->delivery, $repoResult['pickupWindows'] ?? []);
                if (isset($repoResult['fail'])) {
                    if ($order->delivery->status !== DeliveryStatusEnum::Placed->value) {
                        $order->delivery->update(['status' => DeliveryStatusEnum::Placed]);
                    }
                    return $repoResult;
                }
                $order->delivery = $repoResult;
            }
        }

        if (method_exists($repo, 'confirmClaim')) {
            if ($order->delivery->polygon->courier->api === 'yango') {
                sleep(3); // Yango claim can't be immediately confirmed
            }

            $repoResult = $repo->confirmClaim($order->delivery, $skipTransmit);
            if (isset($repoResult['fail'])) {
                if ($order->delivery->status !== DeliveryStatusEnum::PendingAccept->value) {
                    $order->delivery->update(['status' => DeliveryStatusEnum::PendingAccept]);
                }
                return $repoResult;
            }
        }

        if (method_exists($repo, 'pushSubscribe') && $order->delivery->polygon->has_push && !$order->delivery->has_push) {
            $order = $repo->pushSubscribe($order);
        }

        return $order->delivery;
    }

    public function schedulePickup($delivery, $pickupWindow)
    {
        $repo = $delivery->polygon->courier->getRepo();
        if (!method_exists($repo, 'schedulePickup')) {
            return $this->fail('delivery.noScedulePickupCourierSupport');
        }
        if (!$pickupWindow['start'] instanceof Carbon) {
            $pickupWindow['start'] = Carbon::create($pickupWindow['start']);
        }
        if (!$pickupWindow['end'] instanceof Carbon) {
            $pickupWindow['end'] = Carbon::create($pickupWindow['end']);
        }
        return $repo->schedulePickup($delivery, [$pickupWindow]);
    }

    public function trackMany($orders = [])
    {
        if (!count($orders)) {
            return [];
        }

        if (!$orders[0] instanceof Order) {
            $orders = Order::whereIn('id', $orders)->with('delivery')->get();
        }

        $repos = [];
        foreach ($orders as $order) {
            $repo = $order->delivery->polygon->courier->getRepo();
            if (!isset($repos[get_class($repo)])) {
                $repos[get_class($repo)] = [
                    'repo' => $repo,
                    'orders' => [],
                ];
            }
            $repos[get_class($repo)]['orders'][] = $order;
        }

        $results = [];
        foreach ($repos as $repo) {
            if (method_exists($repo['repo'], 'trackClaims')) {
                $results = array_merge($results, $repo['repo']->trackClaims(new Collection($repo['orders'])));
            }
        }
        return $results;
    }

    public function track($inputs)
    {
        if ($inputs instanceof Order) {
            $order = $inputs;
        } else {
            $order = Order::find($inputs['id']);
        }

        if (!$order) {
            return $this->fail('order.notFound', 404);
        }
        if (!$order->delivery->polygon) {
            return $this->fail('order.noPolygon');
        }
        if (!$order->delivery->polygon->courier) {
            return $this->fail('order.noCourier');
        }
        if (!$order->delivery) {
            return $this->fail('order.noDelivery');
        }
        $repo = $order->delivery->polygon->courier->getRepo();
        $order = $repo->trackClaim($order);
        if (!isset($order['fail'])) {
            DeliveryUpdated::dispatch($order->delivery);
        }
        return $order;
    }

    public function getRatesInternational($order)
    {
        $repo = $order->delivery->polygon->courier->getRepo();
        if (method_exists($repo, 'getRatesInternational')) {
            return $repo->getRatesInternational($order);
        }
        return [];
    }

    public function getRatesCollection($order)
    {
        $repo = $order->delivery->polygon->courier->getRepo();
        $rates = $repo->getRatesCollection($order);
        // add profit margin here and remove it from s2g
        return $rates;
    }

    public function transmit($order)
    {
        $repo = $order->delivery->polygon->courier->getRepo();
        if (method_exists($repo, 'transmitDocuments')) {
            $order = $repo->transmitDocuments($order);
        }
        return $order;
    }

    public function getScheduledPickupOptions($order, $inputs = [])
    {
        $repo = $order->delivery->polygon->courier->getRepo();
        if (!method_exists($repo, 'getScheduledPickupOptions')) {
            Log::info('polygon requires pickup, api has no pickup scheduling');
            return $this->fail('delivery.scheduledPickupFail');
        } else {
            $repoResult = $repo->getScheduledPickupOptions($order->delivery, $inputs);
            return $repoResult;
        }
    }
}
