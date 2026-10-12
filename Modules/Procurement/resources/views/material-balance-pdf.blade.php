<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 landscape;
            margin: 6mm
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 100%
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #111827;
            font-size: 6.5px
        }

        .report-head {
            background: #102a4c;
            color: #fff;
            margin: 0 0 7px;
            padding: 8px 10px
        }

        .report-head table {
            border-collapse: collapse;
            width: 100%
        }

        .report-head td {
            vertical-align: middle
        }

        .report-brand {
            color: #64d9e3;
            font-size: 7px;
            font-weight: 700;
            letter-spacing: 1.2px;
            text-transform: uppercase
        }

        .report-title {
            font-size: 13px;
            font-weight: 700;
            margin-top: 2px
        }

        .report-meta {
            color: #d5e5f2;
            font-size: 6px;
            line-height: 1.5;
            text-align: right
        }

        .report-footer {
            bottom: -4mm;
            color: #64748b;
            font-size: 6px;
            left: 0;
            position: fixed;
            right: 0;
            text-align: center
        }

        .report-footer .page-number:after {
            content: counter(page)
        }

        .sheet {
            border-collapse: collapse;
            max-width: 100%;
            table-layout: fixed;
            width: 100%
        }

        .sheet thead {
            display: table-header-group
        }

        .sheet tr {
            page-break-inside: avoid
        }

        .sheet th,
        .sheet td {
            border: .6px solid #63758a;
            overflow-wrap: anywhere;
            padding: 2px;
            vertical-align: top
        }

        .left {
            width: 80px;
            text-align: left
        }

        .detail {
            width: 85px;
            text-align: left
        }

        .month {
            background: #2b1828;
            color: #fff7fc;
            font-weight: 700;
            text-align: center;
            font-size: 8px
        }

        .plant {
            background: #3a2236 !important;
            color: #fff7fc !important;
            text-align: center
        }

        .sheet thead tr:first-child th:first-child {
            background: #2b1828;
            color: #fff7fc
        }

        .number {
            text-align: right;
            font-weight: 700
        }

        .t0 {
            background: #f3c7fa
        }

        .t1 {
            background: #fff
        }

        .t2 {
            background: #fce5d0
        }

        .t3 {
            background: #cfe8fa
        }

        .t4 {
            background: #ccf5f1
        }

        .yellow {
            background: #3a2236;
            color: #fff7fc
        }

        .total td,
        .total th {
            font-weight: 700
        }

        .closing td,
        .closing th {
            font-weight: 700;
            font-size: 8px;
            border-top: 2px solid #111827;
            border-bottom: 2px solid #111827
        }

        .closing th {
            background: #009f5b;
            color: #fff
        }

        .balance-unit {
            display: inline-block;
            margin-left: 4px;
            white-space: nowrap;
            font-size: 82%;
            font-weight: 400 !important
        }

        .negative,
        .negative strong {
            color: #dc2626 !important;
            background: #fff1f2 !important;
            font-weight: 700
        }

        .negative .balance-unit {
            font-size: 82%;
            font-weight: 400 !important
        }

        .po {
            min-height: 24px
        }

        .remarks {
            font-size: 5px
        }
    </style>
</head>

<body>
    @php($selected = $materials->firstWhere('matnr', $selectedMaterial))
    <div class="report-head">
        <table><tr><td><div class="report-brand">APL Connect</div><div class="report-title">Material Balance Summary</div></td><td class="report-meta"><strong>{{ $selected?->maktx ?: $selectedMaterial }}</strong><br>{{ $selectedMaterial }} · Generated {{ now()->format('d.m.Y, h:i A') }}</td></tr></table>
    </div>
    <div class="report-footer">APL Connect · Confidential · Page <span class="page-number"></span></div>
    <table class="sheet">
        <thead>
            <tr>
                <th colspan="2"></th>
                @foreach ($months as $month)
                    <th colspan="{{ $plants->count() * 2 }}" class="month">{{ $month->format('F Y') }}</th>
                @endforeach
            </tr>
            <tr>
                <th colspan="2" class="yellow">{{ $selected?->category_name ?: 'Material Category' }}</th>
                @foreach ($months as $month)
                    @foreach ($plants as $i => $plant)
                        <th class="plant t{{ $i % 5 }}">{{ $plant->name }}</th>
                        <th class="plant t{{ $i % 5 }}">Remarks</th>
                    @endforeach
                @endforeach
            </tr>
        </thead>
        <tbody>
            @php($baseRows = ['opening' => ['O/s', now()->format('d.m.Y')], 'cover_days' => ['Cover for No. Of Days', ''], 'to_buy' => ['To Buy from RIL / Chemplast / DCW / Local', 'RIL - AT Dadri, DCW / CP at Tumkur / Raipur']])
            @foreach ($baseRows as $key => $labels)
                <tr>
                    <th class="left">{{ $labels[0] }}</th>
                    <th class="detail">{{ $labels[1] }}</th>
                    @foreach ($months as $month)
                        @foreach ($plants as $i => $plant)
                            @php($c = $matrix[$month->format('Y-m')][$plant->id])
                            <td class="number t{{ $i % 5 }}">@if($c[$key] !== null){{ number_format($c[$key], $key === 'cover_days' ? 1 : 3) }}<strong class="balance-unit">({{ $key === 'cover_days' ? 'days' : 'mtn' }})</strong>@endif
                            </td>
                            <td class="t{{ $i % 5 }}">{{ $c['remarks'][$key] ?? '' }}</td>
                        @endforeach
                    @endforeach
                </tr>
            @endforeach
            @php($orderKey = fn($o) => implode('|', [$o->source, $o->vendor_name ?: $o->vendor_code, $o->purchase_order, $o->purchase_order_item, $o->delivery_date, $o->mt_quantity]))
            @php($receiptOrders = collect($matrix)->flatten(1)->flatMap(fn($c) => $c['orders'])->sortBy(fn($o) => sprintf('%s|%s|%s', $o->vendor_name ?: $o->vendor_code, $o->delivery_date, $o->purchase_order))->values())
            @forelse ($receiptOrders as $orderIndex => $receiptOrder)
                @php($vendorName = $receiptOrder->vendor_name ?: $receiptOrder->vendor_code)
                @php($previousVendor = $orderIndex > 0 ? ($receiptOrders[$orderIndex - 1]->vendor_name ?: $receiptOrders[$orderIndex - 1]->vendor_code) : null)
                @php($firstVendorRow = $orderIndex === 0 || $previousVendor !== $vendorName)
                <tr>
                    @if($orderIndex === 0)<th class="left" rowspan="{{ $receiptOrders->count() }}">Supplier receipts</th>@endif
                    @if($firstVendorRow)<th class="detail" rowspan="{{ $receiptOrders->filter(fn($o) => ($o->vendor_name ?: $o->vendor_code) === $vendorName)->count() }}">{{ $vendorName }}</th>@endif
                    @foreach ($months as $month)
                        @foreach ($plants as $i => $plant)
                            @php($c = $matrix[$month->format('Y-m')][$plant->id])
                            @php($cellOrder = $c['orders']->first(fn($o) => $orderKey($o) === $orderKey($receiptOrder)))
                            @php($receiptRemark = $c['remarks']['receipt:'.sha1($orderKey($receiptOrder))] ?? null)
                            <td class="number po t{{ $i % 5 }}">@if($cellOrder){{ number_format($cellOrder->mt_quantity, 1) }}<strong class="balance-unit">(mtn)</strong>@endif
                            </td>
                            <td class="remarks t{{ $i % 5 }}">
                                @if($cellOrder)
                                    {{ $cellOrder->source }}
                                    @if($cellOrder->purchase_order) · PO {{ $cellOrder->purchase_order }}@endif
                                    @if($cellOrder->purchase_order_item)/{{ $cellOrder->purchase_order_item }}@endif<br>
                                    Stage: {{ $cellOrder->delivery_status_label ?: 'Planned' }}<br>ETA
                                    {{ \Illuminate\Support\Carbon::parse($cellOrder->delivery_date)->format('d.m.Y') }}
                                @endif
                                @if($receiptRemark)<br>Remarks: {{ $receiptRemark }}@endif
                            </td>
                        @endforeach
                    @endforeach
                </tr>
            @empty
                <tr><th class="left">Supplier receipts</th><th class="detail">No supplier receipt</th>@foreach($months as $month)@foreach($plants as $i => $plant)<td class="number po t{{ $i % 5 }}"></td><td class="remarks t{{ $i % 5 }}"></td>@endforeach @endforeach</tr>
            @endforelse
            @php($totalRows = ['sap_buying' => 'Buying / Imports', 'total_buying' => 'Total Buying', 'availability' => 'Total Availability', 'daily_consumption' => 'Expected Average Consp/day', 'working_days' => 'Days', 'expected_consumption' => 'Total Expected Consp (MT)', 'gross_shortage' => 'Gross Shortage Before POs (MT)', 'closing' => 'Closing Stock', 'extra_required' => 'Remaining Shortage (MT)'])
            @foreach ($totalRows as $key => $label)
                <tr
                    class="{{ in_array($key, ['sap_buying', 'total_buying', 'availability']) ? 'total' : '' }} {{ $key === 'closing' ? 'closing' : '' }}">
                    <th colspan="2" class="left">{{ $label }}</th>
                    @foreach ($months as $month)
                        @foreach ($plants as $i => $plant)
                            @php($c = $matrix[$month->format('Y-m')][$plant->id])
                            @php($unit = $key === 'working_days' ? 'days' : ($key === 'daily_consumption' ? 'mtn/day' : 'mtn'))
                            <td
                                class="number t{{ $i % 5 }} {{ is_numeric($c[$key]) && $c[$key] < 0 ? 'negative' : '' }}">
                                @if($c[$key] === null)N/A @else{{ number_format($c[$key], $key === 'working_days' ? 0 : ($key === 'daily_consumption' ? 4 : 3)) }}<strong class="balance-unit">({{ $unit }})</strong>@endif</td>
                            <td class="t{{ $i % 5 }}">{{ $c['remarks'][$key] ?? '' }}</td>
                        @endforeach
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    <table class="sheet" style="margin-top:8px;width:100%">
        <tr>
            <th colspan="{{ $months->count() + 2 }}">MOU Commitments by Planning Month</th>
        </tr>
        <tr>
<th style="background:#2b1828;color:#fff7fc">Supplier</th>@foreach($months as $month)<th style="background:#2b1828;color:#fff7fc">{{ $month->format('F Y') }}</th>@endforeach<th style="background:#2b1828;color:#fff7fc">Total</th>
        </tr>
        @forelse($mouPlanning->pluck('vendor_name')->unique()->values() as $vendorName)
            <tr>
                <td>{{ $vendorName }}</td>
                @foreach($months as $month)<td class="number">{{ number_format($mouPlanning->where('vendor_name', $vendorName)->where('month_key', $month->format('Y-m'))->sum('quantity_mt'), 1) }}</td>@endforeach
                <td class="number">{{ number_format($mouPlanning->where('vendor_name', $vendorName)->sum('quantity_mt'), 1) }}</td>
        </tr>@empty<tr>
                <td colspan="{{ $months->count() + 2 }}">No MOU vendor plan available</td>
            </tr>
        @endforelse
        <tr>
            <th>Total Qty.</th>
            @foreach($months as $month)<th class="number">{{ number_format($mouPlanning->where('month_key', $month->format('Y-m'))->sum('quantity_mt'), 1) }}</th>@endforeach
            <th class="number">{{ number_format($mouPlanning->sum('quantity_mt'), 1) }}</th>
        </tr>
    </table>
</body>

</html>
