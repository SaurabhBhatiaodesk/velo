<?php

namespace App\Http\Requests\Integrations\EnterpriseApi;

use App\Http\Requests\VeloUpdateRequest;
use App\Models\Order;

class AcceptOrderRequest extends VeloUpdateRequest
{
    protected $modelParam = 'orders';
    protected $modelClass = Order::class;
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
            'order_id' => 'required',
        ]);
    }
}
