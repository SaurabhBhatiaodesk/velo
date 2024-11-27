<?php

namespace App\Repositories\Couriers;

use App\Repositories\BaseRepository;
use App\Repositories\AddressesRepository;
use App\Repositories\OrderStatusRepository;
use App\Models\Address;
use App\Models\Order;

class CourierRepository extends BaseRepository
{
    protected $statuses = [
        // courier status => status,
    ];


    /**
     * generates a numeric barcode
     *
     * @param Address $address
     * @return string
     */
    public function getNumericBarcode($courier, $digits = 6)
    {
        return str_pad(strval($courier->deliveries->count()), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * encodes a json string to base64
     *
     * @param string $data
     * @return string
     */
    public function base64UrlEncode(string $data): string
    {
        $base64Url = strtr(base64_encode($data), '+/', '-_');
        return rtrim($base64Url, '=');
    }

    /**
     * removes country code from address phone numbers
     *
     * @param Address $address
     * @return string
     */
    public function removeCountryCode($address)
    {
        // get the country in lowercase to check countries config
        $country = strtolower($address->country);
        if (config()->has('countries.isoFromCountry.' . $country)) {
            // get the iso code from the country name
            $country = config()->get('countries.isoFromCountry.' . $country);
        }

        // check if the country code is in the config
        if (config()->has('country-codes.' . $country)) {
            $countryCode = preg_replace('/[^0-9]/', '', config()->get('country-codes.' . $country));
            // check if the phone number starts with the country code
            if (str_starts_with($address->phone, $countryCode)) {
                // remove the country code from the phone number
                return substr_replace($address->phone, '0', 0, strlen($countryCode));
            }
        }

        // return the phone number as is
        return $address->phone;
    }

    /**
     * Check if order is a quote
     *
     * @param Order $order
     * @return bool
     */
    public function isQuote($order)
    {
        return str_starts_with($order->name, 'VeloQuote');
    }

    /**
     * translate addresses to courier locale
     *
     * @param Order $order
     * @param bool $saveAddresses
     *
     * return array [Models\Address pickup, Address shipping]
     */
    public function translateAddresses($order, $saveAddresses = true)
    {
        if (!$order->pickup_address) {
            if ($order->delivery->pickup_address && isset($order->delivery->pickup_address['id'])) {
                if (Address::find($order->delivery->pickup_address['id'])) {
                    $order->update(['pickup_address_id' => $order->delivery->pickup_address['id']]);
                } else {
                    $addressesRepo = new AddressesRepository();
                    $address = $addressesRepo->get($order->delivery->pickup_address, $order->delivery->polygon->courier->locale, !$saveAddresses, true);
                    if (!$address instanceof Address) {
                        $address = $order->store->pickup_addresses()->first();
                    }
                    $order->update(['pickup_address_id' => $address->id]);
                    $order->delivery->update(['pickup_address' => $address->toArray()]);
                }
            }
        }

        if (!$order->shipping_address) {
            if ($order->delivery->shipping_address && isset($order->delivery->shipping_address['id'])) {
                if (Address::find($order->delivery->shipping_address['id'])) {
                    $order->update(['shipping_address_id' => $order->delivery->shipping_address['id']]);
                } else {
                    $addressesRepo = new AddressesRepository();
                    $address = $addressesRepo->get($order->delivery->shipping_address, $order->delivery->polygon->courier->locale, !$saveAddresses);
                    if ($address instanceof Address) {
                        $order->update(['shipping_address_id' => $address->id]);
                        $order->delivery->update(['shipping_address' => $address->toArray()]);
                    }
                }

                $order->update(['shipping_address_id' => $order->delivery->shipping_address['id']]);
            }
        }

        $res = [
            'shipping' => $order->shipping_address,
            'pickup' => $order->pickup_address,
        ];

        $courierLocale = $order->delivery->polygon->courier->locale;
        if ($courierLocale) {
            $addressesRepo = new AddressesRepository();
            foreach ($res as $direction => $address) {
                $address = $addressesRepo->get($address, $courierLocale, !$saveAddresses);
                if ($address instanceof Address) {
                    $res[$direction] = $address;
                }
            }
        }

        return $res;
    }

    /**
     * limit barcode length while losing as little info as possible
     *
     * @param string $barcode
     * @param int $maxLength
     *
     * @return string
     */
    protected function limitBarcodeLength($barcode, $maxLength)
    {
        if (strlen($barcode) <= $maxLength) {
            return $barcode;
        }

        $barcodeSegments = $barcode;
        if (strpos($barcodeSegments, '-') !== false) {
            $barcodeSegments = explode('-', $barcode);
            $barcodeSegments = implode('_', $barcodeSegments);
        }

        if (strpos($barcodeSegments, '_') !== false) {
            $barcodeSegments = explode('_', $barcodeSegments);
        }

        // get the order number
        preg_match_all('!\d+!', $barcode, $res);
        $res = implode('_', $res[0]);

        if (is_string($barcodeSegments)) {
            $barcodeSegments = [$barcodeSegments];
        }

        if (count($barcodeSegments) === 1) {
            $barcodeSegments[0] = preg_replace('/[0-9]+/', '', $barcodeSegments[0]);
            if (strlen($res) < $maxLength) {
                $res = substr($barcodeSegments[0], 0, ($maxLength - strlen($res))) . $res;
            }
        } else {
            foreach ($barcodeSegments as $i => $barcodeSegment) {
                if ($i === 0) {
                    continue;
                }

                if (strlen($res . $barcodeSegments[0] . $barcodeSegment) < $maxLength) {
                    $res = $barcodeSegment . $res;
                } else {
                    $res = substr($barcodeSegments[0], -1 * ($maxLength - strlen($res) - 1)) . $res;
                    break;
                }
            }
        }

        if (strlen($res) < $maxLength) {
            $res = substr('V' . $barcode, 0, $maxLength - strlen($res)) . $res;
        }

        if (str_starts_with($barcode, '-')) {
            $res = 'V' . substr($res, 1);
        }

        return $res;
    }

    /**
     * formats an address as a string
     *
     * @param Address $address
     * @param bool $useLine2
     * @param bool $useState
     * @param bool $useCountry
     *
     * @return string
     */
    protected function formatAddress($address, $useLine2 = false, $useState = false, $useCountry = false)
    {
        $res = $address->street . ' ' . $address->number;
        if ($useLine2 && $address->line2 && strlen($address->line2)) {
            $res = $res . ', ' . $address->line2;
        }
        $res = $res . ', ' . $address->city;
        if ($useState && !is_null($address->state) && strlen($address->state)) {
            $res = $res . ', ' . $address->state;
        }
        if ($useCountry && !is_null($address->country) && strlen($address->country)) {
            $res = $res . ', ' . $address->country;
        }
        return $res;
    }

    protected function formatPhoneNumberLocal($phone)
    {
        if (str_starts_with($phone, '972')) {
            $phone = '+' . $phone;
        }

        if (str_starts_with($phone, '+972')) {
            $phone = '0' . substr($phone, strlen('+972'));
        }

        return $phone;
    }

    protected function formatPhoneNumberInternational($phone)
    {
        if (str_starts_with($phone, '972')) {
            $phone = '+' . $phone;
        } else if (str_starts_with($phone, '0')) {
            $phone = '+972' . ltrim($phone, '0');
        }

        if (str_contains($phone, ' ')) {
            $phone = explode(' ', $phone);
            if ($phone[0] === '+972' && str_starts_with($phone[1], 0)) {
                $phone[1] = ltrim($phone[1], '0');
            }
            $phone = implode('', $phone);
        }

        return $phone;
    }

    /**
     * get order notes
     *
     * @param Order $order
     * @param array $translatedAddresses [Address pickup, Address shipping]
     *
     * @return string
     */
    protected function getOrderNotes($order, $translatedAddresses)
    {
        $orderNote = 'איש קשר במוצא: '
            . $translatedAddresses['pickup']->full_name . ' - ' . $this->removeCountryCode($translatedAddresses['pickup'])
            . ' איש קשר ביעד: '
            . $translatedAddresses['shipping']->full_name . ' - ' . $this->removeCountryCode($translatedAddresses['shipping']);

        if ($order->note && strlen($order->note)) {
            $orderNote .= ' ' . $order->note;
        }
        if ($order->delivery->is_replacement) {
            $orderNote .= ' - החלפת מוצר';
        }

        return $orderNote;
    }

    /**
     * get pickup windows for a given time range and window duration
     *
     * @param \Carbon\Carbon $start
     * @param \Carbon\Carbon $end
     * @param int $hours (window duration in hours (e.g. 2 for 2 hours window duration)
     *
     * @return array
     */
    public function getPickupWindows($start, $end, $hours)
    {
        // limit start/end times to between 10:00 and 18:00
        if ($start->isBefore('10:00AM')) {
            $start->setTime(10, 0);
        }
        if ($end->isAfter('18:00')) {
            $end->setTime(18, 0);
        }

        $windows = [];
        while ($start->diffInHours($end) >= $hours) {
            $windowEnd = $start->clone();
            $windowEnd->addHours($hours);
            $windows[] = [
                'start' => $start->clone(),
                'end' => $windowEnd->clone(),
            ];
            $start->addHour();
        }

        return $windows;
    }

    /**
     * check if a delivery is a return delivery
     *
     * @param Order $order
     *
     * @return bool
     */
    public function isReturn($order)
    {
        return ($order->delivery->is_return || $order->delivery->polygon->shipping_code->is_return);
    }

    /**
     * check if a delivery is a replacement delivery
     *
     * @param Order $order
     *
     * @return bool
     */
    public function isReplacement($order)
    {
        return ($order->delivery->is_replacement || $order->delivery->polygon->shipping_code->is_replacement);
    }

    /**
     * Parse update data and save via OrderStatusRepository
     *
     * @param Order $order
     * @param string $courierStatus
     * @param array $updateData
     * @param array $courierResponseAppend
     *
     * @return Order|array [fail => true, error => error message, code => error code]
     */
    public function processUpdateData($order, $courierStatus = false, $updateData = [], $courierResponseAppend = [])
    {
        if ($courierStatus) {
            if (empty($updateData['courier_status'])) {
                $updateData['courier_status'] = $courierStatus;
            }
            if (empty($updateData['status']) && !empty($this->statuses[trim($courierStatus)])) {
                $updateData['status'] = $this->statuses[trim($courierStatus)];
            }
        }
        $repo = new OrderStatusRepository();
        return $repo->saveCourierUpdateData($order, $updateData, $courierResponseAppend);
    }

    /**
     * Handle a new courier response
     *
     * @param \App\Models\Order $order
     * @param array $courierResponse
     * @param bool $webhook
     *
     * @return \App\Models\Order | array fail
     */
    public function handleCourierResponse($order, $courierResponse, $webhook = false)
    {
        \Log::error('handleCourierResponse called on CourierRepository from ' . get_called_class());
        return $order;
    }
}

