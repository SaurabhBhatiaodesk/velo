<?php

namespace App\Http\Requests\Models\Notes;

use App\Http\Requests\VeloUpdateRequest;

class UpdateRequest extends VeloUpdateRequest
{
    protected $modelParam = 'note';
}
