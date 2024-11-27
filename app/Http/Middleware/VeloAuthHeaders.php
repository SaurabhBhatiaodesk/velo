<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VeloAuthHeaders
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
        if (
            !$request->headers->has('X-Velo-Api-Key') ||
            (
                $request->route()->getName() !== 'enterprise.login' &&
                $request->route()->getName() !== 'woocommerce.login' &&
                !$request->headers->has('X-Velo-Hmac')
            )
        ) {
            return response()->json(['message' => 'invalid api credentials'], 403);
        }

        return $next($request);
    }
}
