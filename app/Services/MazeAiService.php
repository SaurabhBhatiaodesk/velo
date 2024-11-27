<?php

namespace App\Services;

use App\Repositories\AI\OpenAiRepository as AiRepository;
use App\Services\SearchService;
use App\Models\Order;

class MazeAiService
{
    /**
     * Get product recommendations for an order
     *
     * @param Order $order
     * @return array
     */
    public static function getOrderRecommendations(Order $order, $zone)
    {
        if (!count($order->products) || empty($order->store->website)) {
            return [];
        }

        $relatedProductsQuerySuffix = implode(' OR ', array_map(
            fn($item) => strpos($item, 'inurl:') === 0 ? $item : '"' . $item . '"',
            [
                'inurl:collections',
                'inurl:products',
                'inurl:search',
                'related products',
                'similar products',
                'customers also bought',
                'you may also like',
                'recommended for you',
            ]
        ));

        $retailQuerySuffix = implode(' OR ', array_map(fn($item) => '"' . $item . '"', [
            $order->store->currency->symbol,
            $order->store->currency->iso,
            'price',
            'cost',
            'buy',
        ]));

        $query = [];
        foreach ($order->products as $i => $product) {
            $productName = $product->name;
            if (!empty($product->pivot->variation)) {
                $productName = str_replace(" - {$product->pivot->variation}", '', $productName);
            }
            $query[] = '"' . $productName . '"';
        }
        $query = "(" . implode(' OR ', $query) . ') (' . $relatedProductsQuerySuffix . ') (' . $retailQuerySuffix . ')';
        $currency = $order->store->currency;

        return SearchService::searchProducts($query, $zone, $order->store);
    }

    /**
     * Make a custom prompt to the AI
     *
     * @param string $prompt
     * @param string $model
     * @param int $maxTokens
     * @return array
     */
    public static function prompt($prompt, $model = 'gpt-3.5-turbo', $maxTokens = 150)
    {
        $repo = new AiRepository();
        return $repo->prompt($prompt, $model, $maxTokens);
    }
}
