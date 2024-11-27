<?php

namespace App\Http\Requests\Models\Orders;

use App\Http\Requests\VeloReadRequest;

class GetDeliveryRequest extends VeloReadRequest
{
    protected $modelParam = 'orders';

    public function authorize()
    {
        return !!$this->user();
    }

    public function rules()
    {
        return [
            'order_ids' => 'required|array',
        ];
    }
}
