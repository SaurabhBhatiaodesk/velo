<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Bill;
use App\Models\Transaction;
use App\Enums\DeliveryStatusEnum;
use App\Models\TaxPolygon;
use App\Repositories\Invoicing\InvoicingRepository;
use App\Repositories\Clearance\PaymeRepository;
use Illuminate\Http\Request;
use App\Http\Requests\Models\Users\UpdateNameRequest;
use App\Http\Requests\Models\Users\UpdatePasswordRequest;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;


class SettingsController extends Controller
{
    public function getDetails(Store $store)
    {
        return $this->respond([]);
    }

    public function getSecurity(Store $store)
    {
        $this->respond(['security' => 'security']);
    }

    public function getTeam(Store $store)
    {
        $this->respond(['team' => 'team']);
    }

    public function getNotifications(Store $store)
    {
        $this->respond(['notifications' => 'notifications']);
    }

    public function getBilling(Store $store)
    {
        $unpaidBills = $store->bills()
            ->whereNull('transaction_id')
            ->get();

        foreach ($unpaidBills as $i => $bill) {
            if ($bill->billable_type === 'App\\Models\\Delivery') {
                switch ($bill->billable->status) {
                    case DeliveryStatusEnum::Placed:
                    case DeliveryStatusEnum::Updated:
                    case DeliveryStatusEnum::AcceptFailed:
                        $unpaidBills->forget($i);
                        break;
                    case DeliveryStatusEnum::Rejected:
                    case DeliveryStatusEnum::Cancelled:
                    case DeliveryStatusEnum::Refunded:
                        $unpaidBills->forget($i);
                        break;
                    default:
                        $bill->load('billable.polygon', 'billable.order');
                }
            }
        }

        $upcomingBills = [];
        $overdueBills = [];
        if ($unpaidBills->count()) {
            $lastPaymentDate = $store->getLastPaymentDate();
            $taxPolygons = new TaxPolygon();
            $taxPolygons = $taxPolygons->getForAddress($unpaidBills->first()->store->getBillingAddress());
            $unpaidBills = $unpaidBills->toArray();
            foreach ($unpaidBills as $i => $bill) {
                $bill['taxes'] = [];
                foreach ($taxPolygons as $polygon) {
                    $bill['taxes'][] = [
                        'name' => $polygon->name,
                        'amount' => ($polygon->amount) ? $polygon->amount : 0,
                        'precentage' => ($polygon->precentage) ? $polygon->precentage : 0,
                        'total' => $polygon->calculateTax($bill['total']),
                    ];
                }

                if ($lastPaymentDate !== false && Carbon::parse($bill['created_at'])->isBefore($lastPaymentDate)) {
                    $overdueBills[] = $bill;
                } else {
                    $upcomingBills[] = $bill;
                }
            }
        }

        return $this->respond([
            'upcoming_bills' => $upcomingBills,
            'overdue_bills' => $overdueBills,
            'payment_methods' => $store->payment_methods,
            'subscriptions' => $store->active_subscriptions,
            'transactions' => $store->transactions()->whereHas('bills')->get(),
            'valid_credit_lines' => $store->valid_credit_lines,
        ]);
    }

    public function payOverdue(Store $store)
    {
        $repo = new PaymeRepository();
        $res = $repo->chargePending($store);
        if (isset($res['fail'])) {
            return $this->fail($res);
        }
        return $this->getBilling($store);
    }

    public function getTransactionBills(Store $store, Transaction $transaction)
    {
        $bills = $transaction->bills()->with('billable')->get();
        foreach ($bills as $bill) {
            // check why this is necessary
            if ($bill->billable_type === 'App\\Models\\Delivery') {
                $bill->billable->load('polygon', 'order');
            }
        }

        return $this->respond([
            'bills' => $bills,
            'creditLines' => $transaction->credit_lines,
        ]);
    }

    public function getInvoiceUrl(Store $store, $invoiceId)
    {
        $repo = new InvoicingRepository();
        $invoiceUrl = $repo->getInvoiceUrl($store, $invoiceId, auth()->user()->locale);
        if (isset($invoiceUrl['fail'])) {
            return $invoiceUrl;
        }

        return $this->respond([
            'invoice_url' => $invoiceUrl
        ]);
    }

    private function payForIntegration($store, $apiUser, $priceSlug = 'integration')
    {
        $subscription = $store->subscriptions()->create([
            'store_slug' => $store->slug,
            'starts_at' => Carbon::now(),
            // synchronize to end of plan subscription to synchoronize with the store's billing cycle
            'ends_at' => $store->plan_subscription->ends_at,
            // auto_renew is false because we need to count subscriptions by type when renewing the plan
            'auto_renew' => false,
            'renewed_from' => null,
            'subscribable_type' => $apiUser->getMorphClass(),
            'subscribable_id' => $apiUser->id,
        ]);
        $priceFactor = $store->plan_subscription->ends_at->diffInDays(Carbon::now()) / $store->plan_subscription->ends_at->diffInDays($store->plan_subscription->starts_at);
        $chargeResult = $store->billingRepo()->billAndChargeSubscription($subscription, $priceSlug, $priceFactor);
        if (isset($chargeResult['fail'])) {
            if ($subscription->bill) {
                $subscription->bill->delete();
            }
            $subscription->delete();
            $apiUser->delete();
            return $chargeResult;
        }
        return $apiUser;
    }

    public function getIntegrations(Store $store)
    {
        $woocommerce = $store->api_users()->where('slug', 'wp')->first();
        if ($woocommerce) {
            $woocommerce = [
                'key' => $woocommerce->key,
                'active' => $woocommerce->active,
                'secret' => '',
            ];
        }

        $venti = null;
        if ($store->plan_subscription) {
            $venti = $store->api_users()->where('slug', 'venti')->first();
        }

        $shopify = null;
        if ($store->shopifyShop) {
            $shopify = [
                'active' => $store->shopifyShop->active,
                'domain' => $store->shopifyShop->domain,
            ];
        }

        return $this->respond([
            'woocommerce' => $woocommerce,
            'shopify' => $store->shopifyShop,
            'venti' => $venti,
            'magento' => null,
            'salesforce' => null
        ]);
    }

    public function getWoocommerceSecret(Store $store)
    {
        $apiUser = $store->api_users()->where('slug', 'wp')->first();
        if (!$apiUser) {
            return $this->respond([
                'message' => 'settings.integrations.woocommerce.notIntegrated'
            ], 422);
        }
        return $this->respond([
            'secret' => $apiUser->secret,
        ]);
    }

    public function integrateWoocommerce(Store $store)
    {
        $apiUser = $store->api_users()->where('slug', 'wp')->first();
        if (!$apiUser) {
            $apiUser = $store->api_users()->create([
                'slug' => 'wp',
            ]);
            $apiUser = $this->payForIntegration($store, $apiUser);
            if (is_array($apiUser) && isset($apiUser['fail'])) {
                return $this->fail($apiUser);
            }
        }

        return [
            'active' => $apiUser->active,
            'key' => $apiUser->key,
            'secret' => '',
        ];
    }

    public function toggleWoocommerce(Store $store)
    {
        $apiUser = $store->api_users()->where('slug', 'wp')->first();
        if (!$apiUser) {
            return $this->respond(['error' => 'integration.notFound'], 403);
        }

        $apiUser->active = !$apiUser->active;
        if (!$apiUser->save()) {
            return $this->respond(['error' => 'integration.updateFailed'], 403);
        }

        return $this->respond([
            'active' => $apiUser->active
        ]);
    }

    public function saveVentiSettings(Store $store, Request $request)
    {
        $inputs = $request->all();
        $inputs['active'] = filter_var($inputs['active'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        $inputs['settings']['charge'] = filter_var($inputs['settings']['charge'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        $inputs['settings']['returnRate'] = floatVal($inputs['settings']['returnRate']);
        $inputs['settings']['replacementRate'] = floatVal($inputs['settings']['replacementRate']);

        $apiUser = $store->api_users()->where('slug', 'venti')->first();
        if (!$apiUser) {
            $inputs['slug'] = 'venti';
            $apiUser = $store->api_users()->create($inputs);
            if (!$apiUser) {
                return $this->fail('apiUser.createFailed');
            }
        } else if (!$apiUser->update($inputs)) {
            return $this->fail('apiUser.updateFailed');
        }

        return $apiUser;
    }

    public function toggleVentiActiveSettings(Store $store)
    {
        $apiUser = $store->api_users()->where('slug', 'venti')->first();
        if (!$apiUser) {
            $apiUser = $store->api_users()->create([
                'slug' => 'venti'
            ]);
        }
        $apiUser->update(['active' => !$apiUser->active]);
        return $apiUser;
    }

    public function getBill(Store $store, Bill $bill)
    {
        if ($store && $bill && $bill->store_slug === $store->slug) {
            if ($bill->billable_type === 'App\\Models\\Delivery') {
                $bill->load('billable', 'billable.order');
            }
            return $this->respond($bill);
        }

        return $this->respond([
            'error' => 'bill.notFound',
            'extra' => [
                'store' => $store,
                'bill' => $bill,
            ]
        ], 404);
    }

    public function getAccount(Store $store)
    {
        if (!auth()->check()) {
            return $this->respond([
                'error' => 'auth.unautorized'
            ], 401);
        }

        $user = auth()->user();

        if (
            !$user->stores->contains($store->id) &&
            !$user->team_stores->contains($store->id) &&
            !$user->isElevated()
        ) {
            return $this->respond([
                'error' => 'auth.forbidden'
            ], 403);
        }

        return $this->respond(auth()->user());
    }

    public function setAccountName(UpdateNameRequest $request, Store $store)
    {
        if (!auth()->user()->update($request->all())) {
            return $this->respond([
                'error' => 'user.updateFailed'
            ], 500);
        }

        return $this->respond();
    }

    public function setAccountPassword(UpdatePasswordRequest $request, Store $store)
    {
        $user = auth()->user();
        if (
            !$user->isElevated() &&
            !Hash::check($request->input('currentPassword'), auth()->user()->password)
        ) {
            return $this->respond([
                'error' => 'auth.unauthorized'
            ], 401);
        }

        if (!$store->user->update(['password' => $request->input('newPassword')])) {
            return $this->respond([
                'error' => 'user.updateFailed'
            ], 500);
        }

        return $this->respond();
    }
}
