<?php

namespace App\Http\Requests\Models\Notes;

use App\Http\Requests\VeloDeleteRequest;

class DeleteRequest extends VeloDeleteRequest
{
    protected $modelParam = 'notes';
}
