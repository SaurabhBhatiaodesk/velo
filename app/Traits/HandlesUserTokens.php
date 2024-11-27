<?php

namespace App\Traits;

use Illuminate\Support\Str;
use Carbon\Carbon;

trait HandlesUserTokens
{
    private function throttleResets($user)
    {
        if (!is_null($user->token_created_at)) {
            $prevToken = Carbon::parse($user->token_created_at);
            if ($prevToken->diffInSeconds(Carbon::now()) < $this->minHoursPerToken * 3600) {
                return false;
            }
        }
        return true;
    }

    private function makeUserToken($user)
    {
        $token = Str::random(30);
        $user->update([
            'token' => $token,
            'token_created_at' => Carbon::now()
        ]);
        return $token;
    }

    private function validateAndNullifyToken($user, $token)
    {
        if (is_null($user->token) || $token !== $user->token) {
            return [
                'fail' => true,
                'error' => 'auth.invalidToken',
                'code' => 403,
            ];
        }

        if ($user->token_created_at->diffInDays(Carbon::now()) > 0) {
            return [
                'fail' => true,
                'error' => 'auth.tokenExpired',
                'code' => 403,
            ];
        }

        if (
            !$user->update([
                'token' => null,
                'token_created_at' => null,
                'email_verified_at' => Carbon::now(),
            ])
        ) {
            return [
                'fail' => true,
                'error' => 'user.token.invalidationFailed',
                'code' => 500,
            ];
        }

        return ['fail' => false];
    }
}
