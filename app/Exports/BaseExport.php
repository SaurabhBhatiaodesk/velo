<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Events\AfterSheet;

abstract class BaseExport implements WithStyles, WithEvents
{
    use Exportable;

    public $rtl = true;

    public function styles($sheet)
    {
        $spreadsheet = $sheet->getParent();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(11);

        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
            'fill' => [
                'fillType' => 'solid',
                'startColor' => ['argb' => 'FF0B60FF'],
            ],
        ]);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                if ($this->rtl) {
                    $sheet->setRightToLeft(true);
                }
                $sheet->freezePane('A2');
                $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

                // Auto size columns
                foreach (range('A', $sheet->getHighestColumn()) as $column) {
                    $sheet->getColumnDimension($column)->setAutoSize(true);
                    $width = ($sheet->getColumnDimension($column)->getWidth() * 2) / 3;
                    $sheet->getColumnDimension($column)->setWidth(round($width), 2);
                }
            }
        ];
    }
}
