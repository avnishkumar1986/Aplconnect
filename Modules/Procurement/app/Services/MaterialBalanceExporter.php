<?php

namespace Modules\Procurement\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

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

        $sheet->mergeCells("A1:{$last}1")->setCellValue('A1', 'APL CONNECT  |  MATERIAL BALANCE SUMMARY');
        $sheet->mergeCells("A2:{$last}2")->setCellValue('A2', ($material?->maktx ?: $data['selectedMaterial']).'  ·  '.$data['selectedMaterial'].'  ·  Generated '.now()->format('d.m.Y, h:i A'));
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
            $sheet->mergeCells("{$from}3:{$to}3")->setCellValue("{$from}3", $month->format('F Y'));
        }

        $sheet->setCellValue('A5', 'O/s')->setCellValue('B5', now()->format('d.m.Y'));
        $sheet->setCellValue('A6', 'Cover for No. Of Days');
        $sheet->setCellValue('A7', 'To Buy from RIL / Chemplast / DCW / Local');
        $sheet->setCellValue('B7', 'RIL - AT Dadri, DCW / CP at Tumkur / Raipur');
        $orderKey = fn ($order) => implode('|', [$order->source, $order->vendor_name ?: $order->vendor_code,
            $order->purchase_order, $order->purchase_order_item, $order->delivery_date, $order->mt_quantity]);
        $receiptOrders = collect($matrix)->flatten(1)
            ->flatMap(fn ($cell) => $cell['orders'])
            ->sortBy(fn ($order) => sprintf('%s|%s|%s', $order->vendor_name ?: $order->vendor_code,
                $order->delivery_date, $order->purchase_order))->values();
        $maxOrders = max(1, $receiptOrders->count());
        $orderStart = 8;
        $totalsStart = $orderStart + $maxOrders;
        for ($i = 0; $i < $maxOrders; $i++) {
            $sheet->setCellValue('A'.($orderStart + $i), $i === 0 ? 'Supplier receipts' : '');
            $receiptOrder = $receiptOrders->get($i);
            $sheet->setCellValue('B'.($orderStart + $i), $receiptOrder
                ? ($receiptOrder->vendor_name ?: $receiptOrder->vendor_code) : 'No supplier receipt');
        }
        $labels = ['Buying / Imports', 'Total Buying', 'Total Availability', 'Expected Average Consp/day', 'Days', 'Total Expected Consp (MT)', 'Gross Shortage Before POs (MT)', 'Closing Stock', 'Remaining Shortage (MT)'];
        foreach ($labels as $offset => $label) $sheet->setCellValue('A'.($totalsStart + $offset), $label);

        $column = 3;
        foreach ($months as $month) foreach ($plants as $plant) {
            $cell = $matrix[$month->format('Y-m')][$plant->id];
            $qty = Coordinate::stringFromColumnIndex($column);
            $remarks = Coordinate::stringFromColumnIndex($column + 1);
            $sheet->setCellValue("{$qty}5", $cell['opening'])->setCellValue("{$qty}6", $cell['cover_days'])->setCellValue("{$qty}7", $cell['to_buy']);
            $sheet->setCellValue("{$remarks}5", $cell['remarks']['opening'] ?? '');
            $sheet->setCellValue("{$remarks}6", $cell['remarks']['cover_days'] ?? '');
            $sheet->setCellValue("{$remarks}7", $cell['remarks']['to_buy'] ?? '');
            $sheet->getStyle("{$qty}6")->getNumberFormat()->setFormatCode('#,##0.0 "   (days)"');
            foreach ($receiptOrders as $index => $receiptOrder) {
                $cellOrder = $cell['orders']->first(fn ($order) => $orderKey($order) === $orderKey($receiptOrder));
                if (! $cellOrder) continue;
                $row = $orderStart + $index;
                $sheet->setCellValue("{$qty}{$row}", (float) $cellOrder->mt_quantity);
                $deliveryDate = $cellOrder->delivery_date ? \Illuminate\Support\Carbon::parse($cellOrder->delivery_date)->format('d.m.Y') : '';
                $reference = $cellOrder->purchase_order ? "PO {$cellOrder->purchase_order}".($cellOrder->purchase_order_item ? "/{$cellOrder->purchase_order_item}" : '') : 'No PO reference';
                $stage = $cellOrder->delivery_status_label ?: 'Planned';
                $remarkKey = 'receipt:'.sha1($orderKey($receiptOrder));
                $userRemark = $cell['remarks'][$remarkKey] ?? null;
                $details = "{$cellOrder->source} · {$reference}\nStage: {$stage}\nETA {$deliveryDate}";
                if ($userRemark) $details .= "\nRemarks: {$userRemark}";
                $sheet->setCellValue("{$remarks}{$row}", $details);
            }
            $values = [
                'sap_buying' => $cell['sap_buying'], 'total_buying' => $cell['total_buying'],
                'availability' => $cell['availability'], 'daily_consumption' => $cell['daily_consumption'],
                'working_days' => $cell['working_days'], 'expected_consumption' => $cell['expected_consumption'],
                'gross_shortage' => $cell['gross_shortage'], 'closing' => $cell['closing'],
                'extra_required' => $cell['extra_required'],
            ];
            foreach ($values as $metricKey => $value) {
                $offset = array_search($metricKey, array_keys($values), true);
                $valueCell = "{$qty}".($totalsStart + $offset);
                $sheet->setCellValue($valueCell, $value);
                $sheet->setCellValue("{$remarks}".($totalsStart + $offset), $cell['remarks'][$metricKey] ?? '');
                if (is_numeric($value) && (float) $value < 0) {
                    $sheet->getStyle($valueCell)->getFont()->setBold(true)->getColor()->setRGB('DC2626');
                    $sheet->getStyle($valueCell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF1F2');
                }
            }
            if ($cell['local_buying'] > 0) {
                $existingRemark = (string) $sheet->getCell("{$remarks}{$totalsStart}")->getValue();
                $localRemark = 'Includes local buying '.$cell['local_buying'].' MTN';
                $sheet->setCellValue("{$remarks}{$totalsStart}", $existingRemark ? $existingRemark."\n".$localRemark : $localRemark);
            }
            $column += 2;
        }

        $endRow = $totalsStart + count($labels) - 1;
        $sheet->getStyle("A1:{$last}{$endRow}")->getFont()->setName('Arial')->setSize(9);
        $sheet->getStyle("A3:{$last}4")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle("A3:{$last}4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2B1828');
        $sheet->getStyle("A3:{$last}4")->getFont()->setBold(true)->getColor()->setRGB('FFF7FC');
        $sheet->getStyle("A3:{$last}{$endRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('111827');
        $sheet->getStyle("C5:{$last}{$endRow}")->getNumberFormat()->setFormatCode('#,##0.000');
        for ($i = 3; $i <= $lastIndex; $i += 2) {
            $quantityColumn = Coordinate::stringFromColumnIndex($i);
            $sheet->getStyle("{$quantityColumn}5")->getNumberFormat()->setFormatCode('#,##0.000 "   (mtn)"');
            $sheet->getStyle("{$quantityColumn}6")->getNumberFormat()->setFormatCode('#,##0.0 "   (days)"');
            $sheet->getStyle("{$quantityColumn}7")->getNumberFormat()->setFormatCode('#,##0.000 "   (mtn)"');
            $sheet->getStyle("{$quantityColumn}{$orderStart}:{$quantityColumn}".($orderStart + $maxOrders - 1))->getNumberFormat()->setFormatCode('#,##0.000 "   (mtn)"');
            foreach (range(0, count($labels) - 1) as $offset) {
                $format = $offset === 3 ? '#,##0.0000 "   (mtn/day)"' : ($offset === 4 ? '0 "   (days)"' : '#,##0.000 "   (mtn)"');
                $sheet->getStyle("{$quantityColumn}".($totalsStart + $offset))->getNumberFormat()->setFormatCode($format);
            }
        }
        $sheet->getStyle("A{$totalsStart}:{$last}".($totalsStart + 2))->getFont()->setBold(true);
        $sheet->getStyle("A".($totalsStart + 6).":{$last}".($totalsStart + 6))->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("A".($totalsStart + 6).":B".($totalsStart + 6))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('009F5B');
        $sheet->getStyle("A".($totalsStart + 6).":B".($totalsStart + 6))->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A".($totalsStart + 6).":{$last}".($totalsStart + 6))->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
        $sheet->getStyle("A".($totalsStart + 6).":{$last}".($totalsStart + 6))->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);
        $sheet->getStyle("A1:{$last}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('102A4C');
        $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A1:{$last}1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A2:{$last}2")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EAF7F9');
        $sheet->getStyle("A2:{$last}2")->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('31516B');
        $sheet->getStyle("A4:B4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('3A2236');
        $sheet->getStyle("A4:B4")->getFont()->setBold(true)->getColor()->setRGB('FFF7FC');
        $sheet->getStyle("A1:{$last}{$endRow}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getColumnDimension('A')->setWidth(34);
        $sheet->getColumnDimension('B')->setWidth(32);
        for ($i = 3; $i <= $lastIndex; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth($i % 2 ? 15 : 38);
        }
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(2)->setRowHeight(22);
        $sheet->getRowDimension(3)->setRowHeight(24);
        $sheet->getRowDimension(4)->setRowHeight(32);
        for ($row = 5; $row < $orderStart; $row++) $sheet->getRowDimension($row)->setRowHeight(24);
        foreach ($receiptOrders as $index => $receiptOrder) {
            $sheet->getRowDimension($orderStart + $index)->setRowHeight(58);
        }
        if ($receiptOrders->isEmpty()) $sheet->getRowDimension($orderStart)->setRowHeight(28);
        for ($row = $totalsStart; $row <= $endRow; $row++) $sheet->getRowDimension($row)->setRowHeight(24);
        $sheet->getStyle("C5:{$last}{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        for ($i = 4; $i <= $lastIndex; $i += 2) {
            $remarksColumn = Coordinate::stringFromColumnIndex($i);
            $sheet->getStyle("{$remarksColumn}5:{$remarksColumn}{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
        $sheet->freezePane('C5'); $sheet->setShowGridlines(false);
        $sheet->getPageSetup()
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(1, 4)
            ->setHorizontalCentered(true);
        $sheet->getPageMargins()->setTop(0.35)->setRight(0.25)->setBottom(0.35)->setLeft(0.25)->setHeader(0.15)->setFooter(0.15);
        $sheet->getHeaderFooter()->setOddFooter('&LAPL Connect  |  Confidential&CMaterial Balance&RPage &P of &N');

        $mouStart = $endRow + 2;
        $mouLastIndex = $months->count() + 2;
        $mouLast = Coordinate::stringFromColumnIndex($mouLastIndex);
        $sheet->mergeCells("A{$mouStart}:{$mouLast}{$mouStart}")->setCellValue("A{$mouStart}", 'MOU Commitments by Planning Month');
        $headerRow = $mouStart + 1;
        $sheet->setCellValue("A{$headerRow}", 'Supplier');
        foreach ($months as $index => $month) $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 2).$headerRow, $month->format('F Y'));
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
        $sheet->getPageSetup()->setPrintArea("A1:{$last}{$mouRow}");

        $raw = $book->createSheet(); $raw->setTitle('Import Data');
        $raw->fromArray([['Material Code','Month','Plant Code','Plant Name','Opening Stock','Stock Cover Days','Quantity To Buy','SAP Buying','Local Buying','Total Buying','Total Availability','Daily Consumption','Days','Expected Consumption','Closing Stock']]);
        $row = 2;
        foreach ($months as $month) foreach ($plants as $plant) {
            $cell = $matrix[$month->format('Y-m')][$plant->id];
            $raw->fromArray([[$data['selectedMaterial'],$month->format('Y-m'),$plant->company_code,$plant->name,$cell['opening'],$cell['cover_days'],$cell['to_buy'],$cell['sap_buying'],$cell['local_buying'],$cell['total_buying'],$cell['availability'],$cell['daily_consumption'],$cell['working_days'],$cell['expected_consumption'],$cell['closing']]], null, 'A'.$row++);
        }
        $raw->getStyle('A1:O1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $raw->getStyle('A1:O1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('102A43');
        foreach (range('A','O') as $letter) $raw->getColumnDimension($letter)->setAutoSize(true);
        $raw->getPageSetup()
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(1, 1)
            ->setPrintArea('A1:O'.max(1, $row - 1))
            ->setHorizontalCentered(true);
        $raw->getPageMargins()->setTop(0.35)->setRight(0.25)->setBottom(0.35)->setLeft(0.25)->setHeader(0.15)->setFooter(0.15);
        $raw->getHeaderFooter()->setOddFooter('&LAPL Connect  |  Confidential&CImport Data&RPage &P of &N');
        $book->setActiveSheetIndex(0);
        return $book;
    }
}
