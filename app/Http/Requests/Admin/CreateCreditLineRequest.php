<?php

namespace App\Http\Requests\Admin;

use App\Models\CreditLine;
use App\Http\Requests\VeloStoreRequest;

class CreateCreditLineRequest extends VeloStoreRequest
{
    protected $tableName = 'credit_lines';
    protected $modelClass = CreditLine::class;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return (
            $this->user() &&
            (
                $this->user()->hasRole('support') ||
                $this->user()->hasRole('admin') ||
                $this->user()->hasRole('super_admin')
            )
        );
    }
}
