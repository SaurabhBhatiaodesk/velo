<?php

namespace App\Http\Controllers;

use Firebase\JWT\JWT;
use Illuminate\Http\Request;

class ZendeskJWTController extends Controller
{
    static public function generate($user)
    {
        $appId = env('ZENDESK_SUPPORT_APP_ID', '');
        $secret = env('ZENDESK_SUPPORT_JWT_SECRET', '');
        $header = array("kid" => $appId);
        return JWT::encode(self::payload($user), $secret, 'HS256', $appId, $header);
    }

    static private function payload($user)
    {
        return array(
            "externalId" => $user->email,
            "userId" => $user->slug,
            "scope" => "appUser"
        );
    }
}
