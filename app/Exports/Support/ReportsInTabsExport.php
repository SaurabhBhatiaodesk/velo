<?php

namespace App\Exports\Support;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\Exportable;

class ReportsInTabsExport implements WithMultipleSheets
{
    use Exportable;

    private $reports;

    /**
     * @param $reports
     */
    public function __construct($reports)
    {
        $this->reports = $reports;
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        return $this->reports;
    }
}
