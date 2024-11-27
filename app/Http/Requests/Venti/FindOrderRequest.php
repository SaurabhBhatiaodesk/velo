<?php

namespace App\Http\Requests\Venti;

use Illuminate\Foundation\Http\FormRequest;

class FindOrderRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'orderName' => 'required',
            'phone' => 'required|numeric',
        ];
    }
}
