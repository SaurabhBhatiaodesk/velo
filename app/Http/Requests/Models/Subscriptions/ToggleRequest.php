<?php

namespace App\Http\Requests\Models\Subscriptions;

use App\Http\Requests\VeloUpdateRequest;

class ToggleRequest extends VeloUpdateRequest
{
    protected $modelParam = 'stores';
}
