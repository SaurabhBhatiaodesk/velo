<?php

namespace App\Http\Requests;

class VeloStoreRequest extends ApiRequest
{
    protected $tableName;
    protected $modelClass;

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
                $this->user()->can('create', [$this->modelClass, $this->input('store_slug')])
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
        $model = new $this->modelClass();
        return $this->getRules($model->rules());
    }
}
