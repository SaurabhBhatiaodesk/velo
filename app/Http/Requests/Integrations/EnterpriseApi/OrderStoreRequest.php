<?php

namespace App\Http\Requests\Integrations\EnterpriseApi;

use App\Http\Requests\ApiRequest;

class OrderStoreRequest extends ApiRequest
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
        return $this->getRules([
            'customerAddress.phone' => 'required',
            'customerAddress.street' => 'required_without:customerAddress.line1',
            'customerAddress.number' => 'required_without:customerAddress.line1',
            'customerAddress.line1' => 'required_without_all:customerAddress.street,customerAddress.number',
            'customerAddress.city' => 'required',
            'customerAddress.country' => 'required',
            'dimensions.width' => 'required|numeric',
            'dimensions.height' => 'required|numeric',
            'dimensions.depth' => 'required|numeric',
            'weight' => 'required|numeric',
            'products' => 'array',
        ]);
    }
}
