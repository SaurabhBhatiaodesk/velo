<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;

class VerifyEmailRequest extends ApiRequest
{
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
            'email' => 'required|email|exists:users,email',
            'token' => 'string',
        ]);
    }
}
