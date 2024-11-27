<?php

namespace App\Http\Requests\Models\Orders;

use App\Http\Requests\VeloBrowseRequest;

class BrowseRequest extends VeloBrowseRequest
{
    protected $modelParam = 'orders';
}
