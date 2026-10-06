<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 12px
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #111827;
            font-size: 6px
        }

        h1 {
            margin: 0 0 8px;
            font-size: 12px
        }

        .sheet {
            border-collapse: collapse;
            width: 100%
        }

        .sheet th,
        .sheet td {
            border: 1px solid #111827;
            padding: 3px;
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
            text-align: center;
            font-size: 8px
        }

        .plant {
            text-align: center
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
            background: #fff600
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

        .negative {
            color: #be123c
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
    <h1>Material Balance Summary - {{ $selected?->maktx ?: $selectedMaterial }}</h1>
    <table class="sheet">
        <thead>
            <tr>
                <th colspan="2"></th>
                @foreach ($months as $month)
                    <th colspan="{{ $plants->count() * 2 }}" class="month">{{ $month->format('M-y') }}</th>
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
                            <td class="number t{{ $i % 5 }}">{{ $c[$key] === null ? '' : number_format($c[$key], 1) }}
                            </td>
                            <td class="t{{ $i % 5 }}"></td>
                        @endforeach
                    @endforeach
                </tr>
            @endforeach
            @php($receiptVendors = collect($matrix)->flatten(1)->flatMap(fn($c) => $c['orders'])->map(fn($o) => $o->vendor_name ?: $o->vendor_code)->filter()->unique()->sort()->values())
            @forelse ($receiptVendors as $vendorIndex => $vendorName)
                <tr>
                    <th class="left">{{ $vendorIndex === 0 ? 'Supplier receipts' : '' }}</th>
                    <th class="detail">{{ $vendorName }}</th>
                    @foreach ($months as $month)
                        @foreach ($plants as $i => $plant)
                            @php($vendorOrders = $matrix[$month->format('Y-m')][$plant->id]['orders']->filter(fn($o) => ($o->vendor_name ?: $o->vendor_code) === $vendorName)->values())
                            <td class="number po t{{ $i % 5 }}">{{ $vendorOrders->isNotEmpty() ? number_format($vendorOrders->sum('mt_quantity'), 1) : '' }}
                            </td>
                            <td class="remarks t{{ $i % 5 }}">
                                @foreach ($vendorOrders as $o)
                                    {{ $o->source }}
                                    @if($o->purchase_order) · PO {{ $o->purchase_order }}@endif
                                    @if($o->purchase_order_item)/{{ $o->purchase_order_item }}@endif<br>
                                    Stage: {{ $o->delivery_status_label ?: 'Planned' }}<br>ETA
                                    {{ $o->delivery_date }}@unless($loop->last)<br><br>@endunless
                                @endforeach
                            </td>
                        @endforeach
                    @endforeach
                </tr>
            @empty
                <tr><th class="left">Supplier receipts</th><th class="detail">No supplier receipt</th>@foreach($months as $month)@foreach($plants as $i => $plant)<td class="number po t{{ $i % 5 }}"></td><td class="remarks t{{ $i % 5 }}"></td>@endforeach @endforeach</tr>
            @endforelse
            @php($totalRows = ['sap_buying' => 'Buying / Imports', 'total_buying' => 'Total Buying', 'availability' => 'Total Availability', 'daily_consumption' => 'Expected Average Consp/day', 'working_days' => 'Working Days', 'expected_consumption' => 'Total Expected Consp (MT)', 'gross_shortage' => 'Gross Shortage Before POs (MT)', 'closing' => 'Closing Stock', 'extra_required' => 'Remaining Shortage (MT)'])
            @foreach ($totalRows as $key => $label)
                <tr
                    class="{{ in_array($key, ['sap_buying', 'total_buying', 'availability']) ? 'total' : '' }} {{ $key === 'closing' ? 'closing' : '' }}">
                    <th colspan="2" class="left">{{ $label }}</th>
                    @foreach ($months as $month)
                        @foreach ($plants as $i => $plant)
                            @php($c = $matrix[$month->format('Y-m')][$plant->id])
                            <td
                                class="number t{{ $i % 5 }} {{ ($key === 'closing' && $c[$key] < 0) || (in_array($key, ['gross_shortage', 'extra_required']) && $c[$key] > 0) ? 'negative' : '' }}">
                                {{ $c[$key] === null ? 'N/A' : number_format($c[$key], $key === 'daily_consumption' ? 3 : 1) }}</td>
                            <td class="t{{ $i % 5 }}"></td>
                        @endforeach
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    <table class="sheet" style="margin-top:10px;width:500px">
        <tr>
            <th colspan="{{ $months->count() + 2 }}">MOU Commitments by Planning Month</th>
        </tr>
        <tr>
            <th style="background:#ecfeff">Supplier</th>@foreach($months as $month)<th style="background:#ecfeff">{{ $month->format('M-y') }}</th>@endforeach<th style="background:#ecfeff">Total</th>
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
