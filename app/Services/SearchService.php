<?php

namespace App\Services;

use App\Repositories\Serp\BrightDataRepository;

class SearchService
{
    /*
     * Search the web for a given query
     *
     * @param string $query
     * @return array
     */
    public static function search($query, $zone, $site = null, $country = null, $locale = null)
    {
        $repo = new BrightDataRepository();
        return $repo->search($query, $zone, $site, $country, $locale);
    }

    /*
     * Search the web for a given query
     *
     * @param string $query
     * @return array
     */
    public static function searchProducts($query, $zone, $store)
    {
        $repo = new BrightDataRepository();
        return $repo->searchProducts($query, $zone, $store);
    }
}
