<?php

namespace App\Http\Middleware;

use App\Http\Controllers\SupportSystem\SupportSystemController;
use App\Models\Store;
use App\Models\SupportSystem;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SupportSystemApiHeaders
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

        $email = $request->header('email');
        ;
        $storeSlug = $request->header('store-slug');
        ;

        \Log::info('SUPPORT_SYSTEM_API_REQUEST', [
            'email' => $email,
            'store-slug' => $storeSlug
        ]);
        $supportSystem = SupportSystem::where('name', config('services.zendesk.support_system_api_name'))->first();

        if ($request->header('Authorization') !== 'Bearer ' . $supportSystem->secret) {
            \Log::info('SUPPORT_SYSTEM_API_AUTH_ERROR', [$request->header('Authorization')]);
            return SupportSystemController::error(403, 'Invalid Credentials', "Invalid 'Authorization' header");
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return SupportSystemController::error(403, 'Invalid Credentials', "User not found for email: $email");
        }
        $store = Store::where('user_id', '=', $user->id)->first();
        if (!$store) {
            return SupportSystemController::error(403, 'Invalid Credentials', "User not found for user_id: $user->id ");
        }
        if ($store->slug !== $storeSlug) {
            return SupportSystemController::error(403, 'Invalid Credentials' . "Store slug mismatch");
        }

        return $next($request);
    }
}
