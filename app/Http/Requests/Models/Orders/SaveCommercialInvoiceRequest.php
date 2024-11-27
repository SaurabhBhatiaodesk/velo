<?php

namespace App\Http\Requests\Models\Orders;

use App\Http\Requests\ApiRequest;

class SaveCommercialInvoiceRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'invoice' => 'nullable|base64mimetypes:application/pdf|base64max:4096',
            'declared_value' => 'required|numeric',
            'tax_id' => 'required',
        ];
    }
}
