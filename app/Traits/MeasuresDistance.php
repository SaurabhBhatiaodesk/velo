<?php

namespace App\Traits;

use Location\Polygon as LocationPolygon;
use Location\Coordinate;
use App\Repositories\AddressesRepository;
use App\Models\Address;
use App\Models\Locale;

trait MeasuresDistance
{
    /**
     * organize a list of objects by distance from the current object
     * @param  \Illuminate\Support\Collection $addresses (has latitude and longitude fields)
     * @return array [ distance => address ]
     */
    public function organizeByDistance($addresses)
    {
        $distances = [];
        foreach ($addresses as $i => $address) {
            if (is_object($address)) {
                if (method_exists($address, 'toArray')) {
                    $address = $address->toArray();
                } else {
                    $address = json_decode(json_encode($address), true);
                }
            }

            if (
                is_null($address['latitude']) ||
                is_null($address['longitude']) ||
                !strlen($address['latitude']) ||
                !strlen($address['longitude'])
            ) {
                continue;
            }
            $distances[$this->measureDistance(
                $address['latitude'],
                $address['longitude'],
            )] = $addresses[$i];
        }

        ksort($distances);
        return $distances;
    }

    /**
     * Calculates the great-circle distance between two points, with
     * the Vincenty formula.
     * @param float $latitude Latitude of target point in [deg decimal]
     * @param float $longitudeTo Longitude of target point in [deg decimal]
     * @param float $earthRadius Mean earth radius in [meters]
     * @return float Distance between points in [meters (same as earthRadius)]
     */
    public function measureDistance(
        $latitude,
        $longitude,
        $earthRadius = 6371000
    ) {
        if (!$this->latitude || !$this->longitude) {
            return -1;
        }
        // convert from degrees to radians
        $latFrom = deg2rad(floatVal($this->latitude));
        $lonFrom = deg2rad(floatVal($this->longitude));
        $latTo = deg2rad(floatVal($latitude));
        $lonTo = deg2rad(floatVal($longitude));

        $lonDelta = $lonTo - $lonFrom;
        $a = pow(cos($latTo) * sin($lonDelta), 2) +
            pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
        $b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);

        $angle = atan2(sqrt($a), $b);
        return $angle * $earthRadius;
    }
}
