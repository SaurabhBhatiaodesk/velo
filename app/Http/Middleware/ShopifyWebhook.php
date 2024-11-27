<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ShopifyShop;

class ShopifyWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $shop = ShopifyShop::where('domain', $request->header('x-shopify-shop-domain'))->first();
        if (!$shop) {
            return response()->json(['message' => 'invalid shop'], 200);
        }

        $hmac_header = $request->header('x-shopify-hmac-sha256');
        $calculated_hmac = base64_encode(hash_hmac('sha256', $request->getContent(), config('shopify.apiSecret'), true));
        if (!hash_equals($hmac_header, $calculated_hmac)) {
            return response()->json(['message' => 'invalid hmac'], 200);
        }

        return $next($request);
    }
}
