<?php

namespace App\Repositories\Invoicing;

use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use App\Models\Delivery;
use Illuminate\Http\Client\ConnectionException;

use Log;

class MorningRepository extends InvoicingRepository
{
    private $token = '';
    private $tokenExpiry = '';

    private $cardTypes = [
        'isracard' => 1,
        'visa' => 2,
        'mastercard' => 3,
        'american express' => 4,
        'americanexpress' => 4,
        'amex' => 4,
        'diners' => 5,
    ];

    /**
     * Get a JWT token from the morning API
     *
     * @return bool
     */
    public function getJwt()
    {
        $response = json_decode(Http::post(rtrim(config('invoicing.morning.apiRoot'), '/') . '/account/token', [
            'id' => config('invoicing.morning.apiKey'),
            'secret' => config('invoicing.morning.apiSecret'),
        ])->body(), true);

        if (isset($response['errorCode'])) {
            Log::error('invoicing.getJwt', $response);
            return false;
        }

        $this->token = $response['token'];
        $this->tokenExpiry = $response['expires'];
        return true;
    }

    /**
     * Make an API request to the morning API
     *
     * @param string $endpoint
     * @param array $data
     * @param string $method
     * @param bool $retry
     * @return bool|array
     */
    public function makeApiRequest($endpoint, $data = [], $method = 'post', $retry = false)
    {
        // build endpoint url
        if (!strstr($endpoint, config('invoicing.morning.apiRoot'))) {
            $endpoint = rtrim(config('invoicing.morning.apiRoot'), '/') . '/' . $endpoint;
        }

        // if no jwt or expired jwt
        if (!strlen($this->token) || Carbon::create($this->tokenExpiry)->isBefore(Carbon::now())) {
            if (!$this->getJwt()) {
                return false;
            }
        }

        try {
            if ($method === 'post') {
                $response = json_decode(Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->token
                ])->withBody(json_encode($data), 'application/json')
                    ->post($endpoint), true);
            } else {
                $response = json_decode(Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->token
                ])->send($method, $endpoint, $data)->body(), true);
            }
        } catch (ConnectionException $e) {
            Log::error('invoicing.makeApiRequest', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            return false;
        }


        if (isset($response['errorCode'])) {
            if (!$retry && intval($response['errorCode']) === 401) {
                // try again with new token
                $this->token = '';
                return $this->makeApiRequest($endpoint, $data, 'post', true);
            } else {
                if (intval($response['errorCode']) !== 404) {
                    Log::error('invoicing.makeApiRequest', [
                        'endpoint' => $endpoint,
                        'response' => $response,
                        'data' => $data,
                    ]);
                }
                return false;
            }
        }

        return $response;
    }

    /**
     * Get the remote client of a store from the morning API
     * @param \App\Models\Store $store
     * @return array
     */
    public function getRemoteClient($store)
    {
        $clientResponse = $this->makeApiRequest('client/search', [
            'email' => str_replace(["\r", "\n"], "", $store->user->email),
            'page' => 1,
            'pageSize' => 20,
        ]);

        if (!isset($clientResponse['items']) || !count($clientResponse['items'])) {
            return [];
        }

        if (count($clientResponse['items']) === 1) {
            return $clientResponse['items'][0];
        }

        foreach ($clientResponse['items'] as $client) {
            if ($client['active']) {
                return $client;
            }
        }

        return [];
    }

    /**
     * Update the remote client of a store from the morning API
     * @param int $clientId
     * @param array $clientData
     * @return bool
     */
    public function updateRemoteClient($clientId, $clientData)
    {
        $clientResponse = $this->makeApiRequest('clients/' . $clientId, $clientData);
        return false;
    }

    /**
     * Prepare the income rows for the invoice
     * @param \Illuminate\Database\Eloquent\Collection $bills
     * @param string $currencyIso
     * @return array
     */
    public function getIncomeRows($bills, $currencyIso, $creditLines = null)
    {
        $incomeRows = [];
        $deliveriesData = [];
        $total = 0;
        foreach ($bills as $bill) {
            $price = $bill->getTotalWithTax();
            $total += $price;
            if ($bill->billable instanceof Delivery) {
                $shippingCode = $bill->billable->polygon->shipping_code->code;

                if (!isset($deliveriesData[$shippingCode])) {
                    $deliveriesData[$shippingCode] = [
                        'description' => __('shipping_codes.no_date.' . $shippingCode),
                        'quantity' => 0,
                        'price' => 0,
                        'currency' => $currencyIso,
                        'vatType' => 1 // Item vat type calculation (0 = default, 1 = included, 2 = exempt)
                    ];
                }
                $deliveriesData[$shippingCode]['price'] = round($price + $deliveriesData[$shippingCode]['price'], 2);
                $deliveriesData[$shippingCode]['quantity']++;
            } else {
                $incomeRows[] = [
                    'description' => $bill->description,
                    'quantity' => 1,
                    'price' => $price,
                    'currency' => $currencyIso,
                    'vatType' => 1 // Item vat type calculation (0 = default, 1 = included, 2 = exempt)
                ];
            }
        }

        foreach ($deliveriesData as $deliveryData) {
            $deliveryData['description'] = $deliveryData['quantity'] . ' * ' . $deliveryData['description'];
            $deliveryData['quantity'] = 1;
            $incomeRows[] = $deliveryData;
        }

        if (!is_null($creditLines)) {
            $creditsTotal = 0;
            foreach ($creditLines as $creditLine) {
                $creditsTotal += $creditLine->total;
            }
            if ($creditsTotal > 0) {
                $total -= $creditsTotal;
                $incomeRows[] = [
                    'description' => __('billing.credits_lines'),
                    'quantity' => 1,
                    'price' => ($creditsTotal * -1),
                    'currency' => $currencyIso,
                    'vatType' => 1 // Item vat type calculation (0 = default, 1 = included, 2 = exempt)
                ];
            }
        }

        return [
            'rows' => $incomeRows,
            'total' => round($total, 2),
        ];
    }

    /**
     * Prepare the payment data for the invoice
     * @param \App\Models\Transaction $transaction

     * @return array
     */
    public function getPaymentData($transaction)
    {
        // required parameters
        $paymentData = [
            'date' => $transaction->created_at->toDateString(), // Payment date in the format YYYY-MM-DD
            'type' => 3, // credit card
            'price' => $transaction->total,
            'currency' => $transaction->store->currency->iso,
        ];

        // optional parameters
        if (isset($transaction->payment_method->mask)) {
            $paymentData['cardNum'] = substr($transaction->payment_method->mask, -4); // last 4 digits of card
        }

        if (isset($transaction->transaction_data['payme_transaction_id'])) {
            $paymentData['transactionId'] = substr($transaction->transaction_data['payme_transaction_id'], -4); // last 4 digits of card
        }

        if (
            isset($transaction->payment_method->card_type) &&
            isset($this->cardTypes[mb_strtolower($transaction->payment_method->card_type)])
        ) {
            $paymentData['cardType'] = $this->cardTypes[mb_strtolower($transaction->payment_method->card_type)];
        }

        return $paymentData;
    }

    /**
     * Prepare the client data for the invoice
     * @param \App\Models\Store $store
     * @return array
     */
    public function getClientData($store)
    {
        $billingAddress = $store->billing_address;
        if (!$billingAddress) {
            $billingAddress = $store->addresses->first();
        }

        // get english address
        $billingAddress = $this->addressesRepo->get($billingAddress);

        $streetAddress = $billingAddress['street'] . ' ' . $billingAddress['number'];
        if (!is_null($billingAddress['line2']) && strlen($billingAddress['line2'])) {
            $streetAddress .= ', ' . $billingAddress['line2'];
        }

        $name = (!is_null($billingAddress['company_name']) && strlen($billingAddress['company_name'])) ? $billingAddress['company_name'] : ($billingAddress['first_name'] . ' ' . $billingAddress['last_name']);

        $clientData = [
            'name' => $name,
            'emails' => [str_replace(["\r", "\n"], "", $store->user->email)],
            'add' => true, // Add a temporary client to the clients' list
            'address' => $streetAddress,
            'city' => $billingAddress['city'],
            'country' => mb_strtoupper(config('countries.isoFromCountry.' . mb_strtolower($billingAddress['country']))), // Client 2-letter ISO country code
            'phone' => $store->phone,
        ];

        if (isset($billingAddress['tax_id']) && !is_null($billingAddress['tax_id'])) {
            $clientData['taxId'] = $billingAddress['tax_id'];
        }

        $remoteClient = $this->getRemoteClient($store);
        if (count($remoteClient)) {
            // update the existing client
            $this->makeApiRequest('clients/' . $remoteClient['id'], $clientData);
            $clientData = ['id' => $remoteClient['id']];
        }

        return $clientData;
    }

    /**
     * Generate a proforma invoice for a collection of bills
     * @param \App\Models\Store $store
     * @param \Illuminate\Database\Eloquent\Collection $bills
     * @return string|bool
     */
    public function generateProformaInvoice($store, $bills)
    {
        $documentDate = Carbon::now()->toDateString();
        $incomeRows = $this->getIncomeRows($bills, $store->currency->iso);
        $total = $incomeRows['total'];
        $incomeRows = $incomeRows['rows'];

        $requestData = [
            // required:
            'type' => 300, // Document type (300 = חשבון עסקה)
            'lang' => 'he', // Primary language
            'currency' => $store->currency->iso, // Primary currency
            'vatType' => 0, // Vat type for that document (0 = default, 1 = exempt, 2 = mixed)

            // not required:
            'date' => $documentDate, // Document date in the format YYYY-MM-DD
            'description' => $store->name . ' ' . Carbon::now()->monthName . ' ' . Carbon::now()->year, // Document's description
            'rounding' => false, // Round the amounts
            'signed' => true, // Digital sign the document
            'attachment' => true, // Attach the document in the email sent to recipient
            'client' => $this->getClientData($store),
            'income' => $incomeRows, // Income rows
            // 'remarks' => '', // Document's remarks
            // 'footer' => '', // Texts appearing in footer
            // 'emailContent' => '', // Custom extra text appearing in email sent to customer
            // 'dueDate' => '', // Document payment due date in the format YYYY-MM-DD
            // 'discount' => [
            //   'amount' => 0,
            //   'type' => 'sum' // 'sum' or 'percentage'
        ];

        $documentsResponse = $this->makeApiRequest('documents', $requestData);

        if (!isset($documentsResponse['id'])) {
            Log::info('invoice creation failed', [
                'documentsResponse' => $documentsResponse,
                'store' => $store->store_slug,
            ]);
            return false;
        }

        return $documentsResponse['url'][substr($store->locale->iso, 0, 2)];
    }

    /**
     * Generate an invoice for a transaction
     * @param \App\Models\Transaction $transaction
     * @return \App\Models\Transaction
     */
    public function generateInvoice($transaction)
    {
        if (gettype($transaction->transaction_data) === 'string') {
            $transaction->transaction_data = json_decode($transaction->transaction_data, true);
        }

        $documentDate = Carbon::now()->toDateString();
        $incomeRows = $this->getIncomeRows($transaction->bills, $transaction->store->currency->iso, $transaction->credit_lines);
        $total = $incomeRows['total'];
        $incomeRows = $incomeRows['rows'];

        if ($transaction->total !== $total) {
            Log::notice($transaction->store_slug . ' *** ' . $transaction->description, [
                'transaction' => $transaction->total,
                'bills' => $total,
            ]);
            return $transaction;
        }

        $requestData = [
            // required:
            'type' => 320, // Document type (320 = חשבונית מס קבלה)
            'lang' => 'he', // Primary language
            'currency' => $transaction->store->currency->iso, // Primary currency
            'vatType' => 0, // Vat type for that document (0 = default, 1 = exempt, 2 = mixed)

            // not required:
            'date' => $documentDate, // Document date in the format YYYY-MM-DD
            'description' => $transaction->description, // Document's description
            'rounding' => false, // Round the amounts
            'signed' => true, // Digital sign the document
            'attachment' => true, // Attach the document in the email sent to recipient
            'client' => $this->getClientData($transaction->store),
            'income' => $incomeRows, // Income rows
            'payment' => [$this->getPaymentData($transaction)],
            // 'remarks' => '', // Document's remarks
            // 'footer' => '', // Texts appearing in footer
            // 'emailContent' => '', // Custom extra text appearing in email sent to customer
            // 'dueDate' => '', // Document payment due date in the format YYYY-MM-DD
            // 'discount' => [
            //   'amount' => 0,
            //   'type' => 'sum' // 'sum' or 'percentage'
        ];

        if (number_format($total, 2, '.', '') !== number_format($requestData['payment'][0]['price'], 2, '.', '')) {
            Log::info('InvoicingRepository - unmatching price betweens bills and transaction', [
                'transaction_id' => $transaction->id,
                'store' => $transaction->store->name,
                'Transaction Total' => $requestData['payment'][0]['price'],
                'Bills Total' => $total,
            ]);
        } else {
            $documentsResponse = $this->makeApiRequest('documents', $requestData);

            if (isset($documentsResponse['id'])) {
                if (!$transaction->update(['invoice_remote_id' => $documentsResponse['id']])) {
                    Log::info('invoice transaction update failed', [
                        'transaction' => $transaction,
                        'store' => $transaction->store->name,
                    ]);
                }
            } else {
                Log::info('invoice creation failed', [
                    'documentsResponse' => $documentsResponse,
                    'transaction' => $transaction->id,
                    'store' => $transaction->store_slug,
                ]);
            }
        }

        return $transaction;
    }

    /**
     * Get invoice url
     *
     * @param \App\Models\Store $store
     * @param string $invoiceId
     * @param \App\Models\Locale $locale
     *
     * @return string|array [fail => true, error => error message, code => error code]
     */
    public function getInvoiceUrl($store, $invoiceId, $locale = null)
    {
        $invoiceUrls = $this->makeApiRequest('documents/' . $invoiceId . '/download/links', [], 'get');
        if (!$invoiceUrls) {
            Log::info('getInvoiceUrl failed', [
                'remoteid' => $invoiceId,
                'response' => $invoiceUrls,
            ]);
            return $this->fail('invoice.notFound');
        }

        $localeSlug = 'origin';
        if ($locale !== false) {
            if (isset($invoiceUrls[$locale->iso])) {
                $localeSlug = $locale->iso;
            } else if (isset($invoiceUrls[$locale->ietf])) {
                $localeSlug = $locale->ietf;
            }
        }

        return $invoiceUrls[$localeSlug];
    }
}
