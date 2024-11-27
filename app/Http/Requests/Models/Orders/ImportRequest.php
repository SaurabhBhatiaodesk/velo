<?php

namespace App\Http\Requests\Models\Orders;

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
        return (
            !!$this->user() &&
            $this->user()->can('create', [$this->modelClass, $this->route()->parameter('store')->slug])
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
            'file' => 'nullable|base64mimetypes:' . implode(',', array_keys(config('excel.mime_to_ext'))) . '|base64max:4096'
        ]);
    }
}
