<?php

namespace App\Repositories\Integrations\Shopify\Traits;

use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use App\Models\ShopifyShop;
use Illuminate\Support\Facades\Log;

trait InstallationTrait
{
    /**
     * Strips the shopify domain from the given string
     *
     * @param string $domain
     * @return string
     */
    private function stripShopifyDomain($domain)
    {
        if (strpos($domain, '://')) {
            $domain = explode('://', $domain);
            $domain = $domain[1];
        }
        if (strpos($domain, '.myshopify.com')) {
            $domain = explode('.myshopify.com', $domain);
            $domain = $domain[0];
        }
        return $domain;
    }

    /**
     * Installs the shopify app on the given store
     *
     * @param string $shopifyDomain
     * @return string | array Fail
     */
    public function shopifyInstall($shopifyDomain)
    {
        // get the shopify domain
        $shopifyDomain = $this->stripShopifyDomain($shopifyDomain);

        // get the login redirect URL
        return Socialite::driver('shopify')
            ->stateless()
            ->scopes(config('shopify.scopes'))
            ->with(['subdomain' => $shopifyDomain])
            ->redirect()
            ->getTargetUrl();
    }


    public function connect($store, $inputs)
    {
        if (!isset($inputs['domain']) || !isset($inputs['token'])) {
            return $this->fail('missing required inputs', 422);
        }

        $shopifyShop = ShopifyShop::where('domain', $inputs['domain'])->first();
        if (!$shopifyShop) {
            return $this->fail('shopifyShop.notFound');
        } else if (!is_null($shopifyShop->store_slug) && $shopifyShop->store_slug !== $store->slug) {
            return $this->fail('shopifyShop.alreadyConnected', 401);
        } else if (is_null($shopifyShop->store_slug)) {
            if (!$shopifyShop->update(['store_slug' => $store->slug])) {
                return $this->fail('shopifyShop.updateFailed');
            }
        }

        if (!$this->createCarrierService($shopifyShop) || !$this->bindWebhooks($shopifyShop)) {
            $shopifyShop->delete();
        }

        return $shopifyShop;
    }

    /**
     * Begins the shopify app authentication process
     *
     * @param string $shopifyDomain
     * @param \App\Models\Store $store
     * @return string | array Fail
     */
    public function authBegin($shopifyDomain, $store)
    {
        // get the shopify domain
        $shopifyDomain = $this->stripShopifyDomain($shopifyDomain);
        // get the store's ShopifyShop
        $shopifyShop = $store->shopifyShop;

        // if the store's doesn't have a ShopifyShop
        if (!$shopifyShop) {
            // try to find the ShopifyShop by domain
            $shopifyShop = ShopifyShop::where('domain', $shopifyDomain)->first();
            // if the ShopifyShop exists by domain
            if ($shopifyShop) {
                // if the ShopifyShop exists but is not connected to any store
                if (is_null($shopifyShop->store_slug)) {
                    // connect the ShopifyShop to the store
                    $shopifyShop->update(['store_slug' => $store->slug]);
                }
                // if the ShopifyShop is already connected to another store
                else if ($shopifyShop->store_slug !== $store->slug) {
                    // return a fail response
                    return $this->fail('shopifyShop.alreadyConnected');
                }
            }
        }

        // return a redirect url
        return Socialite::driver('shopify')
            ->stateless()
            ->scopes(config('shopify.scopes'))
            ->with(['subdomain' => $shopifyDomain])
            ->redirect()
            ->getTargetUrl();
    }

    public function authCallback()
    {
        try {
            $shopifyUser = Socialite::driver('shopify')->stateless()->user();
        } catch (\Exception $e) {
            return $this->fail('shopifyShop.unauthorized');
        }
        if (!$shopifyUser) {
            return $this->fail('shopifyShop.unauthorized');
        }
        $shopifyUser = json_decode(json_encode($shopifyUser), true);

        $shopifyShop = ShopifyShop::where('domain', $shopifyUser['user']['myshopify_domain'])->first();
        $user = User::where('email', $shopifyUser['email'])->first();

        if ($user) {
            if (!$shopifyShop) {
                $shopifyShop = new ShopifyShop([
                    'active' => false,
                    'shopify_id' => $shopifyUser['id'],
                    'domain' => $shopifyUser['user']['myshopify_domain'],
                    'name' => $shopifyUser['name'],
                    'email' => $shopifyUser['email'],
                    'token' => $shopifyUser['token'],
                ]);

                foreach ($user->stores as $store) {
                    if (
                        $store->phone === str_replace('+972', '0', $shopifyUser['user']['phone']) ||
                        $store->phone === str_replace('972', '0', $shopifyUser['user']['phone'])
                    ) {
                        if ($store->shopifyShop) {
                            $shopifyShop = $store->shopifyShop;
                        } else {
                            $shopifyShop->store_slug = $store->slug;
                        }
                        break;
                    } else {
                        foreach ($store->addresses as $address) {
                            if (
                                $address->phone === str_replace('+972', '0', $shopifyUser['user']['phone']) ||
                                $address->phone === str_replace('972', '0', $shopifyUser['user']['phone']) ||
                                (
                                    $address->city === $address->city &&
                                    $address->street === $address->street &&
                                    $address->number === $address->number
                                )
                            ) {
                                if ($store->shopifyShop) {
                                    $shopifyShop = $store->shopifyShop;
                                } else {
                                    $shopifyShop->store_slug = $store->slug;
                                }
                                break 2;
                            }
                        }
                    }
                }
            }

            if (is_null($shopifyShop->store_slug)) {
                foreach ($user->stores as $store) {
                    if (!$store->shopifyShop) {
                        $shopifyShop->store_slug = $store->slug;
                        break;
                    }
                }
            }
        } else {
            Log::info('shopifyAuthCallback - no user', [
                'shopifyShop' => $shopifyShop,
                'shopifyUser' => $shopifyUser,
            ]);
        }

        if (!is_null($shopifyShop->store_slug)) {
            $shopifyShop->save();
        }

        if (!is_null($shopifyShop->id) && !$shopifyShop->shopify_id) {
            $this->token = $shopifyUser['token'];
            $this->shopifyDomain = $shopifyUser['nickname'];
            $this->createCarrierService($shopifyShop);
            $this->bindWebhooks($shopifyShop);
        }

        $shopifyShopData = [
            'shopify_id' => $shopifyUser['id'],
            'name' => $shopifyUser['name'],
            'email' => $shopifyUser['email'],
            'token' => $shopifyUser['token'],
            'domain' => $shopifyUser['user']['myshopify_domain'],
        ];

        if (!$shopifyShop) {
            $shopifyShop = ShopifyShop::create($shopifyShopData);
            if (!$shopifyShop) {
                return $this->fail('shopifyShop.createFailed');
            }
        } else {
            if (!$shopifyShop->update($shopifyShopData)) {
                return $this->fail('shopifyShop.updateFailed');
            }
        }

        return [
            'success' => true,
            'jwt' => ($user) ? auth()->login($user) : null,
            'token' => $shopifyUser['token'],
            'domain' => $shopifyUser['user']['myshopify_domain'],
            'shopifyShop' => $shopifyShop,
        ];
    }

    public function removeCarrierService($shopifyShop)
    {
        $restApiClient = $this->restApiClient($shopifyShop);
        $carriersResponse = json_decode($restApiClient->get('carrier_services.json')->body(), true);
        if (!isset($carriersResponse['carrier_services'])) {
            return false;
        }
        foreach ($carriersResponse['carrier_services'] as $carrier) {
            if ($carrier['name'] === config('shopify.carrierServiceName')) {
                $response = json_decode($restApiClient->delete('carrier_services/' . $carrier['id'] . '.json')->body(), true);
                return true;
            }
        }
        return false;
    }

    public function createCarrierService($shopifyShop)
    {
        $carrierServiceData = [
            'name' => config('shopify.carrierServiceName'),
            'callback_url' => rtrim(config('app.url'), '/') . '/shopify/carrier_service_callback/' . $shopifyShop->domain,
            'carrier_service_type' => 'api',
            'format' => 'json',
            'active' => true,
            'service_discovery' => true,
        ];

        $restApiClient = $this->restApiClient($shopifyShop);
        $this->removeCarrierService($shopifyShop);
        $response = json_decode($restApiClient->post('carrier_services.json', ['carrier_service' => $carrierServiceData])->body(), true);

        if (isset($response['errors'])) {
            Log::info('failed creating carrier service for ' . $shopifyShop->store_slug, $response);
            return false;
        }
        return true;
    }

    public function bindWebhooks($shopifyShop)
    {
        $restApiClient = $this->restApiClient($shopifyShop);
        $webhookRoot = rtrim(config('app.url'), '/') . '/api/shopify/webhooks/' . $shopifyShop->domain . '/';
        $webhooksResponse = json_decode($restApiClient->get('webhooks.json')->body(), true);
        if (isset($webhooksResponse['webhooks'])) {
            // delete and rebind webhooks
            foreach ($webhooksResponse['webhooks'] as $i => $webhook) {
                if (str_contains($webhook['address'], $webhookRoot) || str_contains($webhook['address'], '.ngrok-free.app')) {
                    $restApiClient->delete('webhooks/' . $webhook['id'] . '.json');
                }
            }
        }

        foreach (config('shopify.webhooks.topics') as $topic) {
            $response = json_decode($restApiClient->post('webhooks.json', [
                'webhook' => [
                    'address' => $webhookRoot . str_replace('/', '-', $topic),
                    'topic' => $topic,
                    'format' => 'json'
                ]
            ])->body(), true);
        }

        return true;
    }

    public function validateAccessScopes($shopifyShop)
    {
        $scopes = $this->makeRestApiRequest($shopifyShop, 'access_scopes.json', [], 'get', false, '/admin/oauth');
        if (!isset($scopes['access_scopes'])) {
            Log::info('shopifyShop.invalidAccessScopes response', ['response' => $scopes]);
            return false;
        }
        $scopes = array_map(function ($scope) {
            return $scope['handle'];
        }, $scopes['access_scopes']);

        $missingScopes = array_diff(config('shopify.scopes'), $scopes);
        $redundantScopes = array_diff($scopes, config('shopify.scopes'));

        return count($missingScopes) + count($redundantScopes) === 0;
    }
}
