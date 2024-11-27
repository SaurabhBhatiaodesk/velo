<?php

namespace App\Repositories;

use App\Models\Bill;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\CreditLine;
use App\Repositories\Clearance\PaymeRepository as BillingRepository;
use App\Events\Models\User\NegativeNotification as UserNegativeNotification;
use App\Events\Models\User\PositiveNotification as UserPositiveNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Log;

class SubscriptionsRepository extends BaseRepository
{
    public function autoRenew($query = false)
    {
        if (!$query) {
            $query = new Subscription();
        }
        $subscriptions = $query
            ->where('auto_renew', true)
            ->where('subscribable_type', 'App\\Models\\Plan')
            ->whereDate('ends_at', '<=', Carbon::today())
            ->get();

        $res = [];
        foreach ($subscriptions as $subscription) {
            if (
                // subscription already renewed
                Subscription::where('renewed_from', $subscription->id)->count() ||
                // deleted store
                !$subscription->store ||
                // store already subscribed to subscribable
                $subscription->store
                    ->active_subscriptions
                    ->where('subscribable_type', $subscription->subscribable_type)
                    ->where('subscribable_id', $subscription->subscribable_id)
                    ->count()
            ) {
                // no need to renew
                continue;
            } else {
                $result = $this->renew($subscription);
                if (isset($result['fail'])) {
                    Log::info('SubscriptionsRepository renew subscription failed for: ' . $subscription->store->name, $result);
                }
            }
        }
    }


    /**
     * Creates subscriptions for addons
     * @param \App\Models\Subscription $subscription
     * @return \App\Models\Transaction|array
     */
    private function createAddonSubscriptions($planSubscription, $addonModels, $priceSlug)
    {
        $bills = [];
        if (!is_null($addonModels) && $addonModels->count()) {
            $billingRepo = new BillingRepository();
            foreach ($addonModels as $i => $addonModel) {
                if (!method_exists($addonModel, 'getMorphClass')) {
                    Log::info('SubscriptionsRepository Addon Subscription failed - invalid addon model', [
                        'addonModels' => $addonModels,
                        'i' => $i,
                    ]);
                }
                $subscription = $planSubscription->store->subscriptions()->create([
                    'store_slug' => $planSubscription->store_slug,
                    'starts_at' => Carbon::now(),
                    // synchronize to end of plan subscription to synchoronize with the store's billing cycle
                    'ends_at' => $planSubscription->ends_at,
                    // auto_renew is false because we need to count subscriptions by type when renewing the plan
                    'auto_renew' => false,
                    'renewed_from' => null,
                    'subscribable_type' => $addonModel->getMorphClass(),
                    'subscribable_id' => $addonModel->id,
                ]);
                Log::info('SubscriptionsRepository Addon Subscription created', $subscription->toArray());
                $bill = $billingRepo->billSubscription($subscription, $priceSlug);
                Log::info('SubscriptionsRepository Addon Subscription Bill created', $subscription->load('bill')->toArray());
                if (isset($bill['fail'])) {
                    Log::error('failed to bill addon subscription', [
                        'subscription' => $subscription->toArray(),
                        'billResult' => $bill,
                    ]);
                    $subscription->delete();
                } else {
                    $bills[] = $bill;
                }
            }
        }
        return $bills;
    }

    /**
     * Renews an existing subscription
     * @param Subscription $subscription
     * @return Subscription|array [fail => true, error => error message, code => error code]
     */
    public function renew($subscription)
    {
        // check the store is not already subscribed to the same subscribable
        if (
            $subscription
                ->store
                ->active_subscriptions()
                ->where('subscribable_type', $subscription->subscribable_type)
                ->where('subscribable_id', $subscription->subscribable_id)
                ->exists()
        ) {
            return $subscription;
        }

        // create the new subscription
        $newSubscription = Subscription::create([
            'store_slug' => $subscription->store_slug,
            'starts_at' => Carbon::now()->startOfDay(),
            'ends_at' => Carbon::now()->addMonth()->endOfDay(),
            'renewed_from' => $subscription->id,
            'subscribable_type' => $subscription->subscribable_type,
            'subscribable_id' => $subscription->subscribable_id,
        ]);
        Log::info('SubscriptionsRepository Subscription renewed', [
            'oldSubscription' => $subscription,
            'newSubscription' => $newSubscription,
        ]);
        // propagate personal prices to the new subscription if there are any
        if ($subscription->prices()->count()) {
            if (!$subscription->prices()->update(['subscription_id', $newSubscription->id])) {
                Log::info('SubscriptionsRepository failed to renew subscription prices', [
                    'old' => $subscription->id,
                    'new' => $newSubscription->id
                ]);
            }
        }

        // add a bill for the new subscription
        $billingRepository = new BillingRepository();
        $bills = [$billingRepository->billSubscription($newSubscription)];
        if (isset($bills[0]['fail'])) {
            $newSubscription->delete();
            return $bills[0];
        }

        // prevent duplicated renews
        $subscription->update(['auto_renew' => false]);

        // create addon subscriptions
        // addon subscriptions are always manually created - never renewed
        foreach (config('plans.' . $newSubscription->subscribable->name) as $type => $settings) {
            if ($settings['included'] >= 0) {
                switch ($type) {
                    case 'member':
                        $bills = array_merge($bills, $this->createAddonSubscriptions($newSubscription, $newSubscription->store->users()->skip($settings['included'])->take(PHP_INT_MAX)->get(), $type));
                        break;
                    case 'address':
                        $bills = array_merge($bills, $this->createAddonSubscriptions($newSubscription, $newSubscription->store->pickup_addresses()->skip($settings['included'])->take(PHP_INT_MAX)->get(), $type));
                        break;
                    case 'integration':
                        $bills = array_merge($bills, $this->createAddonSubscriptions($newSubscription, $newSubscription->store->api_users()->skip($settings['included'])->take(PHP_INT_MAX)->get(), $type));
                        $bills = array_merge($bills, $this->createAddonSubscriptions($newSubscription, $newSubscription->store->shopifyShop()->skip($settings['included'])->take(PHP_INT_MAX)->get(), $type));
                        break;
                }
            }
        }

        // charge for the new subscription
        if (!$newSubscription->store->enterprise_billing) {
            $transaction = $billingRepository->pay($newSubscription->store, __('billing.subscription'), new Collection($bills));
            if (isset($transaction['fail'])) {
                return $transaction;
            }
        }

        return $newSubscription;
    }

    public static function create($store, $subscribable, $autoRenew = true, $prices = [], $promo = false)
    {
        $existingSubscription = false;
        if (str_ends_with($subscribable->getMorphClass(), 'Plan')) {
            $existingSubscription = $store->plan_subscription;
            if ($existingSubscription && $existingSubscription->subscribable_id === $subscribable->id) {
                if (auth()->check()) {
                    UserNegativeNotification::dispatch(auth()->user(), __('user_notifications.subscriptions.existing'));
                }
                return $existingSubscription;
            }
            $pendingDowngrade = $store->subscriptions()
                ->where('subscribable_type', 'App\\Models\\Plan')
                ->whereDate('starts_at', '>', Carbon::now())
                ->first();

            if ($pendingDowngrade) {
                $pendingDowngrade->delete();
            }
        }

        $subscription = Subscription::create([
            'renewed_from' => $existingSubscription ? $existingSubscription->id : null,
            'starts_at' => Carbon::now()->startOfDay(),
            'ends_at' => Carbon::now()->addMonth()->endOfDay(),
            'subscribable_type' => $subscribable->getMorphClass(),
            'subscribable_id' => $subscribable->id,
            'store_slug' => $store->slug,
            'auto_renew' => $autoRenew,
        ]);

        $billingRepository = new BillingRepository();
        $bill = $billingRepository->billSubscription($subscription);
        if (isset($bill['fail'])) {
            $subscription->delete();
            return $bill;
        }

        // on downgrade - toggle auto_renew to false and adjust new subscription's dates.
        // on upgrade - cancel old subscription and credit the remaining days
        if ($existingSubscription) {
            // check if downgrade
            if (
                !is_null($existingSubscription->bill) &&
                !is_null($existingSubscription->bill->total) &&
                (
                    !$bill instanceof Bill ||
                    is_null($bill->total) ||
                    floatval($bill->total) < floatval($existingSubscription->bill->total)
                )
            ) {
                // the downgraded subscription starts once the current one ends
                $subscription->update([
                    'auto_renew' => true,
                    'starts_at' => $existingSubscription->ends_at,
                    'ends_at' => $existingSubscription->ends_at->addMonth()->endOfDay(),
                ]);

                // cancel auto-renew on the current (old) subscription
                $existingSubscription->update([
                    'auto_renew' => false,
                ]);
            }

            // check if upgrade
            else if (
                $bill instanceof Bill &&
                !is_null($bill->total) &&
                (
                    is_null($existingSubscription->bill) ||
                    is_null($existingSubscription->bill->total) ||
                    floatval($existingSubscription->bill->total) < floatval($bill->total)
                )
            ) {
                $existingSubscriptionPrice = (is_null($existingSubscription->bill) || is_null($existingSubscription->bill->total)) ? 0 : $existingSubscription->bill->total;
                if ($existingSubscriptionPrice > 0) {
                    $pricePerDay = $existingSubscriptionPrice / $existingSubscription->bill->created_at->daysInMonth;
                    $daysRemaining = Carbon::now()->diffInDays($existingSubscription->ends_at);

                    CreditLine::create([
                        'description' => __('billing.subscription_remainder') . ' - ' . $existingSubscription->billing_description(),
                        'total' => round($pricePerDay * $daysRemaining, 2),
                        'currency_id' => $existingSubscription->store->currency_id,
                        'creditable_type' => 'App\\Models\\Subscription',
                        'creditable_id' => $existingSubscription->id,
                        'store_slug' => $existingSubscription->store_slug,
                    ]);
                }

                // cancel the current (old) subscription immediately
                $existingSubscription->update([
                    'auto_renew' => false,
                    'ends_at' => $subscription->starts_at,
                ]);
            }
        }

        if ($bill instanceof Bill && $bill->total > 0) {
            // free first subscription for shopify users
            if ($promo === 'shopify') {
                Log::info('SubscriptionsRepository create subscription - shopify');
                $bill->update(['total' => 0]);
            }

            $transaction = $billingRepository->chargeSubscription($subscription, $bill);
            if (isset($transaction['fail'])) {
                Log::info('SubscriptionsRepository subscription transaction failed', $transaction);
                $bill->delete();
                $subscription->delete();
            }
        }

        foreach ($prices as $price) {
            $subscription->prices()->create($price);
        }

        UserPositiveNotification::dispatch(auth()->user(), __('user_notifications.subscriptions.created'));
        return ($subscribable instanceof Plan) ? $store->plan_subscription()->first() : $subscription;
    }

    public function cancel($subscription)
    {
        return $subscription->update(['auto_renew' => false]);
    }
}
