<?php

namespace App\Http\Requests\Admin;

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
            $this->user() &&
            $this->user()->isElevated()
        );
    }
}
