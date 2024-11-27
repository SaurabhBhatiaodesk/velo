<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\Auth\TokenLoginRequest;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use App\Models\Locale;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Config;


class SocialAuthController extends Controller
{
    private $supportedProviders = [
        'google',
        'facebook'
    ];

    /**
     * Redirect to social provider
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirect(Request $request, $provider)
    {
        if (!in_array($provider, $this->supportedProviders)) {
            return $this->respond([], 422);
        }
        return Socialite::driver($provider)
            ->redirectUrl(route('social_auth.callback', ['provider' => $provider]))
            ->redirect();
    }

    /**
     * Handle social provider callback
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function callback(Request $request, $provider)
    {
        if (!in_array($provider, $this->supportedProviders)) {
            return $this->respond([], 422);
        }
        try {
            $socialUser = Socialite::driver($provider)
                ->redirectUrl(route('social_auth.callback', ['provider' => $provider]))
                ->stateless()
                ->user();
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return json_decode($e->getResponse()->getBody()->getContents())->error_description;
        }

        $user = User::where('email', $socialUser->email)->first();

        if (!$user) {
            $locale = new Locale();
            $locale = $locale->check('en_US');

            // defaults
            $user = [
                'email' => $socialUser->email,
                'password' => rand(100000, 999999),
                'email_verified_at' => Carbon::now(),
                'locale_id' => $locale->id,
            ];

            // image
            if (property_exists($socialUser, 'picture')) {
                $user['image'] = $socialUser->picture;
            } else if (property_exists($socialUser, 'avatar')) {
                $user['image'] = $socialUser->avatar;
            }

            // first & last names
            if (property_exists($socialUser, 'given_name')) {
                $user['first_name'] = $socialUser->given_name;
            }
            if (property_exists($socialUser, 'family_name')) {
                $user['last_name'] = $socialUser->family_name;
            }
            if (
                property_exists($socialUser, 'name') &&
                strpos($socialUser->name, ' ') !== false &&
                (
                    !isset($user['first_name']) ||
                    is_null($user['first_name']) ||
                    !strlen($user['first_name']) ||
                    !isset($user['last_name']) ||
                    is_null($user['last_name']) ||
                    !strlen($user['last_name'])
                )
            ) {
                $user['last_name'] = explode(' ', $socialUser->name);
                $user['first_name'] = array_shift($user['last_name']);
                $user['last_name'] = implode(' ', $user['last_name']);
            }

            $user = User::create($user);
            if (!$user) {
                return $this->respond(['message' => 'auth.failed'], 500);
            }
        }

        if (
            !$user->update([
                'token' => property_exists($socialUser, 'token') ? $socialUser->token : strtr(base64_encode(Str::random(10)), '+/', '-_'),
                'token_created_at' => Carbon::now(),
            ])
        ) {
            return $this->respond(['message' => 'auth.failed'], 500);
        }

        return redirect(rtrim(config('app.client_url'), '/') . '/auth/social/' . rawurlencode($user->email) . '/' . $user->token);
    }

    /**
     * Login user with SSO token
     *
     * return \Illuminate\Http\JsonResponse
     */
    public function tokenLogin(TokenLoginRequest $request)
    {
        $inputs = $this->validateRequest($request);
        $user = User::where('email', $inputs['email'])->where('token', $inputs['token'])->first();
        if (!$user) {
            return $this->respond(['message' => 'auth.token.invalid'], 401);
        }

        $user->update([
            'token' => null,
            'token_created_at' => null,
        ]);

        $token = auth()->login($user);
        return $this->respondWithToken($token);
    }
}
