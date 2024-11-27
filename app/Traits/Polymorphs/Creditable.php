<?php

namespace App\Traits\Polymorphs;

use App\Models\CreditLine;

trait Creditable
{
    private function getCreditableKey()
    {
        return (isset($this->model_key) && strlen($this->model_key)) ? $this->model_key : 'id';
    }

    public function credit()
    {
        $modelKey = $this->getCreditableKey();
        return $this->morphOne(CreditLine::class, 'creditable', 'creditable_type', 'creditable_' . $modelKey, $modelKey);
    }

    public function credits()
    {
        $modelKey = $this->getCreditableKey();
        return $this->morphMany(CreditLine::class, 'creditable', 'creditable_type', 'creditable_' . $modelKey, $modelKey);
    }

    // protected static function bootSubscribable() {
    //   self::deleting(function ($model) {
    //     $model->credits()->delete();
    //   });
    // }
}
