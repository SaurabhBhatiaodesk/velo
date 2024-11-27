<?php

namespace App\Http\Requests;

class VeloUpdateRequest extends ApiRequest
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
                $this->user()->can('update', $this->getRouteModel())
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
        return $this->getRules($this->getRouteModel()->rules());
    }
}
