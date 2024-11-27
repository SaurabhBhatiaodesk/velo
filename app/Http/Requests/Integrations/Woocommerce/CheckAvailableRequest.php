<?php

namespace App\Http\Requests\Integrations\Woocommerce;

use App\Http\Requests\ApiRequest;

class CheckAvailableRequest extends ApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'customerAddress.phone' => 'nullable|numeric',
            'customerAddress.line1' => 'required',
            'customerAddress.city' => 'required',
            'customerAddress.country' => 'required',
            'customerAddress.latitude' => 'nullable|numeric',
            'customerAddress.longitude' => 'nullable|numeric',
            'dimensions.width' => 'required|numeric',
            'dimensions.height' => 'required|numeric',
            'dimensions.depth' => 'required|numeric',
            'weight' => 'required|numeric',
        ];
    }
}
