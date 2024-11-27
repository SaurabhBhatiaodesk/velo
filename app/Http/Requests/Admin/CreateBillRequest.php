<?php

namespace App\Http\Requests\Admin;

use App\Models\Bill;
use App\Http\Requests\VeloStoreRequest;

class CreateBillRequest extends VeloStoreRequest
{
    protected $tableName = 'bills';
    protected $modelClass = Bill::class;

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
