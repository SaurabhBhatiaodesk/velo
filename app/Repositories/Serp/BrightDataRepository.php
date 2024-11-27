<?php

namespace App\Repositories\Serp;

use App\Repositories\BaseRepository;
use App\Models\Locale;
use App\Models\Currency;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

class BrightDataRepository extends BaseRepository
{
    private $apiKey;
    private $apiRoot;

    public function __construct()
    {
        $this->apiKey = config('services.brightdata.api_key');
        $this->apiRoot = config('services.brightdata.api_root');
    }

    public function searchProducts($query, $zone, $store)
    {
        $response = $this->search($query, $zone, $store->website_domain);
        $results = [];
        $images = [];

        if (!empty($response['images'])) {
            foreach ($response['images'] as $image) {
                $images[$image['link']] = $image['image'];
            }
        }

        if (!empty($response['organic'])) {
            foreach ($response['organic'] as $organic) {
                if (!empty($organic['extensions'])) {
                    foreach ($organic['extensions'] as $extension) {
                        if (
                            strpos($extension['text'], $store->currency->iso) !== false ||
                            strpos($extension['text'], $store->currency->symbol) !== false
                        ) {
                            $results[] = [
                                'title' => $organic['title'] ?? null,
                                'link' => $organic['link'] ?? null,
                                'price' => $extension['text'] ?? null,
                                'description' => $organic['description'] ?? null,
                                'image' => $images[explode('?', $organic['link'])[0]] ?? null,
                            ];
                            continue 2; // Go to next result after finding the first match
                        }
                    }
                }
            }
        }

        return $results;
    }


    public function search($query, $zone, $site = null, $country = null, $locale = null)
    {
        // limit to 10 results
        $query .= '&start=0&num=10';
        if (!empty($site)) {
            $query = "site:{$site} {$query}";
        }

        if (!empty($country)) {
            $country = strtolower($country);
            // Convert country to ISO if needed
            if (!empty(config("countries.countryFromIso.{$country}"))) {
                $country = config("countries.countryFromIso.{$country}");
            }
            // If the country ISO is valid, add it to the query
            if (!empty(config("countries.isoFromCountry.{$country}"))) {
                $query .= '&gl=' . config("countries.isoFromCountry.{$country}");
            }
        }

        if (!empty($locale)) {
            if (!$locale instanceof Locale) {
                $locale = new Locale();
                $locale = $locale->check($locale);
            }

            if ($locale instanceof Locale) {
                $query .= "&hl={$locale->ietf}";
            }
        }

        $query = urlencode($query);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$this->apiKey}",
            ])->post($this->apiRoot, [
                        'zone' => $zone,
                        'url' => "https://www.google.com/search?q={$query}&brd_json=1",
                        'format' => 'raw',
                    ])
                ->json();
        } catch (ConnectionException $e) {
            return [
                'fail' => true,
                'error' => 'search.failed',
                'message' => $e->getMessage(),
                'code' => 500,
            ];
        }

        return $response;
    }
}
