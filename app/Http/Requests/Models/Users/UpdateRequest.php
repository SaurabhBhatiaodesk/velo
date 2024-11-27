<?php

namespace App\Http\Requests\Models\Users;

use App\Http\Requests\ApiRequest;

class UpdateRequest extends ApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return (
            !!$this->user() &&
            !is_null($this->user()->email_verified_at) &&
            $this->user()->id === $this->route()->parameter('user')->id
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return $this->getRules($this->user()->rules());
    }
}
