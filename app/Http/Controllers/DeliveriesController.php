<?php

namespace App\Http\Controllers;

use App\Http\Requests\Models\Deliveries\CheckAvailableRequest;
use App\Repositories\ShippingCodesCheckRepository;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class DeliveriesController extends Controller
{
    public function formatAvailableShippingCode($shippingCodeData)
    {
        $result = [
            'polygon' => [
                'id' => $shippingCodeData['polygon']->id,
                'shipping_code' => [
                    'external_service_id' => isset($shippingCodeData['external_service_id']) ? $shippingCodeData['external_service_id'] : null,
                    'code' => $shippingCodeData['polygon']->shipping_code->code,
                    'initial_free_km' => $shippingCodeData['polygon']->shipping_code->initial_free_km,
                ],
            ],
            'pickup_max_days' => $shippingCodeData['polygon']->pickup_max_days,
            'dropoff_max_days' => $shippingCodeData['polygon']->dropoff_max_days,
            'courier' => (isset($shippingCodeData['external_courier_name']) && !is_null($shippingCodeData['external_courier_name'] && strlen($shippingCodeData['external_courier_name']))) ? $shippingCodeData['external_courier_name'] : $shippingCodeData['courier'],
            'is_international' => !!(isset($shippingCodeData['is_international']) && $shippingCodeData['is_international']),
            'is_collection' => !!(isset($shippingCodeData['is_collection']) && $shippingCodeData['is_collection']),
            'prices' => []
        ];

        if (isset($shippingCodeData['delivery_days']) && isset($shippingCodeData['delivery_days']['min']) && isset($shippingCodeData['delivery_days']['max'])) {
            $result['delivery_days'] = $shippingCodeData['delivery_days'];
        }

        if (!is_null($shippingCodeData['polygon']->title) && strlen($shippingCodeData['polygon']->title)) {
            $result['polygon']['title'] = $shippingCodeData['polygon']->title;
        }
        if (!is_null($shippingCodeData['polygon']->description) && strlen($shippingCodeData['polygon']->description)) {
            $result['polygon']['description'] = $shippingCodeData['polygon']->description;
        }

        if (isset($shippingCodeData['external_service_id']) && !is_null($shippingCodeData['external_service_id']) && strlen($shippingCodeData['external_service_id'])) {
            $result['external_service_id'] = $shippingCodeData['external_service_id'];
        }
        if (isset($shippingCodeData['external_service_name']) && !is_null($shippingCodeData['external_service_name']) && strlen($shippingCodeData['external_service_name'])) {
            $result['external_service_name'] = $shippingCodeData['external_service_name'];
        }
        if (isset($shippingCodeData['external_courier_name']) && !is_null($shippingCodeData['external_courier_name']) && strlen($shippingCodeData['external_courier_name'])) {
            $result['external_courier_name'] = $shippingCodeData['external_courier_name'];
        }

        foreach ($shippingCodeData['prices'] as $price) {
            if (!isset($price['currency_id']) && isset($price['currency'])) {
                $price['currency_id'] = $price['currency']->id;
            }
            $result['prices'][] = [
                'price' => $price['price'],
                'slug' => $price['slug'],
                'currency_id' => $price['currency_id'],
                'shield' => (isset($price['shield'])) ? $price['shield'] : 0,
            ];
        }

        if (isset($shippingCodeData['estimate'])) {
            $result['estimate'] = $shippingCodeData['estimate'];
            if (isset($shippingCodeData['estimate']['tax']) && $shippingCodeData['estimate']['tax'] > 0) {
                $result['tax'] = $shippingCodeData['estimate']['currency']->symbol . $shippingCodeData['estimate']['tax'];
            }
        }

        if (isset($shippingCodeData['shield'])) {
            $result['shield'] = $shippingCodeData['shield'];
        }
        return $result;
    }

    public function check(CheckAvailableRequest $request)
    {
        $shippingCodesCheckRepository = new ShippingCodesCheckRepository();
        $availableShippingCodes = $shippingCodesCheckRepository->available($request->all());
        foreach ($availableShippingCodes as $i => $shippingCodeData) {
            if (isset($shippingCodeData[0]) && isset($shippingCodeData[0]['is_collection']) && $shippingCodeData[0]['is_collection']) {
                foreach ($shippingCodeData as $j => $collectionShippingCodeData) {
                    $availableShippingCodes[$i][$j] = $this->formatAvailableShippingCode($collectionShippingCodeData);
                }
            }
        }

        return $this->respond($availableShippingCodes);
    }

    public function showDeliveries($slug)
    {
        // the slug is an encryption of {order->shipping_address->phone}/{order->name}
        // e.g. Crypt::encryptString('0545445412/Vvelo55');
        try {
            $slug = Crypt::decryptString($slug);
        } catch (DecryptException $e) {
            return 'invalid link';
        }
        return $slug;
    }
}
