<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Polygon;
use App\Models\Currency;
use Illuminate\Support\Facades\Cache;
use Log;

class RatesService
{
    /*
     * Estimate shipping rates for an order
     *
     * @param Order $order
     * @param array $polygonIds
     * @param array $addresses - translated addresses for all relevant courier locales
     * @return array
     */
    public static function estimate($order, $polygonIds, $translatedAddresses)
    {
        // use cache to prevent duplicate requests
        $cacheSlug = 'velo.rates_service.' . $order->name . '_' . $order->pickup_address->slugified . '_' . $order->shipping_address->slugified;

        if (Cache::has($cacheSlug) || !count($polygonIds)) {
            return [];
        }
        Cache::put($cacheSlug, true, now()->addSeconds(5));

        try {
            $response = json_decode(Http::post(config('services.rates.url'), [
                'order_name' => $order->name,
                'polygon_ids' => $polygonIds,
                'size' => array_values($order->delivery->dimensions),
                'weight' => $order->delivery->weight,
                'store_address' => $order->pickup_address,
                'destination' => $order->shipping_address,
                'imperial_units' => $order->store->imperial_units,
                'currency' => $order->store->currency->iso,
                'translatedAddresses' => $translatedAddresses,
            ])->body(), true);
        } catch (\Exception $e) {
            Log::info('rates service connection exception: ' . $e->getMessage());
            $response = [];
        }

        $rates = [];
        $currencies = Currency::all();
        if (!empty($response) && empty($response['message'])) {
            foreach ($response as $rate) {
                $rate['polygon'] = Polygon::find($rate['polygon']['id']);
                if (empty($rates[$rate['polygon']->shipping_code->code])) {
                    $rates[$rate['polygon']->shipping_code->code] = [];
                }

                $currency = false;
                if (!$rate['polygon']->external_pricing) {
                    $rate['prices'] = array_values($rate['polygon']->getPrices($order->store)->toArray());
                    if (!count($rate['prices'])) {
                        continue;
                    }
                    $currency = $currencies->where('id', $rate['prices'][0]['currency_id'])->first();
                } else {
                    $currency = $currencies->where('iso', $rate['prices'][0]['currency'])->first();
                }

                if (empty($rate['prices'][0]['shield'])) {
                    $shield = $rate['polygon']->getShield($order->store);
                    if ($shield) {
                        $rate['prices'][0]['shield'] = $shield->toArray();
                    }
                }

                if (!empty($rate['estimate'])) {
                    $rate['estimate'] += $order->delivery->calculateProfitMargin($rate['estimate'], $rate['polygon']);
                }

                foreach ($rate['prices'] as $i => $price) {
                    $rate['prices'][$i]['currency'] = $currency;
                    $rate['prices'][$i]['currency_id'] = $currency->id;
                    if (empty($price['slug']) || is_null($price['slug']) || !strlen($price['slug'])) {
                        $rate['prices'][$i]['price'] = $price['price'] + $order->delivery->calculateProfitMargin($price['price'], $rate['polygon']);
                        if (empty($rate['estimate'])) {
                            $rate['estimate'] = $rate['prices'][$i]['price'];
                        }
                    }
                }

                $rates[$rate['polygon']->shipping_code->code][] = $rate;
            }
        }

        return $rates;
    }
}
