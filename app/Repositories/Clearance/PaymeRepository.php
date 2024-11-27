<?php

namespace App\Repositories\Clearance;

use Illuminate\Support\Facades\Http;
use App\Models\Locale;
use App\Repositories\BillingRepository;
use App\Repositories\AddressesRepository;
use Log;

class PaymeRepository extends BillingRepository
{
    public function __construct()
    {
        $this->apiRoot = config('clearance.payme.apiRoot');
        $this->clearanceFee = 0.015;
    }

    private function apiClient()
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json'
        ]);
    }

    public function makeTransaction($paymentMethod, $description, $total)
    {
        $store = $paymentMethod->store;
        $eng = Locale::where('iso', 'en_US')->first();
        $addressesRepo = new AddressesRepository();

        $billingAddress = $store->getBillingAddress();
        if (!$billingAddress) {
            Log::info('Store ' . $store->slug . ' has no billing address');
            return $this->fail('bill.paymentFailed');
        }
        $apiKey = config('clearance.payme.apiKey.' . config('countries.isoFromCountry.' . strtolower($billingAddress->country)));
        if (!$apiKey) {
            $apiKey = config('clearance.payme.apiKey.default');
        }

        $transactionData = json_decode($this->apiClient()->post($this->apiRoot . '/generate-sale', [
            'seller_payme_id' => $apiKey,
            'sale_price' => intval($total * 100), // payme accept fractional price
            'currency' => $store->currency->iso,
            'product_name' => $description,
            'buyer_key' => $paymentMethod->token,
        ])->body(), true);

        if (isset($transactionData['status_code']) && !!$transactionData['status_code']) {
            Log::info('Store ' . $store->slug . ' payment failed', $transactionData);
            return $this->fail('bill.paymentFailed');
        }

        return [
            'description' => $description,
            'transaction_data' => $transactionData,
            'total' => $total,
            'payment_method_id' => $paymentMethod->id,
            'store_slug' => $store->slug,
        ];
    }
}
