<?php

namespace App\Http\Requests\Models\Users\Team;

use App\Http\Requests\VeloUpdateRequest;

class AcceptInviteRequest extends VeloUpdateRequest
{
    protected $modelParam = 'users';
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
            'email' => 'required|email',
            'token' => 'required',
        ]);
    }
}
