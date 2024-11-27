<?php

namespace App\Repositories\Integrations\Shopify\Traits;

use Illuminate\Support\Facades\Log;
use App\Models\Customer;

trait CustomersTrait
{
    public function saveCustomer($params)
    {
        if (is_null($params['phone']) && isset($params['default_address']) && isset($params['default_address']['phone'])) {
            $params['phone'] = $params['default_address']['phone'];
        }
        $data = [
            'first_name' => $params['first_name'],
            'last_name' => $params['last_name'],
            'email' => $params['email'],
            'phone' => preg_replace('/[^0-9]/', '', $params['phone']),
            'store_slug' => $params['store_slug'],
            'shopify_id' => $params['id'],
        ];

        $customer = Customer::where('store_slug', $data['store_slug'])
            ->where('shopify_id', $data['shopify_id'])
            ->first();

        if (!$customer) {
            if (
                !isset($data['first_name']) || is_null($data['first_name']) ||
                !isset($data['last_name']) || is_null($data['last_name']) ||
                !isset($data['phone']) || is_null($data['phone'])
            ) {
                Log::debug('saveCustomer validation fail', [
                    'data' => $data,
                    'params' => $params,
                ]);
                return false;
            }
            $customer = Customer::create($data);
        } else {
            $customer->update($data);
        }

        if (isset($params['note']) && strlen($params['note'])) {
            $customer->notes()->create([
                'note' => $params['note'],
                'user_id' => $customer->store->user_id
            ]);
        }

        if (isset($params['addresses'])) {
            foreach ($params['addresses'] as $i => $addressData) {

                if (isset($addressData['phone'])) {
                    $addressData['phone'] = preg_replace('/[^0-9]/', '', $addressData['phone']);
                } else {
                    if ($addressData['last_name'] === $customer->last_name) {
                        $addressData['phone'] = $customer->phone;
                    } else {
                        continue;
                    }
                }

                if ($addressData['phone'] === $customer->phone) {
                    if (isset($addressData['id'])) {
                        $addressData['shopify_id'] = $addressData['id'];
                        unset($addressData['id']);
                    }
                    if (!isset($addressData['store_slug'])) {
                        $addressData['store_slug'] = $customer->store_slug;
                    }

                    $this->addressesRepo->get(array_merge($addressData, [
                        'addressable_type' => 'App\\Models\\Customer',
                        'addressable_slug' => $customer->id,
                        'user_id' => $customer->store->user_id,
                    ]));
                }
            }
        }

        return $customer;
    }
}
