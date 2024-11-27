<?php

namespace App\Traits\Polymorphs;

use App\Models\Note;

trait Notable
{
    private function getNotableKey()
    {
        return (isset($this->model_key) && strlen($this->model_key)) ? $this->model_key : 'id';
    }

    public function note()
    {
        $modelKey = $this->getNotableKey();
        return $this->morphOne(Note::class, 'notable', 'notable_type', 'notable_' . $modelKey, $modelKey);
    }

    public function notes()
    {
        $modelKey = $this->getNotableKey();
        return $this->morphMany(Note::class, 'notable', 'notable_type', 'notable_' . $modelKey, $modelKey);
    }

    protected static function bootNotable()
    {
        self::deleting(function ($model) {
            $model->notes()->delete();
        });
    }
}
