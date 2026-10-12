@extends('layout.app')
@section('title', 'Sales Dashboard')
@push('styles')
    <style>
        .sales-dashboard {
            --navy: #102a4c;
            --muted: #64748b;
            --line: #dce6f1;
            display: grid;
            gap: 20px
        }

        .sales-toolbar,
        .sales-panel,
        .sales-kpi {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(15, 42, 76, .055)
        }

        .sales-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 20px
        }

        .sales-eyebrow {
            color: #0891a6;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase
        }

        .sales-title {
            color: var(--navy);
            font-size: 24px;
            font-weight: 800;
            line-height: 1.15;
            margin: 4px 0
        }

        .sales-subtitle,
        .sales-updated {
            color: var(--muted);
            font-size: 12px
        }

        .sales-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap
        }

        .sales-filter {
            border: 1px solid #cad8e7;
            border-radius: 9px;
            color: var(--navy);
            font-size: 13px;
            font-weight: 700;
            min-width: 145px;
            padding: 10px 34px 10px 12px
        }

        .sales-kpi-grid {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(4, minmax(0, 1fr))
        }

        .sales-kpi {
            overflow: hidden;
            min-height: 118px;
            padding: 17px 88px 31px 16px;
            position: relative
        }

        .sales-kpi:before {
            background: var(--theme-primary, #bd2ca6);
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 3px
        }

        .sales-kpi:nth-child(even):before {
            background: var(--theme-secondary, #f36b2f)
        }

        .sales-kpi-head {
            display: contents
        }

        .sales-kpi-icon {
            position: absolute;
            top: 50%;
            right: 18px;
            display: grid;
            width: 56px;
            height: 56px;
            place-items: center;
            border: 1px solid color-mix(in srgb, var(--theme-primary, #bd2ca6) 24%, #fff);
            border-radius: 50%;
            background: color-mix(in srgb, var(--theme-primary, #bd2ca6) 9%, #fff);
            color: var(--theme-primary, #bd2ca6);
            box-shadow: 0 6px 16px color-mix(in srgb, var(--theme-primary, #bd2ca6) 12%, transparent);
            transform: translateY(-50%)
        }

        .sales-kpi:nth-child(even) .sales-kpi-icon {
            border-color: color-mix(in srgb, var(--theme-secondary, #f36b2f) 25%, #fff);
            background: color-mix(in srgb, var(--theme-secondary, #f36b2f) 9%, #fff);
            color: var(--theme-secondary, #f36b2f);
            box-shadow: 0 6px 16px color-mix(in srgb, var(--theme-secondary, #f36b2f) 12%, transparent)
        }

        .sales-kpi-icon svg {
            height: 23px;
            width: 23px
        }

        .sales-kpi-label {
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            margin: 0 0 3px
        }

        .sales-kpi-value {
            color: var(--navy);
            font-size: 23px;
            font-weight: 800;
            margin-top: 3px
        }

        .sales-change {
            position: absolute;
            left: 16px;
            bottom: 11px;
            display: block;
            color: #059669;
            font-size: 11px;
            font-weight: 800
        }

        .sales-change.down {
            color: #dc2626
        }

        .sales-change span {
            color: var(--muted);
            font-weight: 500
        }

        .tone-cyan {
            --accent: #0891a6;
            --soft: #e7f8fa
        }

        .tone-blue {
            --accent: #2563eb;
            --soft: #eef4ff
        }

        .tone-emerald {
            --accent: #059669;
            --soft: #eafaf4
        }

        .tone-amber {
            --accent: #d97706;
            --soft: #fff7e8
        }

        .tone-violet {
            --accent: #7c3aed;
            --soft: #f4efff
        }

        .tone-red {
            --accent: #dc2626;
            --soft: #fff0f0
        }

        .sales-main-grid {
            display: grid;
            gap: 18px;
            grid-template-columns: repeat(2, minmax(0, 1fr))
        }

        .sales-panel {
            padding: 20px
        }

        .sales-panel-head {
            align-items: flex-start;
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px
        }

        .sales-panel h2 {
            color: var(--navy);
            font-size: 15px;
            font-weight: 800
        }

        .sales-panel-note {
            color: var(--muted);
            font-size: 11px;
            margin-top: 3px
        }

        .sales-legend {
            display: flex;
            gap: 14px
        }

        .sales-legend span {
            align-items: center;
            color: var(--muted);
            display: flex;
            font-size: 11px;
            font-weight: 700;
            gap: 6px
        }

        .sales-legend i {
            border-radius: 4px;
            height: 8px;
            width: 8px
        }

        .sales-chart {
            align-items: end;
            border-bottom: 1px solid var(--line);
            display: grid;
            gap: 9px;
            grid-template-columns: repeat(12, minmax(22px, 1fr));
            height: 230px;
            padding: 16px 4px 0;
            position: relative
        }

        .sales-chart:before {
            background: repeating-linear-gradient(to bottom, transparent 0, transparent 52px, #edf2f7 53px);
            content: "";
            inset: 0 0 22px;
            position: absolute
        }

        .sales-month {
            align-items: center;
            display: flex;
            flex-direction: column;
            height: 100%;
            justify-content: flex-end;
            position: relative;
            z-index: 1
        }

        .sales-bars {
            align-items: end;
            display: flex;
            gap: 3px;
            height: 185px
        }

        .sales-bar {
            border-radius: 5px 5px 1px 1px;
            min-height: 8px;
            width: 8px
        }

        .sales-bar.apl {
            background: #0891a6
        }

        .sales-bar.kpl {
            background: #7dd3df
        }

        .sales-month small {
            color: #718096;
            font-size: 9px;
            margin-top: 7px
        }

        .company-stack {
            display: grid;
            gap: 12px
        }

        .company-card {
            background: #f8fafc;
            border: 1px solid #e4ebf3;
            border-radius: 11px;
            padding: 14px
        }

        .company-head {
            align-items: center;
            display: flex;
            justify-content: space-between
        }

        .company-name {
            align-items: center;
            color: var(--navy);
            display: flex;
            font-size: 13px;
            font-weight: 800;
            gap: 8px
        }

        .company-badge {
            background: #e7f8fa;
            border-radius: 7px;
            color: #087f91;
            padding: 6px 8px
        }

        .company-percent {
            color: #059669;
            font-size: 17px;
            font-weight: 800
        }

        .progress-track {
            background: #e5edf5;
            border-radius: 20px;
            height: 7px;
            margin: 11px 0;
            overflow: hidden
        }

        .progress-fill {
            background: linear-gradient(90deg, #0891a6, #22c55e);
            border-radius: 20px;
            height: 100%
        }

        .company-meta {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px
        }

        .company-meta div {
            border-right: 1px solid #e1e8f0
        }

        .company-meta div:last-child {
            border: 0
        }

        .company-meta small {
            color: var(--muted);
            display: block;
            font-size: 9px;
            text-transform: uppercase
        }

        .company-meta strong {
            color: var(--navy);
            font-size: 12px
        }

        .sales-bottom-grid {
            display: grid;
            gap: 18px;
            grid-template-columns: 1fr
        }

        .pipeline-list {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(4, minmax(0, 1fr))
        }

        .ageing-list {
            display: grid;
            gap: 10px
        }

        .pipeline-row {
            align-items: flex-start;
            background: #f8fafc;
            border: 1px solid #e7edf4;
            border-top: 3px solid var(--accent);
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-height: 120px;
            padding: 15px;
            position: relative
        }

        .pipeline-dot {
            background: var(--soft);
            border-radius: 8px;
            height: 34px;
            position: relative;
            width: 34px
        }

        .pipeline-dot:after {
            background: var(--accent);
            border-radius: 50%;
            content: "";
            height: 8px;
            left: 13px;
            position: absolute;
            top: 13px;
            width: 8px
        }

        .pipeline-row strong {
            color: var(--navy);
            display: block;
            font-size: 12px
        }

        .pipeline-row small {
            color: var(--muted);
            display: block;
            font-size: 10px
        }

        .pipeline-count {
            color: var(--navy);
            font-size: 22px;
            font-weight: 800;
            position: absolute;
            right: 15px;
            text-align: right;
            top: 17px
        }

        .ageing-row-head {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            margin-bottom: 5px
        }

        .ageing-row-head span {
            color: var(--muted);
            font-weight: 700
        }

        .ageing-row-head strong {
            color: var(--navy)
        }

        .ageing-bar {
            background: #edf2f7;
            border-radius: 20px;
            height: 8px;
            overflow: hidden
        }

        .ageing-bar i {
            background: var(--accent);
            border-radius: 20px;
            display: block;
            height: 100%
        }

        .sales-dummy-note {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 9px;
            color: #0369a1;
            font-size: 11px;
            padding: 9px 12px
        }

        @media(max-width:1100px) {
            .sales-kpi-grid {
                grid-template-columns: repeat(2, 1fr)
            }

            .sales-main-grid {
                grid-template-columns: 1fr
            }

            .pipeline-list {
                grid-template-columns: repeat(2, minmax(0, 1fr))
            }

            .sales-bottom-grid {
                grid-template-columns: 1fr
            }
        }

        @media(max-width:700px) {
            .sales-toolbar {
                align-items: flex-start;
                flex-direction: column
            }

            .sales-controls {
                width: 100%
            }

            .sales-filter {
                flex: 1;
                min-width: 120px
            }

            .sales-title {
                font-size: 20px
            }

            .sales-kpi-grid,
            .sales-bottom-grid,
            .pipeline-list {
                grid-template-columns: 1fr
            }

            .pipeline-row {
                min-height: 112px
            }

            .sales-panel {
                padding: 15px
            }

            .sales-kpi {
                padding: 15px 82px 30px 15px
            }

            .sales-kpi-icon {
                right: 15px
            }

            .sales-change {
                left: 15px
            }

            .sales-chart {
                gap: 3px;
                height: 195px;
                overflow-x: auto
            }

            .sales-bars {
                height: 150px
            }

            .sales-bar {
                width: 6px
            }
        }
    </style>
@endpush
@section('content')
    @php
        $formatInr = function ($amount) {
            if ($amount >= 10000000) {
                return '₹' . number_format($amount / 10000000, 2) . ' Cr';
            }
            if ($amount >= 100000) {
                return '₹' . number_format($amount / 100000, 2) . ' L';
            }
            return '₹' . number_format($amount);
        };
        $maxMonthly = max(array_merge($dashboard['monthly_sales']['apl'], $dashboard['monthly_sales']['kpl']));
    @endphp
    <div class="sales-dashboard">
        <section class="sales-toolbar">
            <div>
                <div class="sales-eyebrow">Sales Intelligence</div>
                <p class="sales-subtitle">Company performance, targets, orders, dispatches and collections.</p>
            </div>
            <div class="sales-controls">
                <select class="sales-filter" aria-label="Company">
                    <option value="">APL + KPL</option>
                    @foreach ($companies as $company)
                        <option value="{{ $company->company_code }}">{{ $company->company_name }}</option>
                    @endforeach
                </select>
                <select class="sales-filter" aria-label="Financial year">
                    <option>FY 2026–27</option>
                </select>
                <span class="sales-updated">Updated {{ $dashboard['updated_at'] }}</span>
            </div>
        </section>
        <section class="sales-kpi-grid">
            @foreach ($dashboard['kpis'] as $kpi)
                <article class="sales-kpi tone-{{ $kpi['tone'] }}">
                    <div class="sales-kpi-head"><span class="sales-kpi-icon">
                            @if ($kpi['icon'] === 'sales')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 3v18h18" />
                                    <path d="m7 16 4-5 3 3 5-7" />
                                </svg>
                            @elseif($kpi['icon'] === 'calendar')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="5" width="18" height="16" rx="2" />
                                    <path d="M16 3v4M8 3v4M3 10h18" />
                                </svg>
                            @elseif($kpi['icon'] === 'target')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="9" />
                                    <circle cx="12" cy="12" r="5" />
                                    <circle cx="12" cy="12" r="1" />
                            </svg>@else<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 7h16v12H4zM4 10h16M16 15h2M7 7V5h10v2" />
                                </svg>
                            @endif
                        </span>
                        <span class="sales-change {{ $kpi['change'] < 0 ? 'down' : '' }}">{{ $kpi['change'] > 0 ? '↑' : '↓' }}
                            {{ abs($kpi['change']) }}% <span>vs last period</span></span>
                    </div>
                    <p class="sales-kpi-label">{{ $kpi['label'] }}</p>
                    <p class="sales-kpi-value">
                        {{ isset($kpi['suffix']) ? number_format($kpi['value'], 1) . $kpi['suffix'] : $formatInr($kpi['value']) }}
                    </p>
                </article>
            @endforeach
        </section>
        <section class="sales-main-grid">
            <article class="sales-panel">
                <div class="sales-panel-head">
                    <div>
                        <h2>Monthly sales trend</h2>
                        <p class="sales-panel-note">Values shown in ₹ crore</p>
                    </div>
                    <div class="sales-legend"><span><i style="background:#0891a6"></i>APL</span><span><i
                                style="background:#7dd3df"></i>KPL</span></div>
                </div>
                <div class="sales-chart">
                    @foreach ($dashboard['monthly_sales']['labels'] as $index => $month)
                        <div class="sales-month">
                            <div class="sales-bars"><i class="sales-bar apl"
                                    title="APL ₹{{ $dashboard['monthly_sales']['apl'][$index] }} Cr"
                                    style="height:{{ ($dashboard['monthly_sales']['apl'][$index] / $maxMonthly) * 100 }}%"></i><i
                                    class="sales-bar kpl" title="KPL ₹{{ $dashboard['monthly_sales']['kpl'][$index] }} Cr"
                                    style="height:{{ ($dashboard['monthly_sales']['kpl'][$index] / $maxMonthly) * 100 }}%"></i>
                            </div><small>{{ $month }}</small>
                        </div>
                    @endforeach
                </div>
            </article>
            <article class="sales-panel">
                <div class="sales-panel-head">
                    <div>
                        <h2>Target vs achievement</h2>
                        <p class="sales-panel-note">Current financial year</p>
                    </div>
                </div>
                <div class="company-stack">
                    @foreach ($dashboard['companies'] as $code => $company)
                        <div class="company-card">
                            <div class="company-head"><span class="company-name"><b
                                        class="company-badge">{{ $code }}</b> {{ $code }}
                                    Sales</span><span class="company-percent">{{ $company['achievement'] }}%</span></div>
                            <div class="progress-track">
                                <div class="progress-fill" style="width:{{ $company['achievement'] }}%"></div>
                            </div>
                            <div class="company-meta">
                                <div><small>Sales</small><strong>{{ $formatInr($company['sales']) }}</strong></div>
                                <div><small>Target</small><strong>{{ $formatInr($company['target']) }}</strong></div>
                                <div>
                                    <small>Balance</small><strong>{{ $formatInr($company['target'] - $company['sales']) }}</strong>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </article>
        </section>
        <section class="sales-bottom-grid">
            <article class="sales-panel">
                <div class="sales-panel-head">
                    <div>
                        <h2>Pending orders & dispatches</h2>
                        <p class="sales-panel-note">Items requiring sales team attention</p>
                    </div><span class="sales-updated">39 open items</span>
                </div>
                <div class="pipeline-list">
                    @foreach ($dashboard['pipeline'] as $item)
                        <div class="pipeline-row tone-{{ $item['tone'] }}"><span class="pipeline-dot"></span>
                            <div><strong>{{ $item['label'] }}</strong><small>{{ $formatInr($item['amount']) }} order
                                    value</small></div><span class="pipeline-count">{{ $item['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </article>
            <article class="sales-panel">
                <div class="sales-panel-head">
                    <div>
                        <h2>Outstanding payments</h2>
                        <p class="sales-panel-note">Receivables ageing — total {{ $formatInr(32750000) }}</p>
                    </div>
                </div>
                <div class="ageing-list">
                    @foreach ($dashboard['outstanding'] as $item)
                        <div class="tone-{{ $item['tone'] }}">
                            <div class="ageing-row-head">
                                <span>{{ $item['label'] }}</span><strong>{{ $formatInr($item['value']) }} ·
                                    {{ $item['percent'] }}%</strong></div>
                            <div class="ageing-bar"><i style="width:{{ $item['percent'] }}%"></i></div>
                        </div>
                    @endforeach
                </div>
            </article>
        </section>
    </div>
@endsection
