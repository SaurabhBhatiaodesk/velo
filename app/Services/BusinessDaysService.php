<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

class BusinessDaysService
{
    /*
     * Returns a count of business days between two dates
     *
     * @param mixed $fromDate
     * @param mixed $toDate
     * @param array $weekdays (default is Subday to Thursday)
     * @return int
     */
    public static function count($fromDate, $toDate, $weekdays = [0, 1, 2, 3, 4])
    {
        if (!$fromDate || !$toDate) {
            return 0;
        }

        if (!$fromDate instanceof Carbon) {
            $fromDate = Carbon::parse($fromDate);
        }
        if (!$toDate instanceof Carbon) {
            $toDate = Carbon::parse($toDate);
        }

        $period = CarbonPeriod::create($fromDate, $toDate);
        $count = 0;
        foreach ($period as $date) {
            if (in_array($date->dayOfWeek, $weekdays)) {
                $count++;
            }
            if ($count > 60)
                break;
        }
        return $count;
    }
}
