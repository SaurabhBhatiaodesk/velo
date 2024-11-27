<?php

namespace App\Http\Controllers\SupportSystem;

use App\Http\Controllers\Controller;
use App\Models\User;
use Request;

class SupportSystemController extends Controller
{

    static public function getUser()
    {
        return User::where('email', Request::header('email'))->first();
    }

    static public function uri()
    {
        return Request::path();
    }

    static public function storeSlug()
    {
        return Request::header('store-slug');
    }

    static public function getRequest()
    {
        return [
            'uri' => SupportSystemController::uri(),
            'email' => Request::header('email'),
            'store-slug' => Request::header('store-slug')
        ];
    }

    static public function response($data)
    {
        \Log::info('SUPPORT_SYSTEM__RESPONSE -> ' . self::uri(), $data);
        return response()->json($data);
    }

    static public function notFound($arr = [])
    {
        $response = ['success' => 0];
        \Log::info('SUPPORT_SYSTEM__NOT_FOUND', [
            'request' => self::getRequest(),
            'response' => $response,
            'data' => $arr
        ]);
        return response()->json($response);
    }

    static public function error($err = 401, $desc = false, $reason = "")
    {
        $errors = [
            401 => 'Operation failed.',
            403 => 'Not authorized.',
        ];
        $response = [
            'success' => 0,
            'error' => $desc ? $desc : $errors[$err]
        ];

        \Log::error('SUPPORT_SYSTEM__ERROR', [
            'request' => self::getRequest(),
            'response' => $response,
            'reason' => $reason
        ]);
        return response()->json($response, $err);
    }
    static public function query($query)
    {
        $sql = $query->toSql();
        $bindings = $query->getBindings();
        $fullQuery = vsprintf(str_replace('?', '%s', $sql), array_map(function ($binding) {
            return is_numeric($binding) ? $binding : "'" . addslashes($binding) . "'";
        }, $bindings));
        return $fullQuery;
    }
}
