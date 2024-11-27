<?php

namespace App\Http\Requests\Integrations\EnterpriseApi;

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
        return !!$this->user();
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
            'customerAddress.street' => 'required_without:customerAddress.line1',
            'customerAddress.number' => 'required_without:customerAddress.line1',
            'customerAddress.line1' => 'required_without_all:customerAddress.street,customerAddress.number',
            'customerAddress.city' => 'required',
            'customerAddress.country' => 'required',
            'customerAddress.latitude' => 'nullable|numeric',
            'customerAddress.longitude' => 'nullable|numeric',
            'storeAddress.phone' => 'nullable|numeric',
            'storeAddress.street' => 'required_without:storeAddress.line1',
            'storeAddress.number' => 'required_without:storeAddress.line1',
            'storeAddress.line1' => 'required_without_all:storeAddress.street,storeAddress.number',
            'storeAddress.city' => 'nullable',
            'storeAddress.country' => 'required',
            'storeAddress.latitude' => 'nullable|numeric',
            'storeAddress.longitude' => 'nullable|numeric',
            'dimensions.width' => 'required|numeric',
            'dimensions.height' => 'required|numeric',
            'dimensions.depth' => 'required|numeric',
            'weight' => 'required|numeric',
        ];
    }
}
