<?php

namespace App\Traits\Polymorphs;

use App\Models\Bill;

trait Billable
{
    private function getBillableKey()
    {
        return (isset($this->model_key) && strlen($this->model_key)) ? $this->model_key : 'id';
    }

    public function bill()
    {
        $modelKey = $this->getBillableKey();
        return $this->morphOne(Bill::class, 'billable', 'billable_type', 'billable_' . $modelKey, $modelKey);
    }

    public function bills()
    {
        $modelKey = $this->getBillableKey();
        return $this->morphMany(Bill::class, 'billable', 'billable_type', 'billable_' . $modelKey, $modelKey);
    }
}
