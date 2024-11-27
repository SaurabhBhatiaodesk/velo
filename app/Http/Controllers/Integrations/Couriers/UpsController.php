<?php

namespace App\Http\Controllers\Integrations\Couriers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Courier;
use App\Repositories\Couriers\UpsRepository;

class UpsController extends Controller
{
    public function __construct()
    {
        $this->repo = new UpsRepository();
    }

    public function authCallback(Request $request)
    {
        return $this->repo->authCallback();
        return $this->respond();
    }
}
