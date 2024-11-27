<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\VeloReadRequest;

class LoadStoreDataRequest extends VeloReadRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return (
            $this->user() &&
            $this->user()->isElevated()
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return $this->getRules();
    }
}
