<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;

class ValidateOtpRequest extends ApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return !auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'email' => 'required|email:filter',
            'otp' => 'required|numeric|digits:6',
        ];
    }
}
