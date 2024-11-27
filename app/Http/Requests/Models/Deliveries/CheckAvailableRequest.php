<?php

namespace App\Http\Requests\Models\Deliveries;

use App\Http\Requests\VeloStoreRequest;
use App\Models\Order;

class CheckAvailableRequest extends VeloStoreRequest
{
    protected $tableName = 'deliveries';
    protected $modelClass = Delivery::class;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return !!$this->user();
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
            'customerAddress.latitude' => 'required|numeric',
            'customerAddress.longitude' => 'required|numeric',
            'customerAddress.phone' => 'required|numeric',
            'storeAddress.latitude' => 'required|numeric',
            'storeAddress.longitude' => 'required|numeric',
            'dimensions.width' => 'required|numeric',
            'dimensions.height' => 'required|numeric',
            'dimensions.depth' => 'required|numeric',
            'weight' => 'required|numeric',
        ]);
    }
}
