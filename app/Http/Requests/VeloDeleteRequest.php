<?php

namespace App\Http\Requests;

class VeloDeleteRequest extends ApiRequest
{
    protected $modelParam;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return (
            !!$this->user() &&
            (
                $this->isElevatedUser() ||
                $this->user()->can('delete', $this->getRouteModel())
            )
        );
    }
}
