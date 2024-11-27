<?php

namespace App\Traits;

use Location\Polygon as LocationPolygon;
use Location\Coordinate;
use App\Repositories\AddressesRepository;
use App\Models\Address;

trait HasGeography
{
    /**
     * Check if an address column matches a local column
     *
     * @param Address $address
     * @param string $column
     * @param string $prefix
     * @return bool
     */
    public function checkColumn($address, $column, $prefix = '')
    {
        if (!$address instanceof Address) {
            return false;
        }
        if (is_null($this->{$prefix . $column})) {
            // no conditions for the column
            return true;
        } else if (is_null($address->{$column})) {
            // the address column must match the local column, but it's null.
            return false;
        }

        // get the local and address column values
        $localValue = strtolower($this->{$prefix . $column});
        $addressValue = strtolower($address->{$column});
        // add negation for excluding match columns
        $exclusionMatch = str_starts_with($localValue, '!');
        if ($exclusionMatch) {
            $addressValue = '!' . $addressValue;
            $exclusionMatch = true;
        }

        // if the address value exists somewhere in the local value
        if (strpos($localValue, $addressValue) !== false) {
            if (
                // if it's the only value
                $localValue === $addressValue ||
                // if it's the first value
                str_starts_with($localValue, $addressValue . ',') ||
                // if it's the last value
                str_ends_with($localValue, ',' . $addressValue) ||
                // if it's the in between other values
                strpos($localValue, ',' . $addressValue . ',') !== false
            ) {
                return !$exclusionMatch;
            }
        }

        // no match was found
        return $exclusionMatch;
    }

    /**
     * Check if the polygon contains the given coordinate
     *
     * @param float $latitude
     * @param float $longitude
     * @param string $columnPrefix
     * @return bool
     */
    private function checkCoordinate($latitude, $longitude, $columnPrefix = '')
    {
        // get the polygon column's name
        $classProperty = (!strlen($columnPrefix)) ? 'polygon' : rtrim($columnPrefix, '_') . 'Polygon';
        // get the polygon from the class if already exists
        $polygon = $this->{$classProperty};
        // if the polygon is not already built
        if (is_null($polygon)) {
            // build the polygon as a location polygon
            $polygon = new LocationPolygon();
            foreach ($this->polygon as $latlong) {
                $polygon->addPoint(new Coordinate($latlong[0], $latlong[1]));
            }

            // update the property on the class
            $this->{$classProperty} = $polygon;
        }
        // check if the polygon contains the coordinate
        return $polygon->contains(new Coordinate($latitude, $longitude));
    }

    /**
     * Check if the address matches the local polygon
     *
     * @param Address $address
     * @param Address $secondAddress
     * @param string $columnPrefix
     * @return bool
     */
    public function checkAddress($address, $columnPrefix = '', $secondAddress = null)
    {
        // get the address from the repository
        $addressesRepo = new AddressesRepository();
        $address = $addressesRepo->get($address);
        if (!$address instanceof Address) {
            return false;
        }

        // check if the address matches the local columns
        foreach (['country', 'state', 'city', 'zipcode'] as $column) {
            if (!$this->checkColumn($address, $column, $columnPrefix)) {
                return false;
            }
        }

        // check ranges
        if (!is_null($this->max_range) || !is_null($this->min_range)) {
            if (is_null($secondAddress)) {
                return false;
            }

            if (
                !is_null($this->max_range) &&
                $this->max_range > 0 &&
                    // max range is in km, measureDistance is in meter
                (1000 * $this->max_range) > $address->measureDistance($secondAddress->latitude, $secondAddress->longitude)
            ) {
                return false;
            }

            if (
                !is_null($this->min_range) &&
                $this->min_range > 0 &&
                    // max range is in km, measureDistance is in meter
                (1000 * $this->min_range) < $address->measureDistance($secondAddress->latitude, $secondAddress->longitude)
            ) {
                return false;
            }
        }

        // if the polygon is limited by specific coordinates, and the address is out of thoses coordinates
        if (
            !is_null($this->{$columnPrefix . 'polygon'}) &&
            !$this->checkCoordinate($address->latitude, $address->longitude, $columnPrefix)
        ) {
            return false;
        }

        // all conditions are met
        return true;
    }

    /**
     * Get the geographical entities that are between two addresses
     *
     * @param Address $pickupAddress
     * @param Address $dropoffAddress
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Support\Collection
     */
    public function getBetweenAddresses($pickupAddress, $dropoffAddress, $collection = false)
    {
        // make a query if one is not provided
        if (!$collection) {
            $collection = self::where('active', true)->get();
        }

        foreach ($collection as $i => $item) {
            foreach (['pickup', 'dropoff'] as $prefix) {
                foreach (['country', 'state', 'city', 'zipcode'] as $column) {
                    if (!$item->checkColumn(${$prefix . 'Address'}, $column, $prefix . '_')) {
                        // forget unmatched polygons
                        $collection->forget($i);
                        break 2;
                    }
                }

                // check range
                if (
                    !is_null($item->max_range) &&
                    $item->max_range > 0 &&
                        // max range is in km, measureDistance is in meter
                    (1000 * $item->max_range) < $pickupAddress->measureDistance($dropoffAddress->latitude, $dropoffAddress->longitude)
                ) {
                    $collection->forget($i);
                    break;
                }

                if (
                    !is_null($item->min_range) &&
                    $item->min_range > 0 &&
                        // max range is in km, measureDistance is in meter
                    (1000 * $item->min_range) > $pickupAddress->measureDistance($dropoffAddress->latitude, $dropoffAddress->longitude)
                ) {
                    $collection->forget($i);
                    break;
                }

                if (
                    !is_null($item->{$prefix . '_polygon'}) &&
                    !$item->checkCoordinate(${$prefix . 'Address'}->latitude, ${$prefix . 'Address'}->longitude, $prefix)
                ) {
                    // forget unmatched polygons
                    $collection->forget($i);
                    break;
                }
            }
        }

        return $collection;
    }

    /**
     * Get the geographical entities that are relevant to an address
     *
     * @param Address $address
     * @param string $columnPrefix
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Support\Collection
     */
    public static function getForAddress($address, $columnPrefix = '', $collection = false)
    {
        // make a collection if one is not provided
        if (!$collection) {
            $collection = self::where('active', true)->get();
        }

        foreach ($collection as $i => $item) {
            // check if the addresses match the relevant columns
            foreach (['country', 'state', 'city', 'zipcode'] as $column) {
                if (!$item->checkColumn($address, $column, $columnPrefix)) {
                    // forget unmatched polygons
                    $collection->forget($i);
                    break;
                }
            }

            // check geometric polygons
            if (
                !is_null($item->{$columnPrefix . 'polygon'}) &&
                !$item->checkCoordinate($address->latitude, $address->longitude, $columnPrefix)
            ) {
                // forget unmatched polygons
                $collection->forget($i);
            }
        }

        return $collection;
    }
}
