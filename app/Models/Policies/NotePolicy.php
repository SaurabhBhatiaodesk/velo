<?php

namespace App\Models\Policies;

use App\Policies\PolymorphPolicy;

class NotePolicy extends PolymorphPolicy
{
    public $modelName = 'notes';
    public $polymorphicName = 'notable';
}
