<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Store;
use App\Models\Subscription;
use App\Repositories\SubscriptionsRepository;
use App\Http\Requests\Models\Subscriptions\BuyRequest;
use App\Http\Requests\Models\Subscriptions\ToggleRequest;

class SubscriptionsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function buy(Store $store, BuyRequest $request)
    {
        $inputs = $request->all();
        $existingSubscription = $store->active_subscriptions()
            ->where('subscribable_id', $inputs['subscribable_id'])
            ->where('subscribable_type', $inputs['subscribable_type'])
            ->first();

        if ($existingSubscription) {
            return $this->respond([
                'message' => 'user_notifications.subscriptions.existing',
            ], 400);
        }

        $subscribable = call_user_func($inputs['subscribable_type'] . '::find', $inputs['subscribable_id']);
        if (!$subscribable) {
            return $this->respond([
                'message' => 'subscription.noSubscribable',
            ], 404);
        }
        $repo = new SubscriptionsRepository();
        $result = $repo->create($store, $subscribable);

        if (isset($result['fail'])) {
            return $this->respond([
                'message' => 'subscription.createFailed',
            ], 500);
        }

        return $this->respond([
            'subscriptions' => $store->active_subscriptions()->with('subscribable')->get(),
        ]);
    }

    public function toggle(Store $store, Subscription $subscription, ToggleRequest $request)
    {
        $subscription->update(['auto_renew' => !$subscription->auto_renew]);
        return $this->respond([
            'subscriptions' => $store->active_subscriptions()->with('subscribable')->get(),
        ]);
    }

    public function getStore(Store $store)
    {
        return $this->respond($store->active_subscriptions()->with('subscribable')->get());
    }
}
