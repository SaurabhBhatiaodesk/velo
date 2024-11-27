<?php

namespace App\Http\Requests\Admin;

use App\Models\Transaction;
use App\Http\Requests\VeloStoreRequest;

class CreateTransactionRequest extends VeloStoreRequest
{
    protected $tableName = 'transactions';
    protected $modelClass = Transaction::class;

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
                $this->user()->hasRole('admin') ||
                $this->user()->hasRole('super_admin')
            )
        );
    }
}
