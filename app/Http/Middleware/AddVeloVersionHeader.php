<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AddVeloVersionHeader
{
    public function handle(Request $request, Closure $next): mixed
    {
        $result = $next($request);
        if (!is_null($result) && method_exists($result, 'header')) {
            $result->header('X-Velo-Client-Version', config('app.version'));
        }
        return $result;
    }
}
