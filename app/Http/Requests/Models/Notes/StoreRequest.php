<?php

namespace App\Http\Requests\Models\Notes;

use App\Models\Note;
use App\Http\Requests\VeloStoreRequest;

class StoreRequest extends VeloStoreRequest
{
    protected $tableName = 'notes';
    protected $modelClass = Note::class;
}
