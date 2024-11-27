<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Locale;
use Illuminate\Support\Facades\Mail;
use App\Mail\Users\EmailVerification;
use App\Repositories\BaseRepository;
use App\Traits\HandlesUserTokens;
use App\Traits\Integrations\ConnectsShopifyToUser;

class UsersRepository extends BaseRepository
{
    use HandlesUserTokens, ConnectsShopifyToUser;

    public function store($inputs, $skipEmail = false)
    {
        // if a locale was provided, find the id
        if (
            isset($inputs['locale_iso']) &&
            strlen($inputs['locale_iso'])
        ) {
            $inputs['locale_id'] = Locale::where('iso', $inputs['locale_iso'])->pluck('id')->first();
            if (is_null($inputs['locale_id'])) {
                unset($inputs['locale_id']);
            }
        }
        // if no locale was provided, default to English
        if (!isset($inputs['locale_id'])) {
            $inputs['locale_id'] = 1;
        }

        // if a Shopify domain and token were provided
        if (
            isset($inputs['shopify_domain']) &&
            strlen($inputs['shopify_domain']) &&
            isset($inputs['shopify_token']) &&
            strlen($inputs['shopify_token'])
        ) {
            // update the email address of the ShopifyShop if it doesn't match the user's
            $this->connectByDomainTokenAndEmail($inputs['shopify_domain'], $inputs['shopify_token'], $inputs['email']);
            // unset the Shopify domain and token
            unset($inputs['shopify_domain']);
            unset($inputs['shopify_token']);
        }

        $user = User::create($inputs);
        if (!$skipEmail && $user && $this->throttleResets($user)) {
            $this->makeUserToken($user);
            Mail::to($user->email)->send(new EmailVerification($user));
        }

        return $user;
    }

    public function update($inputs, $user)
    {
        $user->fill($inputs);
        if (!$user->save()) {
            return $this->fail('user.updateFailed');
        }
        return $user;
    }
}
