<?php

namespace App\Exports\Support;

use App\Exports\BaseExport;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CustomExport extends BaseExport implements FromCollection, WithTitle, WithHeadings
{
    use Exportable;

    protected $title;
    protected $orders;

    /**
     * @param array $data
     * @param string $title
     */
    public function __construct($data, $title = 'Velo Report')
    {
        $this->data = $data;
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return $this->title;
    }

    public function headings(): array
    {
        return array_keys($this->data[0]);
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return collect($this->data);
    }
}
