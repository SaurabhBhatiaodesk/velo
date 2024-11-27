<?php

namespace App\Http\Requests\Admin;

use App\Models\Store;
use App\Http\Requests\VeloUpdateRequest;

class UpdateStoreRequest extends VeloUpdateRequest
{
    protected $tableName = 'stores';
    protected $modelClass = Store::class;

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
        return $this->getRules([]);
    }
}
