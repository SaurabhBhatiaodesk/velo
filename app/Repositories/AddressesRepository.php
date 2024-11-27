<?php

namespace App\Repositories;

use App\Models\AddressTranslation;
use App\Repositories\BaseRepository;
use Spatie\Geocoder\Facades\Geocoder;
use App\Models\Address;
use App\Models\Locale;
use Log;

class AddressesRepository extends BaseRepository
{
    private $rootLocaleIso = 'en_US';
    /**
     * Guess address data's locale by regex
     *
     * @param array $addressData
     * @return \App\Models\Locale $bestMatchedLocale
     */
    public function guessLocale($addressData)
    {
        $bestMatchedLocale = null;
        $bestMatchCount = 0;
        $dataAsAddress = new Address($addressData);
        foreach (Locale::all() as $locale) {
            if (
                !is_null($locale->country) &&
                $locale->checkColumn($dataAsAddress, 'country') &&
                (
                    is_null($locale->state) ||
                    $locale->checkColumn($dataAsAddress, 'state')
                )
            ) {
                return $locale;
            }

            $matchCount = 0;

            if (isset($addressData['city'])) {
                preg_match_all($locale->regex_identifier, $addressData['city'], $matches);
                $matchCount += count($matches[0]);
            }

            if (isset($addressData['state'])) {
                preg_match_all($locale->regex_identifier, $addressData['state'], $matches);
                $matchCount += count($matches[0]);
            }

            if (isset($addressData['country'])) {
                preg_match_all($locale->regex_identifier, $addressData['country'], $matches);
                $matchCount += count($matches[0]);
            }

            if ($matchCount > $bestMatchCount) {
                $bestMatchedLocale = $locale;
                $bestMatchCount = $matchCount;
            }
        }

        return $bestMatchedLocale;
    }

    /**
     * merge translation data into a root address
     * return a new Address with the $rootAddress's id
     *
     * @param \App\Models\Address $rootAddress
     * @param \App\Models\AddressTranslation $translation
     *
     * @return \App\Models\Address $address
     */
    private function makeMergedAddress($rootAddress, $translation)
    {
        $this->changeCountryIsoToCountry($rootAddress);
        $address = new Address($this->mergeTranslationData($rootAddress, $translation));
        $address->id = $rootAddress->id;
        return $address;
    }

    /**
     * merge translation data into a root address
     *
     * @param \App\Models\Address $rootAddress
     * @param \App\Models\AddressTranslation $translation
     *
     * @return array $address
     */
    private function mergeTranslationData($address, $translation)
    {
        $address = $address->toArray();
        foreach (['street', 'city', 'state', 'country'] as $attribute) {
            // if the translation has a value for the attribute
            if (
                !is_null($translation->{$attribute}) &&
                (
                    (
                        is_string($translation->{$attribute}) &&
                        strlen($translation->{$attribute})
                    ) ||
                    $translation->{$attribute}
                )
            ) {
                $address[$attribute] = $translation->{$attribute};
            }
        }
        // return the merged address
        return $address;
    }

    /**
     * Unify address data into a single format
     *
     * @param \App\Models\Address | array $addressData;
     *
     * @return array $addressData;
     */
    public function prepareData($addressData)
    {
        // if a root address was passed in
        if ($addressData instanceof Address) {
            $addressData = $addressData->toArray();
            $addressData['locale_iso'] = $this->rootLocaleIso;
            $addressData['from_db'] = true;
        } else if ($addressData instanceof AddressTranslation) {
            $localeIso = $addressData->getLocaleIso();
            $rootAddress = $addressData->address;
            if ($rootAddress) {
                $addressData = $this->mergeTranslationData($rootAddress, $addressData);
            }
            $addressData['locale_iso'] = $localeIso;
            $addressData['from_db'] = true;
        }

        if (is_object($addressData)) {
            if (method_exists($addressData, 'toArray')) {
                $addressData = $addressData->toArray();
            } else {
                $addressData = json_decode(json_encode($addressData), true);
            }
        }

        $columns = [
            'zipcode' => ['zip', 'postal_code'],
            'line1' => ['address1'],
            'line2' => ['address2'],
            'latitude' => ['lat'],
            'longitude' => ['lng'],
            'full_name' => ['name'],
            'last_name' => ['surname'],
        ];

        foreach ($columns as $column => $falseColumns) {
            if (isset($addressData[$column]) && !is_null($addressData[$column])) {
                continue;
            }
            foreach ($falseColumns as $falseColumn) {
                if (
                    isset($addressData[$falseColumn]) &&
                    !is_null($addressData[$falseColumn]) &&
                    (
                        is_numeric($addressData[$falseColumn]) ||
                        (
                            is_string($addressData[$falseColumn]) &&
                            strlen($addressData[$falseColumn])
                        )
                    )
                ) {
                    $addressData[$column] = $addressData[$falseColumn];
                    unset($addressData[$falseColumn]);
                    break;
                }
            }
        }

        // get street and house number
        if (
            isset($addressData['line1']) &&
            (
                !isset($addressData['street']) ||
                !$addressData['street'] ||
                !isset($addressData['number']) ||
                !$addressData['number']
            )
        ) {
            preg_match_all('(\d[\d.]*)', $addressData['line1'], $matches);
            if (isset($matches[0][0])) {
                $addressData['street'] = trim(str_replace($matches[0][0], '', $addressData['line1']));
                $addressData['number'] = intVal($matches[0][0]);
            } else {
                $addressData['street'] = $addressData['line1'];
                $addressData['number'] = 0;
            }
            unset($addressData['line1']);
        }

        $addressData['phone'] = preg_replace('/[^0-9]/', '', $addressData['phone']);

        if (
            isset($addressData['full_name']) &&
            (
                !isset($addressData['first_name']) ||
                !$addressData['first_name'] ||
                !isset($addressData['last_name']) ||
                !$addressData['last_name']
            )
        ) {
            $name = explode(' ', $addressData['full_name']);
            $addressData['first_name'] = $name[0];
            if (isset($name[1])) {
                unset($name[0]);
                $name = implode(' ', $name);
                $addressData['last_name'] = $name;
            } else {
                $addressData['last_name'] = $addressData['first_name'];
            }
        }

        if (
            isset($addressData['addressable_type']) &&
            !is_null($addressData['addressable_type']) &&
            !str_contains($addressData['addressable_type'], 'App\\Models\\')
        ) {
            $addressData['addressable_type'] = 'App\\Models\\' . ucfirst($addressData['addressable_type']);
        }

        if (!isset($addressData['user_id']) || !$addressData['user_id']) {
            if (auth()->check()) {
                $addressData['user_id'] = auth()->id();
            }
        }

        if (!isset($addressData['locale_iso'])) {
            $addressData['locale_iso'] = $this->guessLocale($addressData)->iso;
        }

        return $addressData;
    }

    /**
     * Geocode an address and get its info in a specific locale
     *
     * @param  array $addressData
     * @param \App\Models\Locale or String $locale
     *
     * @return array Fail
     */
    private function geocode($addressData, $locale, $isRetry = false)
    {
        $formattedAddress = '';
        if (!$isRetry) {
            if (
                (
                    isset($addressData['street']) &&
                    !is_null($addressData['street'])
                ) ||
                (
                    isset($addressData['number']) &&
                    !is_null($addressData['number'])
                )
            ) {
                $formattedAddress = $addressData['street'] . ' ' . $addressData['number'] . ' ';
            } else if (isset($addressData['line1']) && !is_null($addressData['line1'])) {
                $formattedAddress = $addressData['line1'] . ' ';
            }
        }

        $formattedAddress .= $addressData['city'];

        if (isset($addressData['state']) && !is_null($addressData['state'])) {
            $formattedAddress .= ", {$addressData['state']}";
        }

        if (!$isRetry && isset($addressData['country']) && !is_null($addressData['country'])) {
            $formattedAddress .= ", {$addressData['country']}";
        }

        $geocoder = Geocoder::setLanguage($locale['ietf']);
        $gmapsResult = $geocoder->getCoordinatesForAddress($formattedAddress);
        if (
            !isset($gmapsResult['lat']) ||
            !isset($gmapsResult['lng'])
        ) {
            return $this->fail('address.geocodeFail', 422, [
                'addressData' => $addressData,
                'formattedAddress' => $formattedAddress,
                'gmapsResult' => $gmapsResult
            ]);
        }

        // $gmapsResult['accuracy']:
        // ROOFTOP - the most accurate
        // RANGE_INTERPOLATED - the second most accurate
        // GEOMETRIC_CENTER - the third most accurate
        // APPROXIMATE - the least accurate

        $gmapsData = [
            'street' => '',
            'number' => '',
            'city' => '',
            'state' => '',
            'country' => '',
            'zipcode' => '',
            'latitude' => $gmapsResult['lat'],
            'longitude' => $gmapsResult['lng'],
            'user_id' => isset($addressData['user_id']) ? $addressData['user_id'] : auth()->id(),
        ];

        // Get each component of the address from the place details,
        // and then fill-in the corresponding field on the form.
        // place.address_components are google.maps.GeocoderAddressComponent objects
        // which are documented at http://goo.gle/3l5i5Mr
        $shouldBeComponent = [
            'street' => ['street_address', 'route'],
            'number' => ['street_number'],
            'zipcode' => ['postal_code'],
            'state' => [
                'administrative_area_level_1',
                // 'administrative_area_level_2',
                // 'administrative_area_level_3',
                // 'administrative_area_level_4',
                // 'administrative_area_level_5'
            ],
            'city' => [
                'locality',
                'sublocality',
                'sublocality_level_1',
                'sublocality_level_2',
                'sublocality_level_3',
                'sublocality_level_4',
                'postal_town',
            ],
            'country' => ['country']
        ];

        if (!isset($gmapsResult['address_components'])) {
            // Try again for malformed addresses will return no address components
            if ($addressData['city'] === $addressData['street'] && !$isRetry) {
                $addressData['street'] = '';
                return $this->geocode($addressData, $locale, true);
            }
            return $this->fail('address.geocodeFail', [
                'addressData' => $addressData,
                'formattedAddress' => $formattedAddress,
                'gmapsResult' => $gmapsResult,
                'message' => 'Geocoder returned no address components'
            ]);
        }
        foreach ($gmapsResult['address_components'] as $component) {
            foreach ($shouldBeComponent as $column => $shouldBe) {
                if (property_exists($component, 'types') && count($component->types) && in_array($component->types[0], $shouldBe)) {
                    if (!isset($gmapsData[$column]) || !strlen($gmapsData[$column])) {
                        $gmapsData[$column] = '';
                    } else {
                        $gmapsData[$column] .= ' ';
                    }
                    $gmapsData[$column] .= ($column === 'state') ? $component->short_name : $component->long_name;
                }
            }
        }

        foreach ($gmapsData as $property => $value) {
            if (strlen($value)) {
                $addressData[$property] = $value;
            }
        }

        return $addressData;
    }

    /**
     * Looks for matching root addresses in the database
     *
     * @param  array $addressData
     *
     * @return array [accuracy: string, address: \App\Models\Address]
     */
    private function findExistingAddress($addressData)
    {
        if (!isset($addressData['street'])) {
            Log::error('AddressesRepository@findExistingAddress Address data is missing street', $addressData);
            return [
                'accuracy' => 'none',
                'address' => false,
            ];
        }
        // find addresses with the same location info
        $addressQuery = Address::where('country', $addressData['country']);
        $addressQuery->where('city', $addressData['city']);
        $addressQuery->where('street', $addressData['street']);
        $addressQuery->where('number', $addressData['number']);

        if (isset($addressData['state']) && strlen($addressData['state'])) {
            $addressQuery->where('state', $addressData['state']);
        }

        if (!$addressQuery->count()) {
            foreach (Locale::where('iso', '!=', 'en_US')->get() as $locale) {
                // prepare an AddressTranslation object
                $addressQuery = new AddressTranslation();
                $addressQuery->setTable('addresses_' . $locale->iso);
                $addressQuery = $addressQuery
                    ->where('country', $addressData['country'])
                    ->where('city', $addressData['city'])
                    ->where('street', $addressData['street'])
                    ->whereHas('address', function ($query) use ($addressData) {
                        $query->where('number', $addressData['number']);
                    });

                if ($addressQuery->count()) {
                    $addressQuery = Address::whereIn('id', $addressQuery->pluck('address_id'));
                }
            }
        }

        // if some addresses were matched, try a higher accuracy
        if ($addressQuery->count()) {
            // if we have relationship data, try to find an identical address
            if (isset($addressData['addressable_type']) && strlen($addressData['addressable_type'])) {
                $identicalAddress = $addressQuery->clone();
                $identicalAddress->where('addressable_type', $addressData['addressable_type']);
                if (isset($addressData['addressable_id'])) {
                    $identicalAddress->where('addressable_id', $addressData['addressable_id']);
                }
                if (isset($addressData['addressable_slug']) && strlen($addressData['addressable_slug'])) {
                    $identicalAddress->where('addressable_slug', $addressData['addressable_slug']);
                }
                $identicalAddress->where('phone', $addressData['phone']);
                $identicalAddress->where('first_name', $addressData['first_name']);
                $identicalAddress->where('last_name', $addressData['last_name']);

                // if an identical address was found, return it
                if ($identicalAddress->count()) {
                    return [
                        'accuracy' => 'identical',
                        'address' => $identicalAddress->first()
                    ];
                }

                // if no identical address was found, return the address with the same location
                return [
                    'accuracy' => 'location',
                    'address' => $addressQuery->first()
                ];
            }
        }

        // if no addresses were found, return none
        return [
            'accuracy' => 'none',
            'address' => false,
        ];

    }

    /**
     * Looks for matching translations in the database
     *
     * @param  \App\Models\Address $rootAddress
     * @param  \App\Models\Locale $locale
     *
     * @return array
     */
    private function findExistingAddressTranslation($rootAddress, $locale)
    {
        // find identical addresses
        $addresses = Address::where('country', $rootAddress->country)
            ->where('city', $rootAddress->city)
            ->where('street', $rootAddress->street)
            ->where('state', $rootAddress->state);

        // if some addresses were matched
        if ($addresses->count()) {
            // iterate them
            foreach ($addresses->get() as $address) {
                // try to find matching translations
                $translations = $address->locale_translations($locale->iso);
                if ($translations->count()) {
                    // return the first one found
                    return $translations->first()->toArray();
                }
            }
        }

        return [];
    }

    /**
     * Get the target locale
     *
     * @param \App\Models\Locale | string $locale
     * @param \App\Models\Locale $fallback
     *
     * @return \App\Models\Locale
     */
    private function getTargetLocale($locale, $fallback)
    {
        if ($locale instanceof Locale) {
            return $locale;
        }

        // if $locale is a Locale id
        if (is_numeric($locale)) {
            $locale = Locale::find($locale);
        }

        // if $locale is a string
        else if (is_string($locale)) {
            // try to find by ISO first
            $locale = Locale::where('iso', $locale)->first();
            if (!$locale) {
                // if no ISO was found try finding by IETF
                $locale = Locale::where('ietf', $locale)->first();
            }
        }

        // if no locale was found, use the fallback
        if (!$locale) {
            return $fallback;
        }

        // return the locale
        return $locale;
    }

    /**
     * Validate the locale of an address
     *
     * @param \App\Models\Address $address
     *
     * @return boolean
     */
    private function validateLocale($address)
    {
        if (!is_null($address->locale->regex_identifier)) {
            foreach (['city', 'state', 'country'] as $column) {
                if (
                    isset($address->{$column}) &&
                    !is_null($address->{$column}) &&
                    !strlen($address->{$column}) &&
                    !preg_match($address->locale->regex_identifier, $address->{$column})
                ) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Change a country ISO to a country name
     *
     * @param \App\Models\Address | string $address
     * @param boolean $skipSave
     *
     * @return string $country
     */
    private function changeCountryIsoToCountry($address, $skipSave = false)
    {
        $country = is_string($address) ? strtolower($address) : strtolower($address->country);
        if (config()->has('countries.countryFromIso.' . $country)) {
            $country = config()->get('countries.countryFromIso.' . $country);
            if (!$skipSave && !is_string($address)) {
                $address->update(['country' => $country]);
            }
        }
        return $country;
    }

    /**
     * Get an address in a specific Locale
     * $skipGeocode = true - don't create if it doesn't exist
     *
     * @param \App\Models\Address | array $addressData
     * @param \App\Models\Locale | string $locale
     * @param boolean $skipSave
     * @param boolean $skipGeocode
     *
     * @return \App\Models\Address | array
     */
    public function get($addressData, $locale = false, $skipSave = false, $skipGeocode = false)
    {
        // validate data exists
        if (is_null($addressData)) {
            return $this->fail('address.noData');
        }

        /********************************
         * Get all the locales information
         * ******************************/
        $english = Locale::where('iso', $this->rootLocaleIso)->first();
        $locale = $this->getTargetLocale($locale, $english);

        $rootAddress = false;
        $translation = false;

        /********************************
         * Delivery address array was passed in
         * ******************************/
        if (
            is_array($addressData) &&
            !empty($addressData['addressable_type']) &&
            !empty($addressData['id'])
        ) {
            $dbAddress = Address::find($addressData['id']);
            if (
                $dbAddress &&
                $dbAddress->city === $addressData['city'] &&
                $dbAddress->street === $addressData['street'] &&
                $dbAddress->number === $addressData['number']
            ) {
                $addressData = $dbAddress;
            }
        }

        /********************************
         * Root address was passed in
         * ******************************/
        if ($addressData instanceof Address) {
            $rootAddress = $addressData;
            if ($locale->iso === $this->rootLocaleIso) {
                $this->changeCountryIsoToCountry($rootAddress);
                // it's already in the correct locale
                return $rootAddress;
            }
        }

        /**********************************
         * AddressTranslation was passed in
         * ********************************/ else if ($addressData instanceof AddressTranslation) {
            // try to get its root address
            $rootAddress = $addressData->address;
            // if the translation is in the correct locale
            if ($addressData->getLocaleIso() === $locale->iso) {
                $translation = $addressData;
            }
        }

        /**************************************
         * check $rootAddress and $translation
         * ***********************************/
        // if a root address was found
        if ($rootAddress) {
            // but no translation was found for the correct locale
            if (!$translation) {
                // try to get an existing translation
                $translation = $addressData->locale_translations($locale->iso)->first();
            }

            // if we found a translation for the correct locale
            if ($translation) {
                // return the merged address
                return $this->makeMergedAddress($rootAddress, $translation);
            }
        }
        // if no root address was found
        else {
            // unify the address data to an array
            $addressData = $this->prepareData($addressData);
            // try to find matching addresses in the database
            $existingAddressResult = $this->findExistingAddress($addressData);
            // check the accuracy of the existing address
            switch ($existingAddressResult['accuracy']) {
                // no matching address was found
                case 'none':
                    $rootAddress = false;
                    break;
                // an identical address was found
                case 'identical':
                    $rootAddress = $existingAddressResult['address'];
                    break;
                // a location match was found
                default:
                    $rootAddress['latitude'] = $existingAddressResult['address']->latitude;
                    $rootAddress['longitude'] = $existingAddressResult['address']->longitude;
            }
        }
        // if geocoding is needed
        if (
            !$rootAddress instanceof Address ||
            !isset($rootAddress['longitude']) ||
            is_null($rootAddress['longitude']) ||
            !strlen($rootAddress['longitude']) ||
            !isset($rootAddress['latitude']) ||
            is_null($rootAddress['latitude']) ||
            !strlen($rootAddress['latitude'])
        ) {
            // when looking for ONLY existing addresses
            if ($skipGeocode) {
                return $this->fail('address.notFound', 404, [
                    'addressData' => $addressData,
                    'rootAddress' => $rootAddress
                ]);
            }
            // geocode root address
            $rootAddress = $this->geocode($addressData, $english);
            // geocoding failed
            if (isset($rootAddress['fail'])) {
                return $rootAddress;
            }
            // create an address object
            $rootAddress = new Address($rootAddress);
        }

        $rootAddress->country = $this->changeCountryIsoToCountry($rootAddress, true);

        // save the root address if needed
        if (!$skipSave) {
            $rootAddress->save();
        }

        /**************************************
         * At this point we have a root address
         **************************************/
        // return the root address if it's in the correct locale
        if ($locale->iso === $this->rootLocaleIso) {
            return $rootAddress;
        }

        // try to get the translation
        $translation = $rootAddress->locale_translations($locale->iso)->first();
        // a translation was found
        if ($translation) {
            // return the merged address
            return $this->makeMergedAddress($rootAddress, $translation);
        }

        /**************************************************************
         * We have a root address, but still need an AddressTranslation
         **************************************************************/

        // unify the address data to an array
        $addressData = $this->prepareData($addressData);
        // find matching addresses in the database
        $existingAddressTranslationResult = $this->findExistingAddressTranslation($rootAddress, $locale);
        // prepare an AddressTranslation object
        $translation = new AddressTranslation();
        $translation->setTable('addresses_' . $locale->iso);
        // if we found a full translation for the correct locale
        if (
            isset($existingAddressTranslationResult['street']) &&
            !is_null($existingAddressTranslationResult['street']) &&
            strlen($existingAddressTranslationResult['street']) &&
            isset($existingAddressTranslationResult['city']) &&
            !is_null($existingAddressTranslationResult['city']) &&
            strlen($existingAddressTranslationResult['city']) &&
            isset($existingAddressTranslationResult['state']) &&
            !is_null($existingAddressTranslationResult['state']) &&
            strlen($existingAddressTranslationResult['state']) &&
            isset($existingAddressTranslationResult['country']) &&
            !is_null($existingAddressTranslationResult['country']) &&
            strlen($existingAddressTranslationResult['country'])
        ) {
            $translation->fill($existingAddressTranslationResult);
            $translation->address_id = $rootAddress->id;
            // save the translation if needed
            if (!$skipSave) {
                $translation->save();
            }
            // return the merged address
            return $this->makeMergedAddress($rootAddress, $translation);
        }

        /***************************************
         * Not enough data found in the database
         ***************************************/
        $addressData = $this->geocode($addressData, $locale);
        if (isset($addressData['fail'])) {
            return $addressData;
        }

        // create address object
        $translation->fill($addressData);
        $translation->address_id = $rootAddress->id;

        // save the newly geocoded translation
        if (!$skipSave) {
            $translation->save();
        }

        // return the address
        return $this->makeMergedAddress($rootAddress, $translation);
    }

    /**
     * Prepare address data for saving
     *
     * @param \App\Models\Address | array $addressData
     *
     * @return array
     */
    public function prepareDataForSave($addressData)
    {
        // unify the address data to an array
        if (is_object($addressData)) {
            if (method_exists($addressData, 'toArray')) {
                $addressData = $addressData->toArray();
            } else {
                $addressData = json_decode(json_encode($addressData), true);
            }
        }

        // organize polymorphic relationship pointers
        if (isset($addressData['addressable_type']) && !str_starts_with($addressData['addressable_type'], 'App\\Models\\')) {
            $addressData['addressable_type'] = 'App\\Models\\' . $addressData['addressable_type'];
        }

        if (isset($addressData['addressable_slug']) && !strlen($addressData['addressable_slug'])) {
            unset($addressData['addressable_slug']);
        }

        if (isset($addressData['addressable_id']) && !strlen($addressData['addressable_id'])) {
            unset($addressData['addressable_id']);
        }

        return $addressData;
    }

    /**
     * Iterate a Address Collection and use the get method to get each address in a specific locale
     *
     * @param \Illuminate\Support\Collection<Address> | array $addresses
     * @param \App\Models\Locale | string $locale
     * @param boolean $skipSave
     *
     * @return \Illuminate\Support\Collection<Address> | array Fail
     */
    public function getMany($addresses, $locale = false, $skipSave = false)
    {
        // get the locale information so the get function won't do it repeatedly
        $english = Locale::where('iso', $this->rootLocaleIso)->first();
        $locale = $this->getTargetLocale($locale, $english);

        // iterate the addresses and get each one in the specified locale
        $addresses = $addresses->map(function ($address) use ($locale, $skipSave) {
            $address = $this->get($address, $locale, $skipSave);
            return $address;
        });

        return $addresses;
    }
}
