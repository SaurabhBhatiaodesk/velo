<?php

namespace App\Http\Requests\Models\Stores;

use App\Http\Requests\VeloReadRequest;

class ReadRequest extends VeloReadRequest
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
}
