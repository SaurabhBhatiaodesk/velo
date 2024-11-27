<?php

namespace App\Http\Requests\Integrations\Woocommerce;

use App\Http\Requests\VeloStoreRequest;
use App\Models\Order;

class ImportRequest extends VeloStoreRequest
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
        // return (
        //   !!$this->user() &&
        //   $this->user()->can('create', [$this->modelClass, $this->input('storeAddress')['addressable_slug']])
        // );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return $this->getRules([
            'orders.*.customerAddress.phone' => 'required|numeric',
            'orders.*.dimensions.width' => 'required|numeric',
            'orders.*.dimensions.height' => 'required|numeric',
            'orders.*.dimensions.depth' => 'required|numeric',
            'orders.*.weight' => 'required|numeric',
            'orders.*.products' => 'required',
        ]);
    }
}
