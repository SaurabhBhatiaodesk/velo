<?php

namespace App\Exports\Support;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\Exportable;
use App\Models\Store;
use Carbon\Carbon;


class CustomTabsExport implements WithMultipleSheets {
    use Exportable;

    private $data;

    /**
     * @param Carbon $startDate
     * @param Carbon $endDate
     */
    public function __construct($data) {
        $this->data = $data;
    }

    /**
     * @return array
    */
    public function sheets(): array {
        $report = [];
        foreach ($this->data as $tabTitle => $tabData) {
            $report[] = new CustomExport($tabData, $tabTitle);
        }
        return $report;
    }
}
