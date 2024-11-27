<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class XSS
{
    public function handle(Request $request, Closure $next)
    {
        $input = $request->all();
        array_walk_recursive($input, function (&$input, $key) {
            if ($key === 'id' || str_ends_with($key, '_id')) {
                $key = intVal($key);
            } else if ($key !== 'password') {
                $input = strip_tags($input);
            }
        });
        $request->merge($input);
        return $next($request);
    }
}
