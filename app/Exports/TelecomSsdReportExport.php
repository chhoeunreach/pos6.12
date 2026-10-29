<?php

namespace App\Exports;

use App\Services\Reports\TelecomSsdReport;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TelecomSsdReportExport extends DefaultValueBinder implements FromQuery, WithHeadings, WithMapping, WithCustomValueBinder, WithColumnFormatting, WithColumnWidths, WithStyles
{
    private $rowNumber = 0;

    public function __construct(private Builder $rows, private TelecomSsdReport $report)
    {
    }

    public function query()
    {
        return clone $this->rows;
    }

    public function headings(): array
    {
        return [TelecomSsdReport::KHMER_HEADINGS, TelecomSsdReport::HEADINGS];
    }

    public function map($row): array
    {
        return $this->report->dataRow($row, ++$this->rowNumber);
    }

    public function bindValue(Cell $cell, $value)
    {
        $numeric = in_array($cell->getColumn(), ['A', 'H', 'I', 'J'], true) && is_numeric($value);
        $cell->setValueExplicit($numeric ? (float) $value : (string) ($value ?? ''), $numeric ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);

        return true;
    }

    public function columnFormats(): array
    {
        return ['H' => '#,##0.########', 'I' => '#,##0.00', 'J' => '#,##0.00'];
    }

    public function columnWidths(): array
    {
        return TelecomSsdReport::EXPORT_COLUMN_WIDTHS;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = max(2, $sheet->getHighestRow());
        $sheet->getStyle('A1:M' . $lastRow)->applyFromArray([
            'font' => ['name' => 'Times New Roman', 'size' => 11, 'color' => ['rgb' => '000000']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_DOTTED, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getStyle('A1:M2')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN], 'outline' => ['borderStyle' => Border::BORDER_MEDIUM]],
        ]);
        $sheet->getStyle('A1:M1')->getFont()->setName('Khmer OS Battambang')->setSize(12);
        $sheet->getStyle('A2:M2')->getFont()->setName('Times New Roman')->setSize(14);
        $sheet->getRowDimension(1)->setRowHeight(34);
        $sheet->getRowDimension(2)->setRowHeight(44);
        if ($lastRow > 2) {
            $sheet->getStyle('A3:A' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C3:C' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('H3:J' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $sheet->freezePane('A3');
        $sheet->setShowGridlines(false);
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(1, 2)->setPrintArea('A1:M' . $lastRow);
        $sheet->getPageMargins()->setTop(0.3)->setBottom(0.3)->setLeft(0.3)->setRight(0.3);
    }
}
