@extends('layout.app')
@section('title', 'Distributor Dashboard')
@section('content')
    @php
        $money = fn($value) => '₹' . number_format($value / 100000, 2) . ' L';
        $achievement =
            $performance['target'] > 0 ? round(($performance['actual'] / $performance['target']) * 100, 1) : 0;
        $maxQuarter = max(1, collect($quarters)->max('target'));
        $cumulative = 0;
    @endphp
    <div class="distributor-dashboard">
        <header class="dd-topbar">
            <div><span class="dd-eyebrow">APL CONNECT</span>
                <h1>Distributor Performance</h1>
                <p>Sales achievement, outstanding balance and order activity.</p>
            </div>
            <div class="dd-period"><span>Financial year</span><strong>Apr 2026 – Mar 2027</strong></div>
        </header>

        <nav class="dd-tabs" aria-label="Distributor dashboard sections">
            <button type="button">Visit Productivity</button><button class="active"
                type="button">Distribution+</button><button type="button">Loyalty</button><button type="button">Ticket
                Insights</button>
        </nav>

        <section class="dd-filter-card">
            <label><span>Organization</span><select>
                    <option>{{ $organization }}</option>
                    <option>All subsidiaries</option>
                </select></label>
            <button type="button">Apply filter</button>
        </section>

        <div class="dd-section-title">
            <div class="dd-medal">★</div>
            <div>
                <h2>Your performance</h2>
                <p>Live overview for the selected organization</p>
            </div><span class="dd-updated">Updated 07 Oct 2026, 02:41 PM</span>
        </div>

        <section class="dd-kpis">
            <article>
                <div class="dd-kpi-icon target">◎</div>
                <p>This year</p><strong>{{ $money($performance['target']) }}</strong>
                <h3>Target</h3><small>Apr 2026 – Mar 2027</small>
            </article>
            <article>
                <div class="dd-kpi-icon actual">↗</div>
                <p>This year (YTD)</p><strong>{{ $money($performance['actual']) }}</strong>
                <h3>Actual</h3><small class="positive">Achievement: {{ $achievement }}%</small>
            </article>
            <article>
                <div class="dd-kpi-icon outstanding">₹</div>
                <p>As on date</p><strong>{{ $money($performance['outstanding']) }}</strong>
                <h3>Outstanding</h3><small>Receivables awaiting settlement</small>
            </article>
            <article>
                <div class="dd-kpi-icon orders">□</div>
                <p>Pending orders</p><strong>{{ number_format($performance['pending_orders']) }}</strong>
                <h3>Open orders</h3><small>Orders pending fulfilment</small>
            </article>
        </section>

        <section class="dd-card dd-achievement-card">
            <header>
                <div>
                    <h2>Your actual / achievement</h2>
                    <p>Quarterly target compared with actual sales</p>
                </div>
                <div class="dd-legend"><span><i class="target"></i>Target</span><span><i class="actual"></i>Actual</span>
                </div>
            </header>
            <div class="dd-chart">
                @foreach ($quarters as $quarter)
                    @php($percent = $quarter['target'] ? round(($quarter['actual'] / $quarter['target']) * 100) : 0)
                    <div class="dd-quarter">
                        <div class="dd-bars"><i class="target"
                                style="height:{{ max(10, round(($quarter['target'] / $maxQuarter) * 100)) }}%"></i><i
                                class="actual" style="height:{{ max(10, round(($quarter['actual'] / $maxQuarter) * 100)) }}%"></i>
                        </div>
                        <strong>{{ $percent }}%</strong><span>{{ $quarter['label'] }}</span><small>{{ $money($quarter['actual']) }}
                            actual</small>
                    </div>
                @endforeach
            </div>
            <footer>
                @foreach ($quarters as $quarter)
                    @php($percent = $quarter['target'] ? round(($quarter['actual'] / $quarter['target']) * 100) : 0)
                    <div>
                        <b>{{ $percent }}%</b><strong>{{ $money($quarter['actual']) }}</strong><span>{{ strtoupper($quarter['label']) }}</span>
                    </div>
                @endforeach
            </footer>
        </section>

        <section class="dd-card dd-monthly">
            <header>
                <div>
                    <h2>Monthly achievement trend</h2>
                    <p>Target, actual and cumulative performance</p>
                </div><span class="dd-count">12 months</span>
            </header>
            <div class="dd-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Monthly target</th>
                            <th>Monthly actual</th>
                            <th>Achieved</th>
                            <th>Cumulative achievement</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($months as $month)
                            @php($cumulative += $month['actual']) @php($percent = round(($month['actual'] / $month['target']) * 100, 1))
                            <tr>
                                <td><strong>{{ $month['month'] }}</strong></td>
                                <td>{{ $money($month['target']) }}</td>
                                <td>{{ $money($month['actual']) }}</td>
                                <td>
                                    <div class="dd-progress"><i style="width:{{ min(100, $percent) }}%"></i></div><span
                                        class="{{ $percent >= 85 ? 'good' : ($percent >= 70 ? 'medium' : 'low') }}">{{ $percent }}%</span>
                                </td>
                                <td>{{ $money($cumulative) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

@push('styles')
    <style>
        .distributor-dashboard {
            --dd-navy: #142c49;
            --dd-teal: #0f99a5;
            --dd-border: #dce6ee;
            display: grid;
            gap: 18px;
            color: #233b55
        }

        .dd-topbar {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px
        }

        .dd-eyebrow {
            color: var(--dd-teal);
            font-size: 9px;
            font-weight: 850;
            letter-spacing: .13em
        }

        .dd-topbar h1 {
            margin: 4px 0;
            color: var(--dd-navy);
            font-size: 26px;
            letter-spacing: -.025em
        }

        .dd-topbar p,
        .dd-section-title p,
        .dd-card header p {
            margin: 0;
            color: #708196;
            font-size: 12px
        }

        .dd-period {
            border: 1px solid var(--dd-border);
            border-radius: 10px;
            background: #fff;
            padding: 10px 14px
        }

        .dd-period span {
            display: block;
            color: #8290a2;
            font-size: 9px;
            text-transform: uppercase
        }

        .dd-period strong {
            font-size: 12px
        }

        .dd-tabs {
            display: flex;
            gap: 5px;
            overflow-x: auto;
            border-bottom: 1px solid var(--dd-border)
        }

        .dd-tabs button {
            border: 0;
            border-bottom: 3px solid transparent;
            background: transparent;
            padding: 11px 16px;
            color: #60738a;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap
        }

        .dd-tabs button.active {
            border-color: var(--dd-teal);
            color: #087985
        }

        .dd-filter-card {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            border: 1px solid var(--dd-border);
            border-radius: 12px;
            background: #fff;
            padding: 16px 18px;
            box-shadow: 0 5px 18px rgba(25, 49, 75, .045)
        }

        .dd-filter-card label {
            width: min(440px, 100%)
        }

        .dd-filter-card label span {
            display: block;
            margin-bottom: 7px;
            color: #527089;
            font-size: 10px;
            font-weight: 750;
            text-transform: uppercase
        }

        .dd-filter-card select {
            width: 100%;
            height: 43px;
            border: 1px solid #cbd8e4;
            border-radius: 8px;
            background: #fff;
            padding: 0 12px;
            color: #2b425a
        }

        .dd-filter-card button {
            height: 42px;
            border: 0;
            border-radius: 8px;
            background: var(--dd-navy);
            padding: 0 18px;
            color: #fff;
            font-size: 11px;
            font-weight: 750;
            text-transform: uppercase
        }

        .dd-section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--dd-border);
            padding: 4px 2px 12px
        }

        .dd-section-title h2,
        .dd-card h2 {
            margin: 0 0 3px;
            color: var(--dd-navy);
            font-size: 15px
        }

        .dd-medal {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 9px;
            background: #e7f7f8;
            color: var(--dd-teal)
        }

        .dd-updated {
            margin-left: auto;
            color: #8290a2;
            font-size: 10px
        }

        .dd-kpis {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px
        }

        .dd-kpis article {
            position: relative;
            min-height: 150px;
            border: 1px solid var(--dd-border);
            border-radius: 12px;
            background: #fff;
            padding: 20px;
            box-shadow: 0 7px 22px rgba(24, 49, 75, .055)
        }

        .dd-kpis p {
            margin: 0 0 14px;
            color: #168b98;
            font-size: 9px;
            font-weight: 750;
            text-transform: uppercase
        }

        .dd-kpis strong {
            display: block;
            color: var(--dd-navy);
            font-size: 25px;
            letter-spacing: -.03em
        }

        .dd-kpis h3 {
            margin: 3px 0 8px;
            color: #344a61;
            font-size: 12px;
            text-transform: uppercase
        }

        .dd-kpis small {
            color: #8795a6;
            font-size: 9px
        }

        .dd-kpis small.positive {
            color: #13945f
        }

        .dd-kpi-icon {
            position: absolute;
            top: 17px;
            right: 17px;
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 9px;
            background: #edf7f8;
            color: #0f8d98;
            font-weight: 800
        }

        .dd-kpi-icon.outstanding {
            background: #fff5e9;
            color: #c27b1c
        }

        .dd-kpi-icon.orders {
            background: #f0efff;
            color: #6658c9
        }

        .dd-card {
            overflow: hidden;
            border: 1px solid var(--dd-border);
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 7px 22px rgba(24, 49, 75, .05)
        }

        .dd-card>header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 18px;
            border-bottom: 1px solid var(--dd-border)
        }

        .dd-legend {
            display: flex;
            gap: 14px;
            color: #687c91;
            font-size: 10px
        }

        .dd-legend span {
            display: flex;
            align-items: center;
            gap: 5px
        }

        .dd-legend i {
            width: 9px;
            height: 9px;
            border-radius: 2px
        }

        .dd-legend .target {
            background: #ced9e4
        }

        .dd-legend .actual {
            background: #159aa5
        }

        .dd-chart {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            height: 270px;
            align-items: end;
            gap: 28px;
            padding: 28px 40px 18px;
            background: repeating-linear-gradient(to top, #fff 0, #fff 53px, #edf2f6 54px)
        }

        .dd-quarter {
            text-align: center
        }

        .dd-bars {
            display: flex;
            height: 175px;
            align-items: flex-end;
            justify-content: center;
            gap: 8px
        }

        .dd-bars i {
            display: block;
            width: 24px;
            border-radius: 5px 5px 0 0
        }

        .dd-bars .target {
            background: #d4dee8
        }

        .dd-bars .actual {
            background: linear-gradient(#1aabb5, #0c8490)
        }

        .dd-quarter>strong {
            display: block;
            margin-top: 8px;
            color: #159160;
            font-size: 12px
        }

        .dd-quarter>span,
        .dd-quarter>small {
            display: block;
            color: #53677e;
            font-size: 10px
        }

        .dd-quarter>small {
            margin-top: 3px;
            color: #8996a6
        }

        .dd-achievement-card>footer {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            background: #f7f9fb;
            padding: 11px 0
        }

        .dd-achievement-card>footer div {
            text-align: center;
            border-left: 1px solid #dce5ed
        }

        .dd-achievement-card>footer div:first-child {
            border: 0
        }

        .dd-achievement-card footer b,
        .dd-achievement-card footer strong,
        .dd-achievement-card footer span {
            display: block
        }

        .dd-achievement-card footer b {
            color: #159160;
            font-size: 10px
        }

        .dd-achievement-card footer strong {
            color: #273e57;
            font-size: 13px
        }

        .dd-achievement-card footer span {
            color: #7b8999;
            font-size: 9px
        }

        .dd-count {
            border-radius: 999px;
            background: #edf7f8;
            padding: 6px 10px;
            color: #0d7f89;
            font-size: 10px;
            font-weight: 750
        }

        .dd-table-wrap {
            overflow-x: auto
        }

        .dd-monthly table {
            width: 100%;
            min-width: 760px;
            border-collapse: collapse
        }

        .dd-monthly th {
            background: #f7f9fb;
            padding: 11px 14px;
            color: #61748a;
            font-size: 9px;
            text-align: left;
            text-transform: uppercase
        }

        .dd-monthly td {
            padding: 11px 14px;
            border-top: 1px solid #edf1f5;
            color: #52667c;
            font-size: 11px
        }

        .dd-monthly tbody tr:hover {
            background: #f9fcfd
        }

        .dd-progress {
            display: inline-block;
            width: 90px;
            height: 6px;
            margin-right: 8px;
            border-radius: 999px;
            background: #e7edf2;
            vertical-align: middle
        }

        .dd-progress i {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: #16a0aa
        }

        .dd-monthly td span {
            font-weight: 750
        }

        .dd-monthly .good {
            color: #14915f
        }

        .dd-monthly .medium {
            color: #c18224
        }

        .dd-monthly .low {
            color: #cc4d58
        }

        @media(max-width:1100px) {
            .dd-kpis {
                grid-template-columns: repeat(2, 1fr)
            }
        }

        @media(max-width:720px) {

            .dd-topbar,
            .dd-filter-card,
            .dd-card>header {
                align-items: stretch;
                flex-direction: column
            }

            .dd-period {
                align-self: flex-start
            }

            .dd-filter-card label {
                width: 100%
            }

            .dd-kpis {
                grid-template-columns: 1fr
            }

            .dd-updated {
                margin-left: 0
            }

            .dd-chart {
                gap: 8px;
                padding: 24px 10px 15px
            }

            .dd-bars i {
                width: 17px
            }

            .dd-achievement-card>footer {
                overflow-x: auto
            }

            .dd-achievement-card>footer div {
                min-width: 120px
            }
        }
    </style>
@endpush
