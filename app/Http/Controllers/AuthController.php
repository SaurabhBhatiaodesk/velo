<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ForgotRequest;
use App\Http\Requests\Auth\PasswordResetRequest;
use App\Http\Requests\Auth\ValidatePasswordResetToken;
use App\Http\Requests\Auth\setLocaleRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\ValidateOtpRequest;
use App\Mail\Users\PasswordReset;
use App\Mail\Users\Otp;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\Locale;
use App\Traits\HandlesUserTokens;
use App\Traits\Integrations\ConnectsShopifyToUser;

use App\Events\Public\Token\Approved as TokenApproved;
use App\Events\Public\Token\Denied as TokenDenied;

use App\Jobs\Integrations\Shopify\CheckScopesJob;

class AuthController extends Controller
{
    use HandlesUserTokens, ConnectsShopifyToUser;

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['only' => ['me', 'logout', 'reset', 'setlocale']]);
    }

    public function queueSyncChecks(User $user = null)
    {
        if (is_null($user)) {
            $user = auth()->user();
        }
        foreach ($user->stores as $store) {
            if (!Cache::get('velo.check_scopes_job.' . $store->slug)) {
                CheckScopesJob::dispatch($store);
                Cache::put('velo.check_scopes_job.' . $store->slug, true, now()->addDay());
            }
        }
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {
        $inputs = $this->validateRequest($request);
        try {
            $credentials = [
                'email' => $inputs['email'],
                'password' => $inputs['password'],
            ];

            if (!$token = auth()->attempt($credentials)) {
                return response()->json(['message' => 'unauthorized'], 401);
            }

            $user = User::find(auth()->id());
            if (is_null($user->email_verified_at)) {
                return response()->json(['message' => 'emailUnverified'], 401);
            }

            $shopifyShop = false;
            // if a Shopify domain and token were provided
            if (
                isset($inputs['shopify_domain']) &&
                strlen($inputs['shopify_domain']) &&
                isset($inputs['shopify_token']) &&
                strlen($inputs['shopify_token'])
            ) {
                // update the email address of the ShopifyShop if it doesn't match the user's
                $shopifyShop = $this->connectByDomainTokenAndEmail($inputs['shopify_domain'], $inputs['shopify_token'], $inputs['email']);
                // unset the Shopify domain and token
                unset($inputs['shopify_domain']);
                unset($inputs['shopify_token']);
            }

            $this->queueSyncChecks($user);

            return $this->respondWithToken($token, ['deleteShopifyShopCookie' => !!$shopifyShop]);
        } catch (JWTException $e) {
            return response()->json(['message' => 'tokenFail'], 500);
        }
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(auth()->user());
    }

    // old
    /**
     * Verify a user's email
     *
     * @param  VerifyEmailRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify(VerifyEmailRequest $request)
    {
        $inputs = $this->validateRequest($request);
        $user = User::firstWhere('email', $inputs['email']);
        if ($user->isVerified()) {
            return $this->respond([
                'message' => 'alreadyVerified',
                'email' => $user->email,
            ]);
        }
        $validateTokenResult = $this->validateAndNullifyToken($user, $inputs['token']);
        if ($validateTokenResult['fail']) {
            return $this->fail($validateTokenResult);
        }

        $user = User::where('email', $inputs['email'])->first();
        $user->assignRole('store_member');
        $token = auth()->login($user);
        return $this->respondWithToken($token);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();
        return $this->respond(['message' => 'logoutSuccess']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        $token = false;
        try {
            $token = auth()->refresh();
        } catch (\Exception $e) {
            if (\Exception::class == 'Tymon\JWTAuth\Exceptions\TokenExpiredException') {
                return $this->respond(['message' => 'tokenExpired'], 401);
            }
            return $this->respond(['message' => 'unauthorized'], 403);
        }
        if (!$token) {
            return $this->respond(['message' => 'tokenExpired'], 401);
        }
        $this->queueSyncChecks($this->queueSyncChecks(User::find(auth()->id())));
        return $this->respondWithToken($token);
    }

    /**
     * Send a password reset link
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgot(ForgotRequest $request)
    {
        $inputs = $this->validateRequest($request);
        $user = User::where('email', $inputs['email'])->first();

        $userToken = $this->makeUserToken($user);
        if (!Mail::to($user->email)->send(new PasswordReset($user, $userToken))) {
            return $this->respond(['error' => 'resetEmailnotSent']);
        }

        return $this->respond(['message' => 'resetSent']);
    }

    /**
     * Validate a password reset token
     *
     * @param  ValidatePasswordResetToken $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateReset(ValidatePasswordResetToken $request)
    {
        $inputs = $this->validateRequest($request);
        $user = User::firstWhere('email', $inputs['email']);

        $validateTokenResult = $this->validateAndNullifyToken($user, $inputs['token']);
        if ($validateTokenResult['fail']) {
            TokenDenied::dispatch($inputs['token']);
            return $this->fail($validateTokenResult);
        }

        $userToken = $this->makeUserToken($user);
        $token = auth()->login(User::where('email', $inputs['email'])->first());
        TokenApproved::dispatch($inputs['token'], $token);
        return $this->respondWithToken($token, ['token' => $userToken]);
    }

    /**
     * Reset a user's password
     *
     * @param  ValidatePasswordResetToken $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reset(PasswordResetRequest $request)
    {
        $inputs = $this->validateRequest($request);
        $user = auth()->user();
        $user->update(['password' => $inputs['password']]);

        return $this->respond();
    }

    /**
     * Changes the logged user's preferred locale
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function setlocale(setLocaleRequest $request)
    {
        $inputs = $this->validateRequest($request);
        $locale = Locale::find($request->localeId);
        if (!$locale) {
            return $this->respond(['message' => 'locale.invalid'], 422);
        }
        if (!auth()->user()->update(['locale_id' => $locale->id])) {
            $this->respond(['message' => 'locale.saveFailed'], 500);
        }
        return $this->respond(['user' => auth()->user()]);
    }

    /**
     * Sends an OTP to the user's email
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendOtp(SendOtpRequest $request)
    {
        $user = User::firstWhere('email', $request->email);
        if (!$user) {
            return $this->respond(['error' => 'userNotFound'], 404);
        }
        if (
            !$user->update([
                'token' => str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT),
                'token_created_at' => now(),
            ])
        ) {
            return $this->respond(['error' => 'otpNotSent'], 500);
        }

        if (!Mail::to($user->email)->send(new Otp($user))) {
            return $this->respond(['error' => 'otpNotSent'], 500);
        }

        return $this->respond(['message' => 'otpSent']);
    }

    /**
     * Validate an OTP and log the user in
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateOtp(ValidateOtpRequest $request)
    {
        $user = User::firstWhere('email', $request->email);
        if (!$user) {
            return $this->respond(['error' => 'userNotFound'], 404);
        }
        if ($user->token !== $request->otp) {
            return $this->respond(['error' => 'otpInvalid'], 401);
        }
        if ($user->token_created_at->diffInMinutes(now()) > 10) {
            return $this->respond(['error' => 'otpExpired'], 401);
        }

        $user->update([
            'token' => null,
            'token_created_at' => null,
        ]);

        $token = auth()->login($user);
        return $this->respondWithToken($token);
    }
}
