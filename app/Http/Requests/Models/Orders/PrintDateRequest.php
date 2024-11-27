<?php

namespace App\Http\Requests\Models\Orders;

use App\Http\Requests\ApiRequest;

class PrintDateRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'store_slug' => 'required|string',
            'date' => 'required',
        ];
    }
}
