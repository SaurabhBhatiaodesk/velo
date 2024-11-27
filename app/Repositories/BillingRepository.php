<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use App\Repositories\Invoicing\InvoicingRepository;
use App\Repositories\BaseRepository;
use App\Models\TaxPolygon;
use App\Models\Bill;
use App\Models\Delivery;
use App\Models\Transaction;
use App\Models\CreditLine;
use App\Enums\DeliveryStatusEnum;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\Admin\Notification as AdminNotificationEmail;
use Log;

class BillingRepository extends BaseRepository
{
    protected $apiRoot = '';
    protected $clearanceFee = 0.015;

    /**
     * Gets the transaction's description from the bills' creation dates
     *
     * @param Collection<Bill> $bills
     * @param string $billsDescription
     *
     * @return string
     */
    private function getTransactionDescription($bills, $billsDescription = '')
    {
        if (!$bills->count()) {
            return '';
        }
        app()->setLocale($bills->first()->store->getLocale()->iso);

        $description = $bills->first()->store->name;
        if (strlen($billsDescription)) {
            $description .= ' ' . $billsDescription;
        }

        $localizedDate = Carbon::parse($bills->first()->created_at)->locale(app()->getLocale());
        $description .= ' ' . $localizedDate->format('d/m/Y');

        if (!$bills->first()->created_at->isSameDay($bills->last()->created_at)) {
            $description .= ' - ' . Carbon::parse($bills->last()->created_at)->locale(app()->getLocale())->format('d/m/Y');
        }
        return $description;
    }

    /**
     * Makes a transaction (without clearance)
     * @param \App\Models\PaymentMethod $paymentMethod
     * @param string $description
     * @param float $total
     * @return array
     */
    protected function makeTransaction($paymentMethod, $description, $total)
    {
        return $this->fail('makeTransaction function called on billing repository (without clearance)', 500, [
            'store' => $paymentMethod->store->name,
            'description' => $description,
            'total' => $total,
        ]);
    }

    /**
     * Applies credits to the total
     * @param \App\Models\Store $store
     * @param float &$total
     * @return Collection<CreditLine>
     */
    protected function applyCredits($store, &$total)
    {
        $credits = [];

        foreach ($store->valid_credit_lines as $i => $creditLine) {
            if (
                $total > 0 &&
                (
                    // min-charge
                    intVal($total - $creditLine->total) >= 50 ||
                    // full-discount
                    $total === $creditLine->total
                )
            ) {
                $credits[] = $creditLine;
                $total -= floatVal($creditLine->total);
            }
        }

        $total = round($total, 2);
        return new Collection($credits);
    }

    /**
     * Gets the clearance fee
     * @param float $total
     * @return float
     */
    private function getClearanceFee($total)
    {
        return round($total * $this->clearanceFee, 2);
    }

    /**
     * Gets a clearance fee bill
     * @param \App\Models\Store $store
     * @param float $total
     *
     * @return \App\Models\Bill
     */
    private function getClearanceFeeBill($store, $total)
    {
        app()->setLocale($store->getLocale()->iso);
        return new Bill([
            'description' => __('billing.clearance_fee'),
            'total' => $this->getClearanceFee($total),
            'currency_id' => $store->currency_id,
            'billable_type' => 'App\\Models\\Transaction',
            'store_slug' => $store->slug,
        ]);
    }

    /**
     * Pays and invoices a collection of bills
     * @param \App\Models\Store $store
     * @param string $description
     * @param \Illuminate\Support\Collection $bills
     * @return \App\Models\Transaction|array
     */
    public function pay($store, $description, $bills)
    {
        if (!is_null($store->blocked_at)) {
            Mail::to('itay@veloapp.io')->send(new AdminNotificationEmail([
                'message' => 'Blocked store pay attempt - ' . $store->name,
                'result' => [
                    'store' => $store->name,
                    'blocked_at' => $store->blocked_at,
                    'blocked_by' => $store->blocker->email,
                    'description' => $description,
                    'bills' => $bills->toArray(),
                ],
            ]));
            return $this->fail('auth.blockedStore', [
                'store' => $store->name,
                'blocked_at' => $store->blocked_at,
                'blocked_by' => $store->blocker->toArray(),
            ]);
        }
        $bills = $this->removeNullifiedDeliveryBills($bills);
        $total = $this->getTotalWithTaxes($bills);
        if (isset($total['fail'])) {
            return $total;
        }

        $credits = $this->applyCredits($store, $total);
        $defaultPaymentMethod = $store->getPaymentMethod();
        if (!$defaultPaymentMethod) {
            return $this->fail('store.noPaymentMethod');
        }

        $clearanceFeeBill = null;
        if ($total > 0) {
            $clearanceFeeBill = $this->getClearanceFeeBill($store, $total);
            $total = round($total + $clearanceFeeBill->total, 2);
            $transaction = $this->makeTransaction($defaultPaymentMethod, $description, $total);
            if (isset($transaction['fail'])) {
                // try all payment methods
                foreach ($store->payment_methods as $paymentMethod) {
                    if ($defaultPaymentMethod->id !== $paymentMethod->id) {
                        $transaction = $this->makeTransaction($paymentMethod, $description, $total);
                        if (!isset($transaction['fail'])) {
                            // transaction succeeded - break the loop
                            break;
                        }
                    }
                }
            }

            // if all payment methods failed
            if (isset($transaction['fail'])) {
                $store->update(['suspended' => true]);
                return $transaction;
            } else if ($store->suspended) {
                // release suspension on successful payment
                $store->update(['suspended' => false]);
            }
        } else {
            $transaction = [
                'description' => $description,
                'transaction_data' => [],
                'total' => 0,
                'payment_method_id' => $defaultPaymentMethod->id,
                'store_slug' => $store->slug,
            ];
        }

        $transaction = Transaction::create($transaction);
        if (!$transaction) {
            return $this->fail('BillingRepository.pay transaction creation failed');
        }

        // save the clearance fee bill
        if (!is_null($clearanceFeeBill) && $clearanceFeeBill->total > 0) {
            $clearanceFeeBill->billable_id = $transaction->id;
            $clearanceFeeBill->transaction_id = $transaction->id;
            $clearanceFeeBill->save();
        }

        $bills = $this->addTaxes($bills, ['transaction_id' => $transaction->id]);
        if (isset($bills['fail'])) {
            return $bills;
        }

        foreach ($credits as $credit) {
            if (!$credit->update(['transaction_id' => $transaction->id])) {
                Log::debug('credit was used but not updated', [
                    'transaction' => $transaction,
                    'credit' => $credit,
                ]);
            }
        }

        if ($total > 0) {
            $invoicingRepository = new InvoicingRepository();
            $transaction = $invoicingRepository->generateInvoice($transaction);
        }

        return $transaction;
    }

    /**
     * Gets the total of a collection of bills with taxes
     * @param \Illuminate\Support\Collection $bills
     * @return float|array
     */
    public function getTotalWithTaxes($bills)
    {
        if (!$bills->count()) {
            return 0;
        }
        $taxPolygons = new TaxPolygon();
        $taxPolygons = $taxPolygons->getForAddress($bills->first()->store->getBillingAddress());
        $total = 0;
        foreach ($bills as $bill) {
            $total += $bill->total;
            foreach ($taxPolygons as $taxPolygon) {
                $total += $taxPolygon->calculateTax($bill->total);
            }
        }

        return floatval(round($total, 2));
    }

    /**
     * Adds taxes to a collection of bills
     * Accepts extra data for update on the bills
     * @param \Illuminate\Support\Collection $bills
     * @param array $updateData
     * @return \Illuminate\Support\Collection|array
     */
    public function addTaxes($bills, $updateData = [])
    {
        if ($bills->count()) {
            $taxPolygons = new TaxPolygon();
            $taxPolygons = $taxPolygons->getForAddress($bills->first()->store->getBillingAddress());
            foreach ($bills as $bill) {
                $taxes = [];

                if (!($bill->billable_type === 'App\\Models\\Delivery' && $bill->billable->polygon->tax_included)) {
                    foreach ($taxPolygons as $polygon) {
                        $taxes[] = [
                            'total' => $polygon->calculateTax($bill->total),
                            'name' => $polygon->name,
                        ];
                    }
                }

                if (
                    (count($updateData) || count($taxes)) &&
                    !$bill->update(array_merge($updateData, ['taxes' => $taxes]))
                ) {
                    Log::debug('BillingRepository@addTaxes failed', $bill->toArray());
                    return $this->fail('bill.taxesUpdateFailed');
                }
            }
        }

        return $bills;
    }

    /**
     * Bills a delivery
     * @param \App\Models\Delivery $delivery
     * @return \App\Models\Bill|array
     */
    public function billDelivery($delivery, $price = false)
    {
        if (!$price) {
            $price = $delivery->getPrice(false);
        }

        if (isset($price[0]) && isset($price[0]['prices']) && isset($price[0]['prices'][0])) {
            $price = $price[0]['prices'][0];
        }

        if (!$price || isset($price['fail'])) {
            Log::info('Billing Repository BillingRepository - couldn\'t get delivery cost', [
                'store' => $delivery->store->name,
                'order' => $delivery->getOrder()->name,
                'shipping_code' => $delivery->polygon->shipping_code->code,
                'result' => $price,
            ]);
            return $price;
        }

        if ($price['currency_id'] !== $delivery->store->currency_id) {
            Log::info('Billing Repository BillingRepository - invalid currency recieved from courier', [
                'store' => $delivery->store->name,
                'delivery_id' => $delivery->id,
                'shipping_code' => $delivery->polygon->shipping_code->code,
                'result' => $price,
            ]);
            return $this->fail('delivery.invalidCurrency');
        }

        $cost = $delivery->getCost();
        if (isset($cost['fail'])) {
            $cost = null;
        } else {
            $cost = $cost->price;
        }
        // check for venti here
        $bill = Bill::create([
            'description' => $delivery->getOrder()->name . ' - ' . $delivery->created_at->toFormattedDateString(),
            'store_slug' => $delivery->store_slug,
            'billable_type' => 'App\\Models\\Delivery',
            'billable_id' => $delivery->id,
            'total' => $price['price'],
            'currency_id' => $delivery->store->currency_id,
            'cost' => $cost,
        ]);

        if (!$bill) {
            Log::info('Billing Repository BillingRepository - delivery bill creation failed', [
                'store' => $delivery->store->name,
                'delivery_id' => $delivery->id,
                'shipping_code' => $delivery->polygon->shipping_code->code,
                'polygon_id' => $delivery->polygon->shipping_code_id,
                'result' => $price,
            ]);
            return $this->fail('bill.creationFailed');
        }

        return $bill;
    }

    /**
     * Charges pending bills for a store
     * @param \App\Models\Store $store
     * @return array
     */
    public function chargePending($store)
    {
        $bills = $store->bills()
            ->where('total', '>', 0)
            ->whereNull('transaction_id')
            ->get();

        if (!$bills->count()) {
            return [
                'store' => $store->name,
                'total' => 0,
                'paymentMethod' => 'no unpaid bills',
            ];
        }

        $bills = $this->removeNullifiedDeliveryBills($bills);
        $lastPaymentDate = $store->getLastPaymentDate();
        app()->setLocale($store->getLocale()->iso);
        foreach ($bills as $i => $bill) {
            if ($bill->billable_type === 'App\\Models\\Delivery') {
                if (!$bill->billable->accepted_at || $bill->billable->accepted_at->isAfter($lastPaymentDate)) {
                    $bills->forget($i);
                }
            } else if ($bill->created_at->isAfter($lastPaymentDate)) {
                $bills->forget($i);
            }
            if (!is_null($bill->billable) && $bill->billable->bills()->count() > 1) {
                foreach ($bill->billable->bills()->get() as $innerBill) {
                    if (!$bills->contains($innerBill)) {
                        if (str_contains($innerBill->description, __('billing.late_fee'))) {
                            $innerBill->update(['total' => round($innerBill->total * 0.05, 2)]);
                        }
                        $bills->splice($i, 0, [$innerBill]);
                    }
                }
            }
        }

        $transaction = $this->pay($store, $this->getTransactionDescription($bills), $bills);
        if (isset($transaction['fail'])) {
            return $transaction;
        }

        return [
            'total' => $transaction->total,
        ];
    }

    /**
     * Removes nullified delivery bills from a collection and from the database
     * @param \Illuminate\Support\Collection $bills
     * @return \Illuminate\Support\Collection
     */
    public function removeNullifiedDeliveryBills($bills)
    {
        foreach ($bills as $i => $bill) {
            if (is_null($bill)) {
                $bills->forget($i);
            } else if ($bill->billable_type === 'App\\Models\\Delivery') {
                switch ($bill->billable->status->value) {
                    case DeliveryStatusEnum::Rejected->value:
                    case DeliveryStatusEnum::Cancelled->value:
                    case DeliveryStatusEnum::ServiceCancel->value:
                    case DeliveryStatusEnum::Refunded->value:
                        $bills->forget($i);
                        $bill->delete();
                }
            }
        }
        return $bills;
    }

    /**
     * Charges deliveries for a store
     * @param \App\Models\Store $store
     * @return \App\Models\Transaction|array
     */
    public function chargeDeliveries($store)
    {
        app()->setLocale($store->getLocale()->iso);
        $this->creditModularDeliveries($store);
        $bills = Bill::where('store_slug', $store->slug)
            ->where('billable_type', 'App\\Models\\Delivery')
            ->whereNull('transaction_id')
            ->whereHasMorph('billable', [Delivery::class], function ($query) {
                $query->whereNotIn('status', [
                    DeliveryStatusEnum::Placed->value,
                    DeliveryStatusEnum::Updated->value,
                    DeliveryStatusEnum::AcceptFailed->value,
                    DeliveryStatusEnum::Rejected->value,
                    DeliveryStatusEnum::Cancelled->value,
                    DeliveryStatusEnum::ServiceCancel->value,
                    DeliveryStatusEnum::Refunded->value,
                ]);
            })
            ->get();

        if (!$bills->count()) {
            return [
                'fail' => true,
                'error' => 'no bills',
                'code' => 404,
            ];
        }
        $transaction = $this->pay($store, $this->getTransactionDescription($bills, __('billing.deliveries')), $bills);
        return $transaction;
    }

    /**
     * Credits modular deliveries
     * @param \App\Models\Store $store
     * @param Carbon $date
     * @return array<CreditLine>
     */
    public function creditModularDeliveries($store, $date = false)
    {
        app()->setLocale($store->getLocale()->iso);
        if (!$date) {
            $date = Carbon::now();
        }

        $deliveryPrices = [];
        // get the prices of all deliveries in the month (count deliveries per pickup address) and all already-paid deliveries (deliveries to credit)
        foreach ($store->billable_deliveries()->whereBetween('accepted_at', [$date->clone()->startOfMonth(), $date->endOfMonth()])->get() as $delivery) {
            if ($delivery->bill && is_null($delivery->bill->transaction_id)) {
                $price = $delivery->getPrice(false);
                if (!$price) {
                    Log::error('billing repository - modular delivery credit - price not found', [
                        'order' => $delivery->getOrder()->name,
                        'shipping_code' => $delivery->polygon->shipping_code->code,
                        'courier' => $delivery->polygon->courier->name,
                    ]);
                    continue;
                }
                if ($price->slug === 'modular') {
                    $deliverySlug = $delivery->polygon->courier_id . $delivery->getOrder()->pickup_address->slugified;
                    if (
                        isset($deliveryPrices[$deliverySlug]) &&
                        isset($deliveryPrices[$deliverySlug]['calculated'][$delivery->polygon->shipping_code_id])
                    ) {
                        if ($delivery->bill && !is_null($delivery->bill->transaction_id)) {
                            $deliveryPrices[$deliverySlug]['deliveries'][] = $delivery;
                        }
                    } else {
                        // count total deliveries from the same store, courier & pickup address
                        $count = isset($deliveryPrices[$deliverySlug]['count']) ? $deliveryPrices[$deliverySlug]['count'] : $delivery
                            ->store
                            ->orders()
                            ->where(function ($query) use ($date, $delivery) {
                                $query->where('pickup_address_id', $delivery->getOrder()->pickup_address_id);
                                $query->whereHas('delivery', function ($query) use ($date, $delivery) {
                                    $query->whereBetween('created_at', [
                                        $date->clone()->startOfMonth(),
                                        $date->clone()->endOfMonth(),
                                    ]);
                                    $query->whereNotIn('status', [
                                        DeliveryStatusEnum::Placed,
                                        DeliveryStatusEnum::Updated,
                                        DeliveryStatusEnum::AcceptFailed,
                                        DeliveryStatusEnum::Rejected,
                                        DeliveryStatusEnum::Cancelled,
                                        DeliveryStatusEnum::Refunded,
                                    ]);
                                    $query->whereHas('polygon', function ($query) use ($delivery) {
                                        $query->where('courier_id', $delivery->polygon->courier_id);
                                    });
                                });
                            })
                            ->count();

                        // create initial structure (with count and price)
                        if (!isset($deliveryPrices[$deliverySlug])) {
                            $deliveryPrices[$deliverySlug] = [
                                'deliveries' => ($delivery->bill && !is_null($delivery->bill->transaction_id)) ? [$delivery] : [],
                                'price' => $price,
                                'count' => $count,
                                'calculated' => [],
                            ];
                        }

                        // get the modular price for the shipping_code of the delivery
                        $deliveryPrices[$deliverySlug]['calculated'][$delivery->polygon->shipping_code_id] = $price->price;
                        foreach ($price['data']['prices']['total_deliveries_count'] as $threshold => $thresholdPrice) {
                            if ($count >= $threshold) {
                                $deliveryPrices[$deliverySlug]['calculated'][$delivery->polygon->shipping_code_id] = $thresholdPrice;
                            }
                        }
                    }
                }
            }
        }

        $credits = [];
        // iterate the findings and create/update credits
        foreach ($deliveryPrices as $pickupAddressDataset) {
            foreach ($pickupAddressDataset['deliveries'] as $delivery) {
                // if the delivery price is higher than the calculated price
                if ($delivery->bill->total > $pickupAddressDataset['calculated'][$delivery->polygon->shipping_code_id]) {
                    // get the required data for the credit
                    $description = __('billing.modular_credit') . ' - ' . $delivery->getOrder()->name;
                    $amountToCredit = $delivery->bill->total - $pickupAddressDataset['calculated'][$delivery->polygon->shipping_code_id];

                    // get the credit if it exists
                    $credit = $delivery->bill->credit()->where('description', $description)->first();

                    // if the credit exists
                    if ($credit) {
                        // if the credit needs to be updated
                        if ($credit->total !== $amountToCredit) {
                            // attempt update
                            if (!$credit->update(['total' => $amountToCredit])) {
                                // log if fails
                                Log::info('Billing Repository BillingRepository - modular delivery credit update failed', [
                                    'store' => $store->name,
                                    'delivery_id' => $delivery->id,
                                    'shipping_code' => $delivery->polygon->shipping_code->code,
                                    'price' => $price,
                                    'pickupAddressDataset' => $pickupAddressDataset,
                                    'credit' => $credit,
                                ]);
                                // next delivery
                                continue;
                            }
                        }
                        // credit doesn't exist
                    } else {
                        // try to create the credit
                        $credit = $delivery->bill->credit()->create([
                            'description' => $description,
                            'total' => $amountToCredit,
                            'currency_id' => $delivery->store->currency_id,
                            'store_slug' => $delivery->store_slug,
                        ]);
                        if (!$credit) {
                            // log if fails
                            Log::info('Billing Repository BillingRepository - modular delivery credit creation failed', [
                                'store' => $store->name,
                                'delivery_id' => $delivery->id,
                                'shipping_code' => $delivery->polygon->shipping_code->code,
                                'price' => $price,
                                'pickupAddressDataset' => $pickupAddressDataset,
                            ]);
                            // next delivery
                            continue;
                        }
                    }
                    // add valid credits to the array
                    $credits[] = $credit;
                }
            }
        }

        return $deliveryPrices;
    }

    /**
     * Charges a store for a subscription
     * @param \App\Models\Subscription $subscription
     * @return \App\Models\Transaction|array
     */
    public function chargeSubscription($subscription, $bill = false)
    {
        if (!$bill) {
            $bill = $subscription->bill;
        }
        return $this->pay($subscription->store, $subscription->billing_description(), new Collection([$bill]));
    }

    /**
     * Bills a store for a subscription
     * If a Bill somehow already exists, it will be updated
     * @param \App\Models\Subscription $subscription
     * @return \App\Models\Bill|array
     */
    public function billSubscription($subscription, $priceSlug = '', $priceModifier = 1)
    {
        $store = $subscription->store;
        if (!$store) {
            return $this->fail('subscription.noStore');
        }

        if (!$subscription->subscribable) {
            return $this->fail('subscription.noSubscribable');
        }

        if ($subscription->subscribable_type === 'App\\Models\\Plan') {
            $price = $subscription
                ->subscribable
                ->prices()
                ->where('currency_id', $store->currency_id);
        } else {
            $price = $store
                ->plan_subscription
                ->subscribable
                ->prices()
                ->where('currency_id', $store->currency_id);
        }

        $priceQuery = clone $price;
        if (strlen($priceSlug) && $priceQuery->where('slug', $priceSlug)->count()) {
            $price = $price->where('slug', $priceSlug);
        } else {
            $priceQuery = clone $price;
            if ($price->where('slug', 'plan_subscription')->count()) {
                $price = $price->where('slug', 'plan_subscription');
            }
        }

        $price = $price->first();

        $billTotal = round($price->price * $priceModifier, 2);
        $bill = $subscription->bill;
        // if the subscription already has a bill
        if ($bill) {
            Log::info('Billing Repository Subscription Bill exists', [
                'bill' => $bill->toArray(),
                'subscription description' => $subscription->billing_description(),
            ]);
            // if the bill is already paid, return it
            if ($bill->transaction_id) {
                return $bill;
            }
            // if there's an existing unpaid bill with a different total, update it
            else if ($bill->total !== $billTotal) {
                Log::info('Billing Repository Subscription Bill total update', [
                    'bill' => $bill->toArray(),
                    'new total' => $billTotal,
                    'subscription description' => $subscription->billing_description(),
                ]);
                $bill->update(['total' => $billTotal]);
            }
        } else {
            Log::info('Billing Repository Subscription Bill created', [
                'description' => $subscription->billing_description(),
                'store_slug' => $store->slug,
                'total' => $billTotal,
                'currency_id' => $price->currency_id,
            ]);
            // the subscription doesn't have a bill, create one
            $bill = $subscription->bill()->create([
                'description' => $subscription->billing_description(),
                'store_slug' => $store->slug,
                'total' => $billTotal,
                'currency_id' => $price->currency_id,
            ]);
        }

        if (!$bill) {
            return $this->fail('subscription.billCreateFailed');
        }

        return $bill;
    }

    /**
     * Bills and charges a store for a subscription
     * @param \App\Models\Subscription $subscription
     * @return \App\Models\Bill|array
     */
    public function billAndChargeSubscription($subscription, $priceSlug = '', $priceModifier = 1)
    {
        $bill = $this->billSubscription($subscription, $priceSlug, $priceModifier);
        if (isset($bill['fail'])) {
            return $bill;
        }

        if (!$bill instanceof Bill) {
            return [];
        }

        $transaction = $this->chargeSubscription($subscription, $bill);
        if (isset($transaction['fail'])) {
            return $transaction;
        }

        return $bill;
    }
}
