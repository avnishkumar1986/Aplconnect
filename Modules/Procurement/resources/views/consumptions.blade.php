@extends('layout.app')

@section('title', 'Daily Consumption / Supply Planning')

@section('content')
    @include('procurement::planning-styles')

    @php
        $showRemarks = request()->boolean('show_remarks', true);

        $rowOffset = $entries instanceof \Illuminate\Pagination\AbstractPaginator
            ? max(0, ($entries->firstItem() ?? 1) - 1)
            : 0;
    @endphp

    <style>
        .planning-panel-head.supply-planning-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 22px 24px;
            background: linear-gradient(135deg, #ffffff 0%, #f5fafb 100%);
            border-bottom: 1px solid #d8e5eb;
            box-shadow: inset 4px 0 0 #1195a1;
        }

        .supply-planning-heading strong { display:block; color:#172b45; font-size:17px; font-weight:750; letter-spacing:-.01em; }
        .supply-planning-heading span { display:block; margin-top:5px; color:#64748b; font-size:12px; }

        .supply-planning-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }

        .supply-planning-actions .planning-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            min-height: 42px;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            border:1px solid #bfd3df;
            border-radius: 10px;
            background:#fff;
            color:#28516e;
            text-decoration: none;
            box-shadow:0 4px 12px rgba(29,61,85,.08);
            transition:transform .16s ease,border-color .16s ease,background .16s ease,box-shadow .16s ease;
        }

        .supply-planning-actions .planning-btn svg {
            display: block;
            flex-shrink: 0;
        }

        .supply-planning-actions .planning-btn:hover { transform:translateY(-1px); border-color:#79bcc5; background:#f4fbfc; box-shadow:0 7px 16px rgba(29,61,85,.12); }
        .supply-planning-actions .planning-btn-primary { border-color:#0b8290; background:linear-gradient(145deg,#13a4af,#087985)!important; color:#fff!important; }
        .supply-planning-actions .planning-btn-primary:hover { border-color:#066d77; background:linear-gradient(145deg,#1095a0,#066d77)!important; }
        .supply-planning-actions .button-label { position:absolute!important; width:1px!important; height:1px!important; padding:0!important; margin:-1px!important; overflow:hidden!important; clip:rect(0,0,0,0)!important; white-space:nowrap!important; border:0!important; }

        .supply-planning-actions .planning-btn:focus-visible {
            outline: 3px solid #93c5fd;
            outline-offset: 3px;
        }

        .supply-planning-records .planning-scroll {
            width: 100%;
            overflow-x: auto;
        }

        .supply-planning-records .planning-table {
            width: 100%;
            min-width: 1000px;
            border-collapse: collapse;
            border:1px solid #d7e3eb;
        }

        .supply-planning-records .planning-table th,
        .supply-planning-records .planning-table td {
            padding: 13px 16px;
            vertical-align: middle;
            white-space: nowrap;
            border: 1px solid #d9e4ec;
        }

        .supply-planning-records .planning-table th {
            background: linear-gradient(180deg,#eaf2f6 0%,#e4edf3 100%);
            color: #203d58;
            font-size: 11px;
            font-weight: 750;
            letter-spacing:.035em;
            text-transform:uppercase;
            text-align: left;
        }

        .supply-planning-records .planning-table td {
            color:#29445e;
            font-size: 13px;
            background:#fff;
        }

        .supply-planning-records .planning-table .planning-number {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .supply-planning-records .planning-table .planning-serial {
            width: 65px;
            text-align: center;
            font-variant-numeric: tabular-nums;
        }

        .supply-planning-records .planning-table tbody tr:hover {
            background: #f4f9fb;
        }

        .supply-planning-records .planning-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            width:max-content;
            padding:3px;
            border:1px solid #dfe7ef;
            border-radius:10px;
            background:#fff;
            box-shadow:0 2px 7px rgba(24,45,70,.04);
        }

        .supply-planning-records .planning-action-link {
            display:inline-flex;
            width:35px;
            height:35px;
            min-height:35px;
            align-items:center;
            justify-content:center;
            padding:0;
            border:1px solid transparent;
            border-radius:7px;
            color:#334155;
            font-size:12px!important;
            font-weight:700;
            text-decoration:none;
            transition:background .15s,border-color .15s,color .15s,transform .15s;
        }

        .supply-planning-records .planning-action-link svg { width:15px; height:15px; flex:0 0 15px; }
        .supply-planning-records .planning-action-label { position:absolute!important; width:1px!important; height:1px!important; padding:0!important; margin:-1px!important; overflow:hidden!important; clip:rect(0,0,0,0)!important; white-space:nowrap!important; border:0!important; }
        .supply-planning-records .planning-action-link:hover { transform:translateY(-1px); }
        .supply-planning-records .planning-action-view:hover { border-color:#cbd5e1; background:#f8fafc; color:#172b45; }
        .supply-planning-records .planning-action-view { border-color:#d5e1e9; background:#fff; color:#31536d; }
        .supply-planning-records .planning-action-edit { border-color:#b8e3e7; background:#eefafb; color:#087f8b; }
        .supply-planning-records .planning-action-edit:hover { border-color:#75cbd2; background:#ddf5f7; color:#066b75; }

        .supply-planning-records .period-badge {
            display:inline-flex;
            margin-bottom:5px;
            padding:3px 7px;
            border:1px solid #cfe1e8;
            border-radius:999px;
            background:#eaf8f9;
            color:#087681;
            font-size:10px;
            font-weight:800;
            letter-spacing:.025em;
            text-transform:uppercase;
        }

        .supply-planning-records .planning-remove {
            display:inline-grid;
            place-items:center;
            width:35px;
            height:35px;
            border:1px solid #fecaca;
            border-radius:7px;
            background:#fff;
            color:#dc2626;
            cursor:pointer;
            transition:background .15s,border-color .15s,color .15s;
        }

        .supply-planning-records .planning-actions form { margin:0; display:flex; }

        .supply-planning-records .planning-remove:hover {
            background:#fef2f2;
            border-color:#fca5a5;
            color:#b91c1c;
        }

        .supply-planning-records .planning-remove:focus-visible {
            outline:2px solid #ef4444;
            outline-offset:2px;
        }

        .supply-planning-records .planning-table .planning-remarks {
            min-width: 160px;
            max-width: 260px;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .supply-planning-records .planning-shortage { background:#fff3f2!important; color:#b42318!important; font-weight:700; }
        .supply-planning-records .planning-buy { background:#fff9e8!important; color:#8a5a00!important; font-weight:700; }
        .supply-planning-records .dataTables_wrapper { padding:20px 18px 18px; }
        .supply-planning-records .dataTables_filter input,
        .supply-planning-records .dataTables_length select { border-color:#cbdbe5!important; background:#fff!important; }

        .supply-planning-toolbar {
            display:flex;
            align-items:center;
            justify-content:flex-end;
            margin:0;
            padding:0;
        }
        .supply-planning-toolbar .supply-planning-actions { margin-left:0; }
        .supply-planning-records .dt-container > .dt-layout-row:first-child .supply-planning-toolbar { margin-left:auto; }
        .supply-planning-records .dt-container > .dt-layout-row:first-child { flex-wrap:wrap; }

        @media (max-width: 640px) {
            .planning-panel-head.supply-planning-header {
                padding: 12px 16px;
                align-items:flex-start;
                flex-direction:column;
            }
            .supply-planning-actions { width:100%; margin-left:0; }
            .supply-planning-actions .planning-btn { flex:1; }
        }
    </style>

    <div class="planning-page supply-planning-records">
        <section class="planning-panel">
            <div class="supply-planning-toolbar" aria-label="Consumption table actions">
                <div class="supply-planning-actions">
                    <a class="planning-btn"
                       title="Export Table"
                       aria-label="Export Table"
                       href="{{ route('admin.procurement.consumptions.export', request()->only('company_id', 'plant_id', 'material_group', 'material', 'show_remarks')) }}">
                        <svg width="18"
                             height="18"
                             viewBox="0 0 24 24"
                             fill="none"
                             stroke="currentColor"
                             stroke-width="1.8"
                             stroke-linecap="round"
                             stroke-linejoin="round"
                             aria-hidden="true">
                            <path d="M12 3v12"/>
                            <path d="m7 10 5 5 5-5"/>
                            <path d="M5 16v4h14v-4"/>
                        </svg>
                        <span class="button-label">Export table</span>
                    </a>

                    @can('procurement.daily_consumption.create')
                        <a class="planning-btn planning-btn-primary"
                           title="Add Consumption"
                           aria-label="Add Consumption"
                           data-form-modal
                           data-modal-title="Add Daily Consumption"
                           data-modal-size="large"
                           href="{{ route('admin.procurement.consumptions.create') }}">
                            <svg width="18"
                                 height="18"
                                 viewBox="0 0 24 24"
                                 fill="none"
                                 stroke="currentColor"
                                 stroke-width="2"
                                 stroke-linecap="round"
                                 stroke-linejoin="round"
                                 aria-hidden="true">
                                <path d="M12 5v14M5 12h14"/>
                            </svg>
                            <span class="button-label">Add consumption</span>
                        </a>
                    @endcan
                </div>
            </div>

            <div class="planning-scroll">
                <table class="planning-table nowrap"
                       id="consumptions-datatable"
                       aria-label="Supply planning records"
                       data-simple-datatable
                       data-responsive-control
                       data-sno
                       data-actions
                       data-table-toolbar=".supply-planning-toolbar"
                       data-page-length-bottom
                       data-order-column="5"
                       data-search-placeholder="Search planning records...">
                    <thead>
                        <tr>
                            <th scope="col" aria-label="Details"></th>
                            <th scope="col" class="planning-serial">S.No.</th>
                            <th scope="col">Material</th>
                            <th scope="col">Company</th>
                            <th scope="col">Plant</th>
                            <th scope="col">Planning Period</th>

                            <th scope="col" class="planning-number">
                                Current Stock (MT)
                            </th>

                            <th scope="col" class="planning-number">
                                Consumption Quantity (MT)
                            </th>

                            <th scope="col" class="planning-number">
                                Projected Closing (MT)
                            </th>

                            <th scope="col" class="planning-number">
                                Gross Shortage (MT)
                            </th>

                            <th scope="col" class="planning-number">
                                Remaining Shortage (MT)
                            </th>

                            <th scope="col" class="planning-number">
                                Cover Days
                            </th>

                            <th scope="col" class="planning-number">
                                Version
                            </th>

                            @if ($showRemarks)
                                <th scope="col">Remarks</th>
                            @endif

                            <th scope="col">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($entries as $entry)
                            @php
                                $closing = $entry->projected_closing_mt;
                                $cover = $entry->stock_cover_days;
                                $shortage = $entry->extra_required_mt;
                                $grossShortage = $entry->current_stock_mt === null
                                    ? null
                                    : max(0, round((float) $entry->quantity_mt - (float) $entry->current_stock_mt, 3));
                                $hasShortage = $shortage !== null && $shortage > 0;
                                $hasGrossShortage = $grossShortage !== null && $grossShortage > 0;
                            @endphp

                            <tr>
                                <td></td>
                                <td class="planning-serial">
                                    {{ $rowOffset + $loop->iteration }}
                                </td>

                                <td>
                                    {{ $entry->material }}

                                    <div class="planning-muted">
                                        {{ $entry->material_group ?? ($materialGroups[$entry->material] ?? '—') }}
                                    </div>
                                </td>

                                <td>{{ $entry->company_name }}</td>

                                <td>
                                    {{ $entry->plant_code }} - {{ $entry->plant_name }}

                                    <div class="planning-muted">
                                        {{ $entry->status ? 'Active' : 'Inactive' }}
                                    </div>
                                </td>

                                <td data-order="{{ $entry->consumption_date }}-{{ str_pad((string) $entry->id, 12, '0', STR_PAD_LEFT) }}">
                                    <span class="period-badge">{{ ucfirst($entry->period_type ?? 'monthly') }}</span><br>
                                    {{ \Illuminate\Support\Carbon::parse($entry->consumption_date)->format('d M Y') }}
                                    @if(($entry->period_type ?? 'monthly') === 'datewise' && $entry->effective_to)
                                        <div class="planning-muted">to {{ \Illuminate\Support\Carbon::parse($entry->effective_to)->format('d M Y') }}</div>
                                    @else
                                        <div class="planning-muted">Carries forward until changed</div>
                                    @endif
                                </td>

                                <td class="planning-number">
                                    {{ $entry->current_stock_mt === null ? 'N/A' : number_format($entry->current_stock_mt, 3) }}
                                </td>

                                <td class="planning-number">
                                    {{ number_format($entry->quantity_mt, 3) }}
                                </td>

                                <td class="planning-number {{ $closing !== null && $closing < 0 ? 'planning-shortage' : '' }}">
                                    {{ $closing === null ? 'N/A' : number_format($closing, 3) }}
                                </td>

                                <td class="planning-number {{ $hasGrossShortage ? 'planning-shortage' : '' }}">
                                    {{ $grossShortage === null ? 'N/A' : number_format($grossShortage, 3) }}
                                </td>

                                <td class="planning-number planning-buy">
                                    {{ $shortage === null ? 'N/A' : number_format($shortage, 3) }}
                                </td>

                                <td class="planning-number">
                                    {{ $cover === null ? '—' : number_format($cover, 1) }}
                                </td>

                                <td class="planning-number">
                                    {{ $entry->plan_version }}
                                </td>

                                @if ($showRemarks)
                                    <td class="planning-remarks">
                                        {{ $entry->remarks ?: '—' }}
                                    </td>
                                @endif

                                <td>
                                    <div class="planning-actions">
                                        <a class="planning-action-link planning-action-view"
                                           title="View consumption"
                                           aria-label="View consumption"
                                           href="{{ route('admin.procurement.consumptions.show', $entry->id) }}">
                                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.8"/></svg>
                                            <span class="planning-action-label">View</span>
                                        </a>
                                        @can('procurement.daily_consumption.edit')
                                            <a class="planning-action-link planning-action-edit"
                                               title="Edit consumption"
                                               aria-label="Edit consumption"
                                               data-form-modal
                                               data-modal-title="Edit Daily Consumption"
                                               data-modal-size="large"
                                               href="{{ route('admin.procurement.consumptions.edit', $entry->id) }}">
                                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m14.5 5.5 4 4M4 20l3.7-.8L19 7.9a1.4 1.4 0 0 0 0-2l-.9-.9a1.4 1.4 0 0 0-2 0L4.8 16.3 4 20Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                <span class="planning-action-label">Edit</span>
                                            </a>
                                        @endcan
                                        @can('procurement.daily_consumption.delete')
                                            <form method="POST"
                                                  action="{{ route('admin.procurement.consumptions.destroy', $entry->id) }}"
                                                  onsubmit="return confirm('Remove this consumption plan? This cannot be undone.')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="planning-remove" type="submit" title="Remove consumption" aria-label="Remove consumption">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/><path d="M10 11v5M14 11v5"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const placeConsumptionActions = () => {
        const table = document.getElementById('consumptions-datatable');
        const toolbar = document.querySelector('.supply-planning-toolbar');
        const topRow = table?.closest('.dt-container')?.querySelector(':scope > .dt-layout-row:first-child');
        if (!toolbar || !topRow || toolbar.parentElement === topRow) return Boolean(topRow);
        topRow.append(toolbar);
        const container = table.closest('.dt-container');
        const length = container?.querySelector('.dt-length')?.closest('.dt-layout-cell');
        const bottomRow = container?.querySelector(':scope > .dt-layout-row:last-child');
        if (length && bottomRow) bottomRow.prepend(length);
        return true;
    };
    if (!placeConsumptionActions()) {
        const observer = new MutationObserver(() => placeConsumptionActions() && observer.disconnect());
        observer.observe(document.querySelector('.supply-planning-records'), { childList:true, subtree:true });
    }
});
</script>
@endpush
