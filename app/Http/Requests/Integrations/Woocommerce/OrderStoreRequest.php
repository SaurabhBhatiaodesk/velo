<?php

namespace App\Http\Requests\Integrations\Woocommerce;

use App\Http\Requests\VeloStoreRequest;
use App\Models\Order;

class OrderStoreRequest extends VeloStoreRequest
{
    protected $tableName = 'orders';
    protected $modelClass = Order::class;
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
        return $this->getRules([
            'customerAddress.phone' => 'required',
            'customerAddress.line1' => 'required',
            'customerAddress.city' => 'required',
            'customerAddress.country' => 'required',
            'dimensions.width' => 'required|numeric',
            'dimensions.height' => 'required|numeric',
            'dimensions.depth' => 'required|numeric',
            'weight' => 'required|numeric',
            'products' => 'required',
        ]);
    }
}
