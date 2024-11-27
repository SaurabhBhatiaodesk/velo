<?php

namespace App\Http\Requests\Models\Orders;

use App\Http\Requests\ApiRequest;

class PrintMultiRequest extends ApiRequest
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
            'names' => 'required',
            'size' => 'required|string',
        ];
    }
}
