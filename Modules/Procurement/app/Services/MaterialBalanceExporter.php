<?php

namespace Modules\Procurement\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class MaterialBalanceExporter
{
    private array $tones = ['F3C7FA', 'FFFFFF', 'FCE5D0', 'CFE8FA', 'CCF5F1'];

    public function spreadsheet(array $data): Spreadsheet
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Material Balance');
        $plants = $data['plants'];
        $months = $data['months'];
        $matrix = $data['matrix'];
        $material = $data['materials']->firstWhere('matnr', $data['selectedMaterial']);
        $lastIndex = 2 + ($months->count() * $plants->count() * 2);
        $last = Coordinate::stringFromColumnIndex($lastIndex);

        $sheet->mergeCells("A1:{$last}1")->setCellValue('A1', 'Material Balance Summary - '.($material?->maktx ?: $data['selectedMaterial']));
        $sheet->setCellValue('A4', $material?->category_name ?: 'Material Category')->setCellValue('B4', $data['selectedMaterial']);
        $column = 3;
        foreach ($months as $month) {
            $start = $column;
            foreach ($plants as $i => $plant) {
                $qty = Coordinate::stringFromColumnIndex($column);
                $remarks = Coordinate::stringFromColumnIndex($column + 1);
                $sheet->setCellValue("{$qty}4", $plant->name)->setCellValue("{$remarks}4", 'Remarks');
                $sheet->getStyle("{$qty}4:{$remarks}40")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($this->tones[$i % 5]);
                $column += 2;
            }
            $from = Coordinate::stringFromColumnIndex($start);
            $to = Coordinate::stringFromColumnIndex($column - 1);
            $sheet->mergeCells("{$from}3:{$to}3")->setCellValue("{$from}3", $month->format('M-y'));
        }

        $sheet->setCellValue('A5', 'O/s')->setCellValue('B5', now()->format('d.m.Y'));
        $sheet->setCellValue('A6', 'Cover for No. Of Days');
        $sheet->setCellValue('A7', 'To Buy from RIL / Chemplast / DCW / Local');
        $sheet->setCellValue('B7', 'RIL - AT Dadri, DCW / CP at Tumkur / Raipur');
        $receiptVendors = collect($matrix)->flatten(1)
            ->flatMap(fn ($cell) => $cell['orders'])
            ->map(fn ($order) => $order->vendor_name ?: $order->vendor_code)
            ->filter()->unique()->sort()->values();
        $maxOrders = max(1, $receiptVendors->count());
        $orderStart = 8;
        $totalsStart = $orderStart + $maxOrders;
        for ($i = 0; $i < $maxOrders; $i++) {
            $sheet->setCellValue('A'.($orderStart + $i), $i === 0 ? 'Supplier receipts' : '');
            $sheet->setCellValue('B'.($orderStart + $i), $receiptVendors->get($i) ?: 'No supplier receipt');
        }
        $labels = ['Buying / Imports', 'Total Buying', 'Total Availability', 'Expected Average Consp/day', 'Working Days', 'Total Expected Consp (MT)', 'Gross Shortage Before POs (MT)', 'Closing Stock', 'Remaining Shortage (MT)'];
        foreach ($labels as $offset => $label) $sheet->setCellValue('A'.($totalsStart + $offset), $label);

        $column = 3;
        foreach ($months as $month) foreach ($plants as $plant) {
            $cell = $matrix[$month->format('Y-m')][$plant->id];
            $qty = Coordinate::stringFromColumnIndex($column);
            $remarks = Coordinate::stringFromColumnIndex($column + 1);
            $sheet->setCellValue("{$qty}5", $cell['opening'])->setCellValue("{$qty}6", $cell['cover_days'])->setCellValue("{$qty}7", $cell['to_buy']);
            foreach ($receiptVendors as $index => $vendorName) {
                $vendorOrders = $cell['orders']->filter(fn ($order) => ($order->vendor_name ?: $order->vendor_code) === $vendorName)->values();
                if ($vendorOrders->isEmpty()) continue;
                $row = $orderStart + $index;
                $sheet->setCellValue("{$qty}{$row}", (float) $vendorOrders->sum('mt_quantity'));
                $details = $vendorOrders->map(function ($order) {
                    $deliveryDate = $order->delivery_date ? \Illuminate\Support\Carbon::parse($order->delivery_date)->format('d-M-Y') : '';
                    $reference = $order->purchase_order ? "PO {$order->purchase_order}".($order->purchase_order_item ? "/{$order->purchase_order_item}" : '') : 'No PO reference';
                    $stage = $order->delivery_status_label ?: 'Planned';
                    return "{$order->source} · {$reference}\nStage: {$stage}\nETA {$deliveryDate}";
                })->implode("\n\n");
                $sheet->setCellValue("{$remarks}{$row}", $details);
            }
            $values = [$cell['sap_buying'], $cell['total_buying'], $cell['availability'], $cell['daily_consumption'], $cell['working_days'], $cell['expected_consumption'], $cell['gross_shortage'], $cell['closing'], $cell['extra_required']];
            foreach ($values as $offset => $value) $sheet->setCellValue("{$qty}".($totalsStart + $offset), $value);
            $sheet->setCellValue("{$remarks}{$totalsStart}", $cell['local_buying'] > 0 ? 'Includes local buying '.$cell['local_buying'].' MT' : '');
            $column += 2;
        }

        $endRow = $totalsStart + count($labels) - 1;
        $sheet->getStyle("A1:{$last}{$endRow}")->getFont()->setName('Arial')->setSize(9);
        $sheet->getStyle("A3:{$last}4")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle("A3:{$last}3")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
        $sheet->getStyle("A3:{$last}{$endRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('111827');
        $sheet->getStyle("C5:{$last}{$endRow}")->getNumberFormat()->setFormatCode('#,##0.000');
        $sheet->getStyle("A{$totalsStart}:{$last}".($totalsStart + 2))->getFont()->setBold(true);
        $sheet->getStyle("A".($totalsStart + 6).":{$last}".($totalsStart + 6))->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("A".($totalsStart + 6).":B".($totalsStart + 6))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('009F5B');
        $sheet->getStyle("A".($totalsStart + 6).":B".($totalsStart + 6))->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A".($totalsStart + 6).":{$last}".($totalsStart + 6))->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
        $sheet->getStyle("A".($totalsStart + 6).":{$last}".($totalsStart + 6))->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);
        $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A1:{$last}1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("A4:B4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF600');
        $sheet->getStyle("A4:B4")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$last}{$endRow}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getColumnDimension('A')->setWidth(34);
        $sheet->getColumnDimension('B')->setWidth(32);
        for ($i = 3; $i <= $lastIndex; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth($i % 2 ? 15 : 38);
        }
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(2)->setRowHeight(9);
        $sheet->getRowDimension(3)->setRowHeight(24);
        $sheet->getRowDimension(4)->setRowHeight(32);
        for ($row = 5; $row < $orderStart; $row++) $sheet->getRowDimension($row)->setRowHeight(24);
        foreach ($receiptVendors as $index => $vendorName) {
            $largestOrderGroup = collect($matrix)->flatten(1)->max(fn ($cell) => $cell['orders']
                ->filter(fn ($order) => ($order->vendor_name ?: $order->vendor_code) === $vendorName)->count());
            $sheet->getRowDimension($orderStart + $index)->setRowHeight(min(150, max(58, 18 + ((int) $largestOrderGroup * 48))));
        }
        if ($receiptVendors->isEmpty()) $sheet->getRowDimension($orderStart)->setRowHeight(28);
        for ($row = $totalsStart; $row <= $endRow; $row++) $sheet->getRowDimension($row)->setRowHeight(24);
        $sheet->getStyle("C5:{$last}{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        for ($i = 4; $i <= $lastIndex; $i += 2) {
            $remarksColumn = Coordinate::stringFromColumnIndex($i);
            $sheet->getStyle("{$remarksColumn}5:{$remarksColumn}{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
        $sheet->freezePane('C5'); $sheet->setShowGridlines(false);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.35)->setRight(0.25)->setBottom(0.35)->setLeft(0.25)->setHeader(0.15)->setFooter(0.15);

        $mouStart = $endRow + 2;
        $mouLastIndex = $months->count() + 2;
        $mouLast = Coordinate::stringFromColumnIndex($mouLastIndex);
        $sheet->mergeCells("A{$mouStart}:{$mouLast}{$mouStart}")->setCellValue("A{$mouStart}", 'MOU Commitments by Planning Month');
        $headerRow = $mouStart + 1;
        $sheet->setCellValue("A{$headerRow}", 'Supplier');
        foreach ($months as $index => $month) $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 2).$headerRow, $month->format('M-y'));
        $sheet->setCellValue("{$mouLast}{$headerRow}", 'Horizon Total');
        $mouRow = $mouStart + 2;
        foreach ($data['mouPlanning']->pluck('vendor_name')->unique()->values() as $vendorName) {
            $sheet->setCellValue("A{$mouRow}", $vendorName);
            foreach ($months as $index => $month) $sheet->setCellValue(
                Coordinate::stringFromColumnIndex($index + 2).$mouRow,
                $data['mouPlanning']->where('vendor_name', $vendorName)->where('month_key', $month->format('Y-m'))->sum('quantity_mt')
            );
            $sheet->setCellValue("{$mouLast}{$mouRow}", $data['mouPlanning']->where('vendor_name', $vendorName)->sum('quantity_mt'));
            $mouRow++;
        }
        if ($data['mouPlanning']->isEmpty()) $sheet->setCellValue("A{$mouRow}", 'No MOU vendor plan available');
        if ($data['mouPlanning']->isEmpty()) $mouRow++;
        $sheet->setCellValue("A{$mouRow}", 'Total Qty.');
        foreach ($months as $index => $month) $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 2).$mouRow, $data['mouPlanning']->where('month_key', $month->format('Y-m'))->sum('quantity_mt'));
        $sheet->setCellValue("{$mouLast}{$mouRow}", $data['mouPlanning']->sum('quantity_mt'));
        $sheet->getStyle("A{$mouStart}:{$mouLast}{$mouRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('111827');
        $sheet->getStyle("A{$mouStart}:{$mouLast}{$headerRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$mouStart}:{$mouLast}{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$headerRow}:{$mouLast}{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('ECFEFF');
        $sheet->getStyle("A{$mouRow}:{$mouLast}{$mouRow}")->getFont()->setBold(true);

        $raw = $book->createSheet(); $raw->setTitle('Import Data');
        $raw->fromArray([['Material Code','Month','Plant Code','Plant Name','Opening Stock','Stock Cover Days','Quantity To Buy','SAP Buying','Local Buying','Total Buying','Total Availability','Daily Consumption','Working Days','Expected Consumption','Closing Stock']]);
        $row = 2;
        foreach ($months as $month) foreach ($plants as $plant) {
            $cell = $matrix[$month->format('Y-m')][$plant->id];
            $raw->fromArray([[$data['selectedMaterial'],$month->format('Y-m'),$plant->company_code,$plant->name,$cell['opening'],$cell['cover_days'],$cell['to_buy'],$cell['sap_buying'],$cell['local_buying'],$cell['total_buying'],$cell['availability'],$cell['daily_consumption'],$cell['working_days'],$cell['expected_consumption'],$cell['closing']]], null, 'A'.$row++);
        }
        $raw->getStyle('A1:O1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $raw->getStyle('A1:O1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('102A43');
        foreach (range('A','O') as $letter) $raw->getColumnDimension($letter)->setAutoSize(true);
        $book->setActiveSheetIndex(0);
        return $book;
    }
}
