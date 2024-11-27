<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Repositories\UsersRepository;
use App\Http\Requests\Models\Users\StoreRequest;
use App\Http\Requests\Models\Users\UpdateRequest;

class UsersController extends Controller
{
    public function __construct(UsersRepository $repo)
    {
        $this->repo = $repo;
        $this->middleware('auth:api', ['except' => ['store', 'verify']]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRequest $request)
    {
        $inputs = $this->validateRequest($request);
        $user = $this->repo->store($inputs);
        if (!$user) {
            return $this->respond([
                'message' => 'user creation failed',
            ], 500);
        }
        return $this->respond(['user' => $user], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return $this->respond(['id' => $id]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRequest $request, User $user)
    {
        $inputs = $this->validateRequest($request);
        if ($user->id !== auth()->id()) {
            return $this->respond([
                'code' => 403,
                'message' => 'forbidden',
            ], 403);
        }

        $user = $this->repo->update($inputs, $user);
        if (isset($user['fail'])) {
            return $user;
        }
        return $this->respond(['user' => $user], 200);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        return $this->respond(['id' => $id]);
    }
}
