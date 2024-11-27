<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;

class setLocaleRequest extends ApiRequest
{
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
        return [
            'localeId' => 'required|integer',
        ];
    }
}