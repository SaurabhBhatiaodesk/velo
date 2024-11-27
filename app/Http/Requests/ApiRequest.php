<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ApiRequest extends FormRequest
{
    /**
     * Check if the logged user has elevated privileges
     *
     * @return boolean
     */
    public function isElevatedUser()
    {
        return $this->user()->isElevated();
    }

    /**
     * Return the correct name for the permission
     * This is a redundancy fallback
     *
     * @return string
     */
    public function getRouteModelName()
    {
        return Str::plural($this->modelParam);
    }
    /**
     * Find the route model
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getRouteModel()
    {
        $model = $this->route()->parameter(Str::plural($this->modelParam));
        if (!$model) {
            $model = $this->route()->parameter(Str::singular($this->modelParam));
        }
        return $model;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return (!!$this->user() && !is_null($this->user()->email_verified_at));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function getRules($rules = [])
    {
        if (
            config('google.project_id') &&
            strlen(config('google.project_id')) &&
            config('google.account_filename') &&
            strlen(config('google.account_filename'))
        ) {
            $rules['captcha'] = 'required';
        }

        return $rules;
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
