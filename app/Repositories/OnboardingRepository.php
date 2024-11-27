<?php

namespace App\Repositories;

use App\Repositories\BaseRepository;
use App\Repositories\AddressesRepository;
use App\Models\Locale;
use App\Models\ShippingCode;
use App\Models\Address;
use App\Models\Currency;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Store;
use App\Models\ShopifyShop;
use Illuminate\Support\Str;

class OnboardingRepository extends BaseRepository
{
    public function onboard($request)
    {
        $english = Locale::where('iso', 'en_US')->first();
        $payload = $this->validateRequest($request);
        $user = auth()->user();
        if (!$user->first_name || !$user->first_name) {
            if (!$user->first_name) {
                $user->first_name = $payload['details']['first_name'];
            }
            if (!$user->last_name) {
                $user->last_name = $payload['details']['last_name'];
            }

            if (!$user->save()) {
                return [
                    'success' => false,
                    'error' => 'updateFailed'
                ];
            }
        }

        $store = $user->stores()->where('name', $payload['details']['name'])->first();
        if ($store && $store->user->email !== $user->email) {
            return [
                'success' => false,
                'error' => 'duplicateStore'
            ];
        }
        if (!$store) {
            $plan = Plan::find($payload['plan']['plan']['id']);
            if (!$plan) {
                return [
                    'success' => false,
                    'error' => 'invalidPlan'
                ];
            }

            $store = [
                'name' => $payload['details']['name'],
                'slug' => $payload['details']['slug'],
                'first_name' => $payload['details']['first_name'],
                'last_name' => $payload['details']['last_name'],
                'phone' => $payload['details']['phone'],
                'website' => $payload['details']['website'],
                'user_id' => $user->id,
            ];

            if (isset($payload['details']['currency_id'])) {
                $store['currency_id'] = $payload['details']['currency_id'];
            } else {
                $currencyIso = 'USD';
                if (
                    strtolower($payload['details']['addresses'][0]['country']) === 'israel' ||
                    $payload['details']['addresses'][0]['country'] === 'ישראל'
                ) {
                    $currencyIso = 'ILS';
                    $store['week_starts_at'] = 7;
                }

                if (
                    strtolower($payload['details']['addresses'][0]['country']) === 'united states' ||
                    $payload['details']['addresses'][0]['country'] === 'ארצות הברית'
                ) {
                    $store['imperial_units'] = true;
                }

                $currency = Currency::where('iso', $currencyIso)->first();
                $store['currency_id'] = $currency->id;
            }

            // If the slug is a duplicate, generate a new unique slug
            if (Store::where('slug', $payload['details']['slug'])->count() > 0) {
                // Append new random characters
                $payload['details']['slug'] .= '-' . Str::random(3);
                // Keep adding random characters until a unique slug is generated
                while (Store::where('slug', $payload['details']['slug'])->count() > 0) {
                    // Remove the last added random characters
                    $payload['details']['slug'] = mb_substr($payload['details']['slug'], 0, -4);

                    // Append new random characters
                    $payload['details']['slug'] .= '-' . Str::random(3); // Adjust the length of random characters as needed
                }
            }

            try {
                $store = Store::create($store);
            } catch (\Illuminate\Database\QueryException $e) {
                $errorCode = $e->errorInfo[1];

                // Check if the error is due to a duplicate entry
                if ($errorCode == 1062) { // MySQL specific error code for duplicate entry
                    // only the website can be a duplicate
                    return [
                        'success' => false,
                        'error' => 'duplicateWebsite'
                    ];
                }
            }

            if (!$store) {
                return [
                    'success' => false,
                    'error' => 'invalidDetails'
                ];
            }

            $paymentMethod = PaymentMethod::create([
                'mask' => $payload['payment']['card']['cardMask'],
                'holder_name' => $payload['payment']['card']['cardholderName'],
                'expiry' => $payload['payment']['card']['expiry'],
                'email' => $payload['payment']['payerEmail'],
                'phone' => $payload['payment']['payerPhone'],
                'social_id' => (isset($payload['payment']['payerSocialId'])) ? $payload['payment']['payerSocialId'] : null,
                'token' => $payload['payment']['token'],
                'default' => true,
                'store_slug' => $store->slug,
                'user_id' => $user->id,
            ]);

            if (!$paymentMethod) {
                $store->delete();
                return $this->fail('paymentMethod.invalid', 422);
            }

            $addresses = [];
            foreach ($payload['details']['addresses'] as $i => $address) {
                $address = array_merge($address, [
                    'addressable_type' => 'App\\Models\\Store',
                    'addressable_slug' => $store->slug,
                    'user_id' => $user->id,
                    'is_billing' => (count($addresses) === 0),
                    'is_pickup' => (count($addresses) === 0)
                ]);

                $addressesRepo = new AddressesRepository();
                $address = $addressesRepo->get($address);
                if ($address instanceof Address) {
                    $addresses[] = $address;
                }
            }

            if (!count($addresses)) {
                $store->delete();
                return $this->fail('address.invalid', 422);
            }

            $shopifyShop = ShopifyShop::whereNull('store_slug');
            if (isset($payload['details']['shopifyDomain']) && strlen($payload['details']['shopifyDomain'])) {
                $shopifyShop = $shopifyShop->where('domain', $payload['details']['shopifyDomain'])->first();
            } else if (
                isset($payload['shopify']) &&
                isset($payload['shopify']['shopify_domain']) &&
                strlen($payload['shopify']['shopify_domain']) &&
                isset($payload['shopify']['shopify_token']) &&
                strlen($payload['shopify']['shopify_token'])
            ) {
                $shopifyShop = $shopifyShop->where('domain', $payload['shopify']['shopify_domain'])->first();
            } else {
                $shopifyShop = $shopifyShop->where('email', $user->email)->first();
            }

            $subscription = SubscriptionsRepository::create($store, $plan, true, [], (!is_null($shopifyShop)) ? 'shopify' : false);
            if (isset($subscription['fail'])) {
                return $subscription;
            }

            if (!is_null($shopifyShop) && is_null($shopifyShop->store_slug)) {
                $shopifyShop->update(['store_slug' => $store->slug]);
            }

            $user->assignRole('store_owner');
        }

        return $store;
    }

    public function getData()
    {
        $data = [
            'plans' => Plan::where('is_public', true)->with('prices')->get(),
            'shippingCodes' => ShippingCode::all(),
        ];
        $user = auth()->user();
        $shopifyShop = ShopifyShop::whereNull('store_slug')->where('email', $user->email)->first();
        if ($shopifyShop) {
            $data['shopify'] = [
                'shopify_domain' => $shopifyShop->domain,
                'shopify_token' => $shopifyShop->token,
            ];
        }
        return $data;
    }
}
