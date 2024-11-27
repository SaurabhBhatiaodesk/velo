<?php

namespace App\Http\Requests\Integrations\EnterpriseApi;

use App\Http\Requests\VeloReadRequest;

class BarcodeRequest extends VeloReadRequest
{
    protected $modelParam = 'orders';
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return !!$this->user();
    }
}
