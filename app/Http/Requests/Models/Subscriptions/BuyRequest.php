<?php

namespace App\Http\Requests\Models\Subscriptions;

use App\Http\Requests\ApiRequest;

class BuyRequest extends ApiRequest
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
        return $this->getRules([
            'subscribable_type' => 'required',
            'subscribable_id' => 'required',
        ]);
    }
}
