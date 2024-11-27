<?php

namespace App\Http\Controllers;

use App\Http\Requests\Onboarding\SaveRequest;
use App\Repositories\OnboardingRepository;

class OnboardingController extends Controller
{
    /**
     * Onboard a new store
     *
     * @return \Illuminate\Http\Response
     */
    public function save(SaveRequest $request)
    {
        $repo = new OnboardingRepository();
        $store = $repo->onboard($request);
        if (isset($store['fail']) && $store['fail']) {
            return $this->fail($store);
        }

        return $this->respond(['store' => $store], 201);
    }

    /**
     * Onboard a new store
     *
     * @return \Illuminate\Http\Response
     */
    public function getData()
    {
        $repo = new OnboardingRepository();
        return $this->respond($repo->getData());
    }
}
