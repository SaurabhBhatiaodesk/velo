<?php

namespace App\Http\Requests\Models\Users;

use App\Http\Requests\VeloUpdateRequest;

class UpdatePasswordRequest extends VeloUpdateRequest
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
            (
                $this->user()->isElevated() ||
                $this->route()->parameter('store')->user_id === $this->user()->id ||
                $this->route()->parameter('store')->users()->where('user_id', $this->user()->id)->exists()
            )
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return $this->getRules([
            'currentPassword' => 'required|string|min:8|max:255',
            'newPassword' => 'required|string|min:8|max:255',
        ]);
    }
}
