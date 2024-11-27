<?php

namespace App\Http\Requests\Models\Orders;

use App\Http\Requests\VeloStoreRequest;
use App\Models\Order;

class StoreRequest extends VeloStoreRequest
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
        return (
            !!$this->user() &&
            $this->user()->can('create', [$this->modelClass, $this->input('storeAddress')['addressable_slug']])
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return $this->getRules([
            'polygon_id' => 'required',
            'customerAddress.street' => 'required_if:customerAddress.id,null',
            'customerAddress.number' => 'required_if:customerAddress.id,null',
            'customerAddress.city' => 'required_if:customerAddress.id,null',
            'customerAddress.country' => 'required_if:customerAddress.id,null',
            'customerAddress.phone' => 'required_if:customerAddress.id,null|numeric',
            'storeAddress.street' => 'required_if:storeAddress.id,null',
            'storeAddress.number' => 'required_if:storeAddress.id,null',
            'storeAddress.city' => 'required_if:storeAddress.id,null',
            'storeAddress.country' => 'required_if:storeAddress.id,null',
            'storeAddress.latitude' => 'required_if:storeAddress.id,null|numeric',
            'storeAddress.longitude' => 'required_if:storeAddress.id,null|numeric',
            'dimensions.width' => 'required|numeric',
            'dimensions.height' => 'required|numeric',
            'dimensions.depth' => 'required|numeric',
            'weight' => 'required|numeric',
            'products' => 'required',
            'declared_value' => 'nullable|numeric',
            'invoice' => 'nullable|base64mimetypes:application/pdf|base64max:4096'
        ]);
    }
}
