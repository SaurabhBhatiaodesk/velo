<?php

namespace App\Repositories;

use App\Repositories\BaseRepository;
use App\Repositories\DeliveriesRepository;
use App\Repositories\Clearance\PaymeRepository as BillingRepository;
use App\Enums\DeliveryStatusEnum;
use App\Models\Order;
use Carbon\Carbon;
use App\Events\Models\Delivery\Updated as DeliveryUpdated;
use App\Events\Models\User\NegativeNotification;
use App\Mail\Venti\DeliveryConfirmed;
use Illuminate\Support\Facades\Mail;

class OrderStatusRepository extends BaseRepository
{
    /**
     * @param Order $order
     * @param bool $skipTransmit
     *
     * @return Order|array [fail => true, error => error message, code => error code]
     */
    public function accept(Order $order, $skipTransmit, $user = false)
    {
        if ($order->store->blocked_at) {
            NegativeNotification::dispatch($user, __('billing.blocked'));
            return $this->fail('auth.blockedStore', [
                'store' => $order->store->name,
                'blocked_at' => $order->store->blocked_at,
                'blocked_by' => $order->store->blocker->toArray(),
            ]);
        }
        if ($order->store->suspended) {
            NegativeNotification::dispatch($user, __('billing.suspended'));
            return $this->fail('auth.suspendedStore');
        }
        if ($order->delivery->canBeAccepted()) {
            // transmit the delivery to the shipping company
            $repo = new DeliveriesRepository();
            $delivery = $repo->confirm($order, $skipTransmit);
            // catch any errors
            if (isset($delivery['fail'])) {
                if (strpos($delivery['error'], 'cURL error') !== false) {
                    $delivery['error'] = 'Communication error, please try again';
                }
                $order->delivery->update($this->addTimestampsUpdateData([
                    'status' => DeliveryStatusEnum::AcceptFailed,
                    'courier_status' => $delivery['error'],
                ], $order));
                DeliveryUpdated::dispatch($order->delivery);
                if ($user) {
                    NegativeNotification::dispatch($user, __('user_notifications.delivery.accept_failed'));
                }
                return $delivery;
            }
            // update the order with the delivery
            $order->delivery = $delivery;

            // if the delivery is not billed, bill it
            if (!$order->delivery->bill && !$order->delivery->polygon->external_pricing) {
                $billingRepo = new BillingRepository();
                $bill = $billingRepo->billDelivery($order->delivery);
                // catch any errors
                if (isset($bill['fail'])) {
                    return $bill;
                }
            }

            if (!$user) {
                $user = auth()->check() ? auth()->user() : null;
            }

            // update the delivery status and timestamps
            if (
                !$order->delivery->update($this->addTimestampsUpdateData([
                    'accepted_at' => Carbon::now(),
                    'accepted_by' => $user ? $user->id : null,
                    'status' => DeliveryStatusEnum::Accepted,
                ], $order))
            ) {
                // catch any errors
                return $this->fail('order.saveFailed');
            }

            DeliveryUpdated::dispatch($order->delivery);
        }

        // Update VentiCall if exists
        if ($order->ventiCall()->exists()) {
            $order->ventiCall()->update([
                'confirmed_at' => Carbon::now(),
            ]);

            $email = false;
            if ($order->ventiCall->email) {
                $email = $order->ventiCall->email;
            } else if ($order->customer->email) {
                $email = $order->customer->email;
            }

            if ($email && strlen($email)) {
                Mail::to($email)
                    ->send(new DeliveryConfirmed($order));
            }
        }
        // return the order
        return $order;
    }

    public function pickup($request)
    {
        $inputs = $this->validateRequest($request);
        $order = Order::find($inputs['id']);
        if (!$order) {
            return $this->fail('order.notFound', 404);
        }
        if (!$order->delivery) {
            return $this->fail('order.noDelivery', 404);
        }
        if (
            $order->delivery->status->value === DeliveryStatusEnum::Accepted->value ||
            $order->delivery->status->value === DeliveryStatusEnum::Updated->value ||
            $order->delivery->status->value === DeliveryStatusEnum::PendingPickup->value
        ) {
            if (
                !$order->delivery->update($this->addTimestampsUpdateData([
                    'pickup_at' => Carbon::now(),
                    'status' => DeliveryStatusEnum::Transit,
                ], $order))
            ) {
                return $this->fail('order.saveFailed');
            }
        }

        return $order;
    }

    public function reject($inputs)
    {
        $order = Order::find($inputs['id']);
        if (!$order) {
            return $this->fail('order.invalid');
        }
        if (!$order->delivery) {
            return $order->delete();
        }

        if (
            $order->delivery->status->value === DeliveryStatusEnum::PendingAccept->value ||
            $order->delivery->status->value === DeliveryStatusEnum::Placed->value ||
            $order->delivery->status->value === DeliveryStatusEnum::Updated->value
        ) {
            if (
                !$order->delivery->update($this->addTimestampsUpdateData([
                    'rejected_at' => Carbon::now(),
                    'rejected_by' => auth()->id(),
                    'status' => DeliveryStatusEnum::Rejected,
                ], $order))
            ) {
                return $this->fail('order.updateFailed');
            }
        }

        return $order;
    }

    public function complete($order)
    {
        if (!$order->delivery) {
            return $this->fail('order.noDelivery', 404);
        }
        if ($order->delivery->polygon->external_pricing) {
            $billingRepo = new BillingRepository();
            $bill = $billingRepo->billDelivery($order->delivery);
            if (isset($bill['fail'])) {
                return $bill;
            }
        }

        return $order;
    }

    public function markServiceCancel($inputs)
    {
        $order = Order::find($inputs['id']);
        if (!$order) {
            return $this->fail('order.notFound', 404);
        }
        if (!$order->delivery) {
            return $this->fail('order.noDelivery', 404);
        }
        if (
            !$order->delivery->update($this->addTimestampsUpdateData([
                'status' => DeliveryStatusEnum::ServiceCancel,
            ], $order))
        ) {
            return $this->fail('order.updateFailed', 404);
        }
        DeliveryUpdated::dispatch($order->delivery);
        return $order;
    }
    public function markPendingCancel($inputs)
    {
        $order = Order::find($inputs['id']);
        if (!$order) {
            return $this->fail('order.notFound', 404);
        }
        if (!$order->delivery) {
            return $this->fail('order.noDelivery', 404);
        }
        if (
            !$order->delivery->update($this->addTimestampsUpdateData([
                'status' => DeliveryStatusEnum::PendingCancel,
            ], $order))
        ) {
            return $this->fail('order.updateFailed', 404);
        }
        DeliveryUpdated::dispatch($order->delivery);
        return $order;
    }

    public function manuallyChangeStatus($order, $status, $statusText)
    {
        if ($order && !($order instanceof Order)) {
            if (strlen($order)) {
                $order = Order::where('name', $order)->first();
            }
        }
        if (!$order) {
            return $this->fail('order.notFound', 404);
        }
        if (!$order->delivery) {
            return $this->fail('order.noDelivery', 404);
        }

        if (
            !$order->delivery->update($this->addTimestampsUpdateData([
                'status' => $status,
                'courier_status' => (strlen($statusText) > 0) ? $statusText : null,
            ], $order))
        ) {
            return $this->fail('order.updateFailed', 404);
        }
        DeliveryUpdated::dispatch($order->delivery);
        return $order;
    }

    private function addTimestampsUpdateData($updateData, $order)
    {
        // No status update = no timestamps update
        if (!isset($updateData['status'])) {
            return $updateData;
        }
        if ($updateData['status'] instanceof DeliveryStatusEnum) {
            $updateData['status'] = $updateData['status']->value;
        }
        switch ($updateData['status']) {
            // before transmit
            case DeliveryStatusEnum::Placed->value:
            case DeliveryStatusEnum::Updated->value:
            case DeliveryStatusEnum::AcceptFailed->value:
            case DeliveryStatusEnum::DataProblem->value:
                break;
            // rejected by user
            case DeliveryStatusEnum::Rejected->value:
                if (empty($order->delivery->rejected_at)) {
                    $updateData['rejected_at'] ??= Carbon::now();
                    $updateData['rejected_by'] ??= auth()->id();
                }
                break;
            // before pickup
            case DeliveryStatusEnum::Accepted->value:
            case DeliveryStatusEnum::PendingPickup->value:
                if (empty($order->delivery->accepted_at)) {
                    $updateData['accepted_at'] ??= Carbon::now();
                    $updateData['accepted_by'] ??= auth()->id();
                }
                break;
            // in transit
            case DeliveryStatusEnum::Transit->value:
            case DeliveryStatusEnum::TransitToDestination->value:
            case DeliveryStatusEnum::TransitToWarehouse->value:
            case DeliveryStatusEnum::TransitToSender->value:
            case DeliveryStatusEnum::InWarehouse->value:
                if (empty($order->delivery->pickup_at)) {
                    $updateData['pickup_at'] ??= Carbon::now();
                }
                break;
            // done
            case DeliveryStatusEnum::Delivered->value:
                if (empty($order->delivery->delivered_at)) {
                    $updateData['cancelled_at'] = null;
                    $updateData['delivered_at'] ??= Carbon::now();
                }
                break;
            // failed
            case DeliveryStatusEnum::Failed->value:
            // cancellations
            case DeliveryStatusEnum::ServiceCancel->value:
            case DeliveryStatusEnum::Cancelled->value:
                if (empty($order->delivery->cancelled_at)) {
                    $updateData['delivered_at'] = null;
                    $updateData['cancelled_at'] ??= Carbon::now();
                    $updateData['cancelled_by'] ??= auth()->id();
                }

            // refunds
            case DeliveryStatusEnum::Refunded->value:

            // temp statuses
            case DeliveryStatusEnum::PendingAccept->value:
            case DeliveryStatusEnum::PendingCancel->value:
                break;
        }
        return $updateData;
    }

    /**
     * Update an order delivery with courier response data
     *
     * @param Order $order
     * @param array $updateData
     * @param array $courierResponseAppend
     *
     * @return Order|array [fail => true, error => error message, code => error code]
     */
    public function saveCourierUpdateData($order, $updateData, $courierResponseAppend = [])
    {
        // create a new delivery if it doesn't exist (createClaim)
        if (!$order->delivery()->exists()) {
            $updateData['courier_responses'] = [
                array_merge([
                    'date' => Carbon::now(),
                    'code' => $updateData['courier_status'],
                    'status' => $updateData['status'],
                ], $courierResponseAppend)
            ];
            $order->delivery()->create($updateData);
        } else {
            if (
                isset($updateData['courier_status']) &&
                (
                    $order->delivery->courier_status !== $updateData['courier_status'] ||
                    (
                        isset($updateData['status']) &&
                        $order->delivery->status !== $updateData['status']
                    )
                )
            ) {
                $updateData['courier_responses'] ??= [];
                $updateData['courier_responses'][] = array_merge([
                    'date' => Carbon::now(),
                    'code' => $updateData['courier_status'],
                    'status' => $updateData['status'],
                ], $courierResponseAppend);
            }

            if (!$order->delivery->update($this->addTimestampsUpdateData($updateData, $order))) {
                return $this->fail('order.updateFailed');
            }
        }

        return $order;
    }
}
