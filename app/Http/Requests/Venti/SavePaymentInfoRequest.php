<?php

namespace App\Http\Requests\Venti;

use Illuminate\Foundation\Http\FormRequest;

class SavePaymentInfoRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'email' => 'required|email',
            'expiry' => 'required|numeric',
            'holder_name' => 'required',
            'phone' => 'required|numeric',
            'social_id' => 'nullable|numeric',
            'token' => 'required',
            'transaction_data' => 'required'
        ];
    }
}
