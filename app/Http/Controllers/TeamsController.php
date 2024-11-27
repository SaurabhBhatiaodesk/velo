<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\Store;
use App\Http\Requests\Models\Users\Team\AcceptInviteRequest;
use App\Repositories\UsersRepository;
use App\Mail\Users\UserStoreInvite;
use Carbon\Carbon;
use App\Events\Public\Token\Approved as TokenApproved;
use App\Events\Public\Token\Denied as TokenDenied;


class TeamsController extends Controller
{
    /**
     * Get a store's team members.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Store $store)
    {
        $team = $store->users()->with('roles')->get();
        $team->push($store->user->load('roles'));
        return $this->respond($team);
    }

    /**
     * Invite a User to join a store
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Store $store)
    {
        if (!config('plans.' . $store->plan_subscription->subscribable->name . '.member.can_add')) {
            return $this->respond([
                'message' => 'plans.featureUnavailable',
            ], 403);
        }
        $inputs = $request->all();
        $createSubscription = false;
        // store owner + store members
        $usersCount = $store->users()->count();
        $limit = config('plans.' . $store->plan_subscription->subscribable->name . '.member.limit');
        if ($limit > 0 && $usersCount >= $limit) {
            return $this->respond(['message' => 'teams.limitReached'], 200);
        }
        if ($usersCount >= config('plans.' . $store->plan_subscription->subscribable->name . '.member.included')) {
            if (!isset($inputs['confirm_payment'])) {
                return $this->respond(['message' => 'teams.paymentRequired'], 200);
            }
            $createSubscription = true;
        }

        $user = User::where('email', $inputs['email'])->first();
        if (!$user) {
            $usersRepo = new UsersRepository();
            $user = $usersRepo->store(array_merge($inputs, ['password' => Str::random(8)]), true);
        }

        if ($createSubscription) {
            $subscription = $store->subscriptions()->create([
                'store_slug' => $store->slug,
                'starts_at' => Carbon::now(),
                // synchronize to end of plan subscription to synchoronize with the store's billing cycle
                'ends_at' => $store->plan_subscription->ends_at,
                // auto_renew is false because we need to count subscriptions by type when renewing the plan
                'auto_renew' => false,
                'renewed_from' => null,
                'subscribable_type' => 'App\\Models\\User',
                'subscribable_id' => $user->id,
            ]);

            $priceFactor = $store->plan_subscription->ends_at->diffInDays(Carbon::now()) / $store->plan_subscription->ends_at->diffInDays($store->plan_subscription->starts_at);
            $chargeResult = $store->billingRepo()->billAndChargeSubscription($subscription, 'member', $priceFactor);
            if (isset($chargeResult['fail'])) {
                $subscription->bill->delete();
                $subscription->delete();
                return $this->fail($chargeResult);
            }
        }

        $storeUser = $store->users()->find($user->id);
        if (!$storeUser || is_null($storeUser->pivot->joined_at)) {
            $token = Str::random(32);

            if (!$storeUser) {
                $store->users()->attach($user->id, [
                    'invited_at' => Carbon::now(),
                    'store_role' => $inputs['store_role'],
                    'token' => $token
                ]);

                $storeUser = $store->users()->find($user->id);
            } else {
                !$store->
                    users()
                    ->updateExistingPivot($user->id, [
                        'token' => $token,
                        'invited_at' => Carbon::now(),
                        'store_role' => $inputs['store_role'],
                    ]);
            }

            if (!Mail::to($user->email)->send(new UserStoreInvite($user, $store, $token))) {
                return $this->respond(['message' => 'teams.inviteFailed'], 500);
            }
        }

        return $this->respond(
            $store
                ->users()
                ->where('user_id', $user->id)
                ->with('roles')
                ->first()
        );
    }

    /**
     * Accept an invitation to join a store
     *
     * @param $email
     * @param $token
     * @return \Illuminate\Http\JsonResponse
     */
    public function acceptInvite(AcceptInviteRequest $request)
    {
        $user = User::where('email', $request->input('email'))->first();

        if (!$user) {
            TokenDenied::dispatch($request->input('token'));
            return $this->respond([
                'message' => 'auth.invalidCredentials',
            ], 422);
        }

        $store = $user->team_stores()->wherePivot('token', $request->input('token'))->first();
        if (!$store) {
            TokenDenied::dispatch($request->input('token'));
            return $this->respond([
                'message' => 'auth.invalidCredentials',
            ], 422);
        }

        if (!$user->isVerified()) {
            if (
                !$user->update([
                    'token' => null,
                    'token_created_at' => null,
                    'email_verified_at' => Carbon::now(),
                ]) ||
                !$user->assignRole('store_member')
            ) {
                TokenDenied::dispatch($request->input('token'));
                return $this->respond([
                    'message' => 'user.verificationFailed',
                ], 500);
            }
        }

        if (
            !$store->
                users()
                ->updateExistingPivot($user->id, [
                    'token' => null,
                    'joined_at' => Carbon::now()
                ])
        ) {
            TokenDenied::dispatch($request->input('token'));
            return $this->respond([
                'message' => 'team.invitesFailed',
            ], 500);
        }

        $token = auth()->login($user);
        $data = [
            'store' => [
                'name' => $store->name,
                'slug' => $store->slug,
            ]
        ];

        TokenApproved::dispatch($request->input('token'), $token, $data);
        return $this->respondWithToken($token, $data);
    }

    /**
     * Display the specified resource.
     *
     * @param string $email
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Store $store, $email)
    {
        return $this->respond(User::where('email', $email)->first());
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Store $store, $id)
    {
        return $this->respond([
            'message' => 'TeamController@update',
            'inputs' => $request->all(),
            'store' => $store->name,
            'id' => $id,
        ]);
    }

    /**
     * Remove a member from a store
     *
     * @param $email
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Store $store, $email)
    {
        $user = User::where('email', $email)->first();
        if (!$user || !$user->team_stores()->where('id', $store->id)->exists()) {
            return $this->respond(['message' => 'teams.memberNotFound']);
        }

        if (!$store->users()->detach($user->id)) {
            return $this->respond(['message' => 'teams.removeFailed', 500]);
        }

        return $this->respond(['message' => 'teams.removeSuccess']);
    }
}
