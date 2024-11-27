<?php

namespace App\Http\Requests\Models\Orders;

use App\Http\Requests\ApiRequest;

class PrintRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'id' => 'required|numeric'
        ];
    }
}
