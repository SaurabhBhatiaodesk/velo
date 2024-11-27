<?php

namespace App\Repositories\Integrations\Shopify\Traits;

use App\Models\ShopifyShop;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

trait RestApiTrait
{
    private $restApiClient;

    /**
     * Get an API client
     * @param ShopifyShop $shopifyShop
     * @param string $baseUrl
     *
     * @return \Illuminate\Http\Client\PendingRequest
     */
    public function restApiClient($shopifyShop = false, $baseUrl = false)
    {
        if ($this->restApiClient) {
            return $this->restApiClient;
        }
        if (!$shopifyShop || !$shopifyShop->token || !$shopifyShop->domain) {
            $token = $this->token;
            $shopifyDomain = $this->shopifyDomain;
        } else {
            $token = $shopifyShop->token;
            $shopifyDomain = $shopifyShop->domain;
        }
        if (!$baseUrl) {
            $baseUrl = '/admin/api/' . config('shopify.apiVersion');
        }
        $baseUrl = 'https://' . $shopifyDomain . $baseUrl;

        return Http::baseUrl($baseUrl)
            ->withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json'
            ]);
    }

    /**
     * Make an API request
     * @param ShopifyShop $shopifyShop
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @param bool $isSecondAttempt
     * @return array
     */
    public function makeRestApiRequest($shopifyShop, $endpoint, $data = [], $method = 'post', $isSecondAttempt = false, $baseUrl = false)
    {
        if (!$shopifyShop || !$shopifyShop->token || !$shopifyShop->domain) {
            return $this->fail('shopifyShop.invalid', 401, [
                'shopifyShop' => $shopifyShop
            ]);
        }
        try {
            $response = $this->restApiClient($shopifyShop, $baseUrl)
                ->send($method, $endpoint, $data)
                ->body();
        } catch (ConnectionException $e) {
            Log::error('shopify.makeRestApiRequest', [
                'error' => $e->getMessage(),
            ]);
            return $this->fail('shopify.noToken');
        }

        if (!strlen($response)) {
            return $this->fail('shopify.emptyResponse');
        }

        $response = json_decode($response, true);
        // return the response
        return $response;
    }
}
