<?php

namespace App\Repositories\Integrations\Shopify\Traits;

use App\Models\ShopifyShop;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

trait GqlApiTrait
{
    private $gqlApiClient;

    /**
     * Get a GraphQL API client
     * @param ShopifyShop $shopifyShop
     * @param string $baseUrl
     *
     * @return \Illuminate\Http\Client\PendingRequest
     */
    public function gqlApiClient($shopifyShop = false, $baseUrl = false)
    {
        if ($this->gqlApiClient) {
            return $this->gqlApiClient;
        }

        // Check if ShopifyShop object is passed and has valid token and domain
        if (!$shopifyShop || !$shopifyShop->token || !$shopifyShop->domain) {
            Log::error('Missing token or domain for ShopifyShop');
            $token = $this->token;
            $shopifyDomain = $this->shopifyDomain;
        } else {
            // Use token and domain from the passed ShopifyShop object
            $token = $shopifyShop->token;
            $shopifyDomain = $shopifyShop->domain;
        }

        // Log the token and domain
        Log::info('Using Shopify token and domain:', [
            'token' => $token,
            'domain' => $shopifyDomain
        ]);

        if (!$baseUrl) {
            $baseUrl = '/admin/api/' . config('shopify.apiVersion') . '/graphql.json';
        }

        $baseUrl = 'https://' . $shopifyDomain . $baseUrl;

        // Log the final base URL being used for the API request
        Log::info('GraphQL Base URL:', ['baseUrl' => $baseUrl]);

        return Http::baseUrl($baseUrl)
            ->withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json'
            ]);


            
    }

    /**
     * Make a GraphQL API request
     * @param ShopifyShop $shopifyShop
     * @param string $query
     * @param array $variables
     * @param bool $isSecondAttempt
     * @return array (result or fail)
     */
    public function makeGqlApiRequest($shopifyShop, $query, $variables = [], $isSecondAttempt = false, $baseUrl = false)
    {
        if (!$shopifyShop || !$shopifyShop->token || !$shopifyShop->domain) {
            // Log error if ShopifyShop token or domain is invalid
            Log::error('Invalid ShopifyShop object or missing token/domain');
            return $this->fail('shopifyShop.invalid', 401, [
                'shopifyShop' => $shopifyShop
            ]);
        }

        try {
            // Send the request to Shopify GraphQL API
            $response = $this->gqlApiClient($shopifyShop, $baseUrl)
                ->post('', [
                    'query' => $query,
                    'variables' => $variables
                ])
                ->body();
        } catch (ConnectionException | RequestException $e) {
            // Log the error if the request fails
            Log::error('Error making GraphQL request', [
                'error' => $e->getMessage(),
                'shopifyShop' => $shopifyShop
            ]);
            return $this->fail('shopify.noToken');
        }

        if (!strlen($response)) {
            // Handle empty response
            Log::warning('Empty response received from Shopify API');
            return $this->fail('shopify.emptyResponse');
        }

        $response = json_decode($response, true);

        // Check for errors in the GraphQL response
        if (isset($response['errors'])) {
            Log::error('GraphQL error response', [
                'shopifyShop' => $shopifyShop->store->name,
                'response' => $response,
                'query' => $query,
                'variables' => $variables
            ]);
            return $this->fail('shopify.errorResponse', 500, [
                'store' => $shopifyShop->store->name,
                'response' => $response,
                'query' => $query,
                'variables' => $variables
            ]);
        }

        return $response;
    }
}
