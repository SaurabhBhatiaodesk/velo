<?php

namespace App\Http\Requests\Venti;

use Illuminate\Foundation\Http\FormRequest;

class CheckReturnInfoRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'address.phone' => 'required',
            'address.longitude' => 'required',
            'address.latitude' => 'required',
            'deliveryType' => 'required',
            'description' => 'required',
        ];
    }
}
