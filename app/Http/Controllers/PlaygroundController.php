<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;


class PlaygroundController extends Controller
{
    function run()
    {
        $user = User::where('email', 'oryan@veloapp.io')->first();
        $jwt = $user->getZendeskToken('velo-qa');
        return view('playground', [
            'jwt' => $jwt,
            'externalId' => $user->email,
            'appUrl' => rtrim(config('app.url'), '/'),
        ])->with('jwt', $jwt)->with('externalId', $user->email);
    }
    function translate()
    {

        return;
    }
}
