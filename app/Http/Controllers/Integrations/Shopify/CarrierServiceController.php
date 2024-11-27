<?php

namespace App\Http\Controllers\Integrations\Shopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\AddressesRepository;
use App\Repositories\ShippingCodesCheckRepository;
use App\Repositories\Integrations\Shopify\IntegrationRepository;
use App\Models\ShopifyShop;
use App\Models\Address;
use Carbon\Carbon;
use Log;

// https://shopify.dev/docs/api/admin-rest/2023-01/resources/carrierservice
class CarrierServiceController extends Controller
{
    private function formatTimeForShopify($dateTime)
    {
        return $dateTime->toDateString() . ' ' . $dateTime->toTimeString() . ' ' . str_replace(':', '', $dateTime->getOffsetString());
    }

    private function getShippingOptionArray($shopifyShop, $shippingOption, $price, $variables = [])
    {
        if (!isset($variables['service_name'])) {
            $variables['service_name'] = __('shipping_codes.' . $shippingOption['polygon']->shipping_code->code);
        }

        if (
            $shippingOption['polygon']->is_collection &&
            isset($shippingOption['external_service_name']) &&
            strlen($shippingOption['external_service_name']) > 0
        ) {
            $variables['service_name'] .= ' - ' . $shippingOption['external_service_name'];
        }

        $variables['service_code'] = $shippingOption['polygon']->shipping_code->code;
        if ($shippingOption['polygon']->is_collection) {
            $variables['service_code'] .= '_EXT_' . $shippingOption['external_service_id'];
        }
        $variables['service_code'] .= '_POL_' . $shippingOption['polygon']->id;

        if (!isset($variables['description'])) {
            $variables['description'] = __('shipping_codes.' . $shippingOption['polygon']->shipping_code->code . '_desc');
        }

        if (
            isset($shippingOption['pickup_station']) &&
            isset($shippingOption['pickup_station']['address']) &&
            strlen($shippingOption['pickup_station']['address'])
        ) {
            $variables['description'] .= ' - ' . $shippingOption['pickup_station']['address'];
        }

        return array_merge([
            // price in subunits (https://shopify.dev/api/admin-rest/2022-10/resources/carrierservice)
            'total_price' => strval(intval($price * 100)),
            'currency' => $shopifyShop->store->currency->iso,
            'phone_required' => !str_contains(config('app.url'), 'ngrok'),
        ], $variables);
    }


    private function otherDayRate($shopifyShop, $shippingOption, $price)
    {
        $closestDeliveryDate = $shopifyShop->store->getClosestDeliveryDate();
        if (!$closestDeliveryDate) {
            return [];
        }

        return $this->getShippingOptionArray($shopifyShop, $shippingOption, $price, [
            'service_name' => __('shipping_codes.' . $shippingOption['polygon']->shipping_code->code, ['date' => $closestDeliveryDate->format('d/m/o')]),
            'min_delivery_date' => $this->formatTimeForShopify(Carbon::createFromTimeString($shopifyShop->store->weekly_deliveries_schedule[Carbon::now($shopifyShop->store->timezone)->addDay()->dayOfWeekIso]['hours'], $shopifyShop->store->timezone)),
        ]);
    }

    private function sameDayRate($shopifyShop, $shippingOption, $price)
    {
        return $this->getShippingOptionArray($shopifyShop, $shippingOption, $price, [
            'service_name' => __('shipping_codes.' . $shippingOption['polygon']->shipping_code->code),
            'min_delivery_date' => $this->formatTimeForShopify(Carbon::createFromTimeString($shopifyShop->store->weekly_deliveries_schedule[Carbon::now($shopifyShop->store->timezone)->dayOfWeekIso]['hours'], $shopifyShop->store->timezone)),
            'max_delivery_date' => $this->formatTimeForShopify(Carbon::createFromTimeString($shopifyShop->store->weekly_deliveries_schedule[Carbon::now($shopifyShop->store->timezone)->dayOfWeekIso]['hours'], $shopifyShop->store->timezone)),
        ]);
    }

    private function ratesResponse($rates = [], $hideNextDayWhenSameDayIsAvailable = false)
    {
        $resultsInOrder = [
            [
                'code' => 'VELOAPPIO_ON_DEMAND',
                'rates' => [],
            ],
            [
                'code' => 'VELOAPPIO_SAME_DAY',
                'rates' => [],
            ],
            [
                'code' => 'VELOAPPIO_NEXT_DAY',
                'rates' => [],
            ],
            [
                'code' => 'VELOAPPIO_STANDARD',
                'rates' => [],
            ],
        ];

        $sameDayAvailable = false;

        foreach ($rates as $i => $rate) {
            foreach ($resultsInOrder as $j => $rateResults) {
                if (strpos($rate['service_code'], $rateResults['code']) === 0) {
                    if ($rateResults['code'] === 'VELOAPPIO_SAME_DAY') {
                        $sameDayAvailable = true;
                    }
                    $resultsInOrder[$j]['rates'][] = $rate;
                    unset($rates[$i]);
                }
            }
        }

        $results = [];
        foreach ($resultsInOrder as $i => $rateResults) {
            // Log::info('rateResults: ' . $rateResults['code'], $rateResults['rates']);
            if (
                !$hideNextDayWhenSameDayIsAvailable ||
                !$sameDayAvailable ||
                $rateResults['code'] !== 'VELOAPPIO_NEXT_DAY'
            ) {
                $results = array_merge($results, $rateResults['rates']);
            }
        }
        $results = array_merge($results, $rates);

        return response()->json([
            'rates' => $results
        ], 200);
    }

    private function itemsCheck($shopifyShop, $items = [])
    {
        $total = 0;
        $weight = 0;
        $inStock = true;
        $shopifyRepo = new IntegrationRepository();

        if (count($items)) {
            // $queryIds = [];
            foreach ($items as $i => $variant) {
                $total += $variant['quantity'] * $variant['price'];
                $weight += floatVal($variant['grams']);
                if ($inStock && $shopifyShop->store->validate_inventory) {
                    // Shopify inventory check
                    if (!$shopifyRepo->validateVariantStock($shopifyShop, $variant['variant_id'], $variant['quantity'])) {
                        $inStock = false;
                    }
                }
            }
        } else {
            $inStock = true;
        }

        $total = $total / 100; // prices are fractional
        $weight = $weight / 1000; // convert to kg
        $prices = $shopifyShop->store->getRates($total);

        return [
            'weight' => $weight,
            'inStock' => $inStock,
            'prices' => $prices,
        ];
    }

    public function getRate($shopifyShopDomain, Request $request)
    {
        // Log::info('getRate', ['shopifyShopDomain' => $shopifyShopDomain, 'inputs' => $request->all()]);
        $shopifyShop = ShopifyShop::where('domain', $shopifyShopDomain)->first();
        if (!$shopifyShop) {
            Log::notice('getRate ShopifyShop not found', ['shopifyShopDomain' => $shopifyShopDomain]);
            return $this->ratesResponse();
        }

        if (!$shopifyShop->store) {
            Log::notice('getRate ShopifyShop not connected to a store', ['shopifyShopDomain' => $shopifyShopDomain]);
            return $this->ratesResponse();
        }

        if (!$shopifyShop->active) {
            return $this->ratesResponse();
        }

        $rate = $request->input('rate');
        if (!isset($rate['destination'])) {
            Log::notice('getRate rate param missing', ['store' => $shopifyShop->store->slug, 'inputs' => $request->all()]);
            return $this->ratesResponse();
        }

        if (!isset($rate['destination']['address1']) || is_null($rate['destination']['address1'])) {
            return $this->ratesResponse();
        }

        // check inventory, discount and weight
        $itemsCheck = $this->itemsCheck($shopifyShop, $rate['items']);
        if ($shopifyShop->store->validate_inventory && !$itemsCheck['inStock'] || $itemsCheck['prices'] === false) {
            Log::notice('getRate rate failed to meet stock requirements', ['shopifyShopDomain' => $shopifyShopDomain, '$itemsCheck' => $itemsCheck]);
            return $this->ratesResponse();
        }

        $addressesRepo = new AddressesRepository();
        $rate['destination'] = $addressesRepo->get($rate['destination'], false, true);
        if (!$rate['destination'] instanceof Address) {
            return $this->ratesResponse();
        }

        app()->setLocale($addressesRepo->guessLocale($rate['destination']->toArray())->iso);

        $resultsByShippingCode = [];
        $shippingCodesCheckRepo = new ShippingCodesCheckRepository();
        $checkedCities = [];
        $checkedAddresses = [];
        for ($i = 0; $i <= 1; $i++) {
            foreach ($rate['destination']->organizeByDistance($shopifyShop->store->pickup_addresses) as $address) {
                if (strtolower($address->country) !== strtolower($rate['destination']['country'])) {
                    continue;
                }

                if (!isset($checkedAddresses[$address->id]) && (!isset($checkedCities[$address->city]) || $i > 0)) {
                    $checkedCities[$address->city] = true;
                    $checkedAddresses[$address->id] = true;

                    if ($shopifyShop->store->validate_inventory) {
                        // $address inventory validation
                        // on success - $shippingCodesCheckRepo->available
                        // on failure - continue
                    }

                    $availableShippingCodes = $shippingCodesCheckRepo->available([
                        'store' => $shopifyShop->store,
                        'weight' => $itemsCheck['weight'],
                        'customerAddress' => $rate['destination'],
                        'storeAddress' => $address,
                    ], true, false);

                    // Log::info($address->street.' '.$address->number.' '.$address->city, $availableShippingCodes);

                    foreach ($availableShippingCodes as $code => $repoResults) {
                        if (!isset($resultsByShippingCode[$code])) {
                            $resultsByShippingCode[$code] = [];
                            foreach ($repoResults as $i => $repoResult) {
                                $resultsByShippingCode[$code][] = $repoResult;
                            }
                        }
                    }

                    if (count($resultsByShippingCode)) {
                        break;
                    }
                }
            }
        }

        // no service out of the polygons
        if (!count($resultsByShippingCode)) {
            Log::notice('getRate out of polygons', $rate);
            return $this->ratesResponse();
        }
        $results = [];
        $sameDayResults = [];
        foreach ($resultsByShippingCode as $shippingCode => $shippingCodesResults) {
            foreach ($shippingCodesResults as $shippingCodesResult) {
                if (
                    isset($itemsCheck['prices'][$shippingCodesResult['polygon']->shipping_code->code]) &&
                    $itemsCheck['prices'][$shippingCodesResult['polygon']->shipping_code->code]['active']
                ) {
                    if ($shippingCodesResult['polygon']->external_pricing) {
                        $itemsCheck['prices'][$shippingCodesResult['polygon']->shipping_code->code]['price'] += $shippingCodesResult['estimate'];
                    }
                    if ($shippingCodesResult['polygon']->shipping_code->is_same_day) {
                        $results[] = $this->sameDayRate($shopifyShop, $shippingCodesResult, $itemsCheck['prices'][$shippingCodesResult['polygon']->shipping_code->code]['price']);
                        $sameDayResults[] = $this->sameDayRate($shopifyShop, $shippingCodesResult, $itemsCheck['prices'][$shippingCodesResult['polygon']->shipping_code->code]['price']);
                    } else {
                        $results[] = $this->otherDayRate($shopifyShop, $shippingCodesResult, $itemsCheck['prices'][$shippingCodesResult['polygon']->shipping_code->code]['price']);
                    }
                }
            }
        }

        return $this->ratesResponse($results, !$shopifyShop->store->always_show_next_day_options);
    }
}
