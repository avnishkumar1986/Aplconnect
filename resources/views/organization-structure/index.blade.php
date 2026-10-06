@extends('layout.app')
@section('title', 'Organization Structure')

@section('content')
<div class="org-structure" data-org-structure>
    @if($errors->any())<div class="org-alert"><strong>Please correct the form:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="org-tab-header">
        <nav class="org-tabs" aria-label="Organization records">
            <button class="active" data-org-tab="departments">Departments <span>{{ $departments->count() }}</span></button>
            <button data-org-tab="designations">Designations <span>{{ $designations->count() }}</span></button>
            <button data-org-tab="levels">Designation Levels <span>{{ $levels->count() }}</span></button>
        </nav>
        <form method="GET" class="org-search">
            <input name="q" value="{{ $search }}" placeholder="Search departments or designations…" aria-label="Search organization records">
            <button>Search</button>
        </form>
    </div>

    @php
        $sections = [
            'departments' => ['title' => 'Departments', 'subtitle' => 'Business functions and teams', 'records' => $departments],
            'designations' => ['title' => 'Designations', 'subtitle' => 'Job titles and reporting hierarchy', 'records' => $designations],
            'levels' => ['title' => 'Designation Levels', 'subtitle' => 'Seniority and hierarchy sequence', 'records' => $levels],
        ];
    @endphp
    @foreach($sections as $type => $section)
    <section class="org-panel {{ $type !== 'departments' ? 'hidden' : '' }}" data-org-panel="{{ $type }}">
        <header><div><h2>{{ $section['title'] }}</h2><p>{{ $section['subtitle'] }}</p></div><div class="org-header-actions">@can('users.edit')<form class="table-bulk-actions" method="POST" action="{{ route('admin.organization-structure.bulk-action', $type) }}" data-org-bulk-form>@csrf<select name="action" required><option value="">Bulk actions</option><option value="activate">Activate</option><option value="deactivate">Deactivate</option><option value="delete">Delete</option></select><button disabled>Apply</button><span data-org-bulk-count>0 selected</span></form>@endcan @can('users.create')<button class="org-primary" data-org-create="{{ $type }}">+ Add {{ Str::singular($section['title']) }}</button>@endcan</div></header>
        <div class="org-table-wrap"><table class="org-table"><thead><tr>
            <th class="check"><input type="checkbox" data-org-select-all aria-label="Select all"></th><th>S.NO.</th>
            @if($type === 'departments')<th>Code</th><th>Department</th><th>Description</th><th>Designations</th>
            @elseif($type === 'designations')<th>Code</th><th>Designation</th><th>Department</th><th>Level</th><th>Reports To</th>
            @else<th>Code</th><th>Level</th><th>Hierarchy Order</th><th>Reports To</th><th>Designations</th>@endif
            <th>Status</th><th class="org-actions">Actions</th>
        </tr></thead><tbody>
        @forelse($section['records'] as $record)<tr>
            <td class="check"><input type="checkbox" value="{{ $record->id }}" data-org-select aria-label="Select record"></td><td class="record-serial">{{ $loop->iteration }}</td>
            @if($type === 'departments')
                <td><strong>{{ $record->department_code }}</strong></td><td>{{ $record->department_name }}</td><td>{{ $record->description ?: '—' }}</td><td>{{ $record->designations_count }}</td>
            @elseif($type === 'designations')
                <td><strong>{{ $record->des_id }}</strong></td><td>{{ $record->designation_name }}</td><td>{{ $record->department?->department_name ?: '—' }}</td><td>{{ $record->level?->level_name ?: '—' }}</td><td>{{ $record->parentDesignation?->designation_name ?: '—' }}</td>
            @else
                <td><strong>{{ $record->level_code }}</strong></td><td>{{ $record->level_name }}</td><td>{{ $record->hierarchy_order }}</td><td>{{ $record->reportsToLevel?->level_name ?: '—' }}</td><td>{{ $record->designations_count }}</td>
            @endif
            <td>
                @can('users.edit')
                    @php($statusResource = $type === 'levels' ? 'designation-levels' : $type)
                    <button type="button" class="status-toggle {{ $record->status ? 'active' : 'inactive' }}"
                        data-status-url="{{ route('admin.status.toggle', [$statusResource, $record->id]) }}"
                        aria-pressed="{{ $record->status ? 'true' : 'false' }}"
                        aria-label="{{ $record->status ? 'Deactivate' : 'Activate' }} record">
                        <span class="status-switch"><i></i></span>
                        <span class="status-toggle-label">{{ $record->status ? 'Active' : 'Inactive' }}</span>
                    </button>
                @else
                    <span class="org-status {{ $record->status ? 'active' : '' }}">{{ $record->status ? 'Active' : 'Inactive' }}</span>
                @endcan
            </td>
            <td class="org-actions">
                @can('users.edit')<button class="org-icon" title="Edit" data-org-edit="{{ $type }}" data-record="{{ $record->toJson() }}">✎</button>@endcan
                @can('users.delete')<form method="POST" action="{{ route('admin.organization-structure.destroy', [$type, $record->id]) }}" onsubmit="return confirm('Remove this record?')">@csrf @method('DELETE')<button class="org-icon danger" title="Remove">×</button></form>@endcan
            </td>
        </tr>@empty<tr><td colspan="9" class="org-empty">No {{ strtolower($section['title']) }} found.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    @endforeach

    <div class="org-modal hidden" data-org-modal aria-hidden="true"><div class="org-modal-backdrop" data-org-close></div><section class="org-dialog" role="dialog" aria-modal="true">
        <header><div><p>Organization Master</p><h2 data-org-title>Add record</h2></div><button data-org-close>×</button></header>
        <form method="POST" data-org-form>@csrf <input type="hidden" name="_method" value="POST" data-org-method>
            <div class="org-form" data-form-fields="departments">
                <label>Department code *<input name="department_code" maxlength="50"></label><label>Department name *<input name="department_name" maxlength="150"></label>
                <label class="wide">Description<textarea name="description" rows="3"></textarea></label>
            </div>
            <div class="org-form hidden" data-form-fields="designations">
                <label>Designation code *<input name="des_id" maxlength="50"></label><label>Designation name *<input name="designation_name" maxlength="150"></label>
                <label>Department<select name="department_id"><option value="">Not assigned</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->department_name }}</option>@endforeach</select></label>
                <label>Designation level<select name="designation_level_id"><option value="">Not assigned</option>@foreach($levels as $level)<option value="{{ $level->id }}">{{ $level->level_code }} — {{ $level->level_name }}</option>@endforeach</select></label>
                <label>Reports to<select name="reports_to_designation_id"><option value="">Top level</option>@foreach($designations as $designation)<option value="{{ $designation->id }}">{{ $designation->designation_name }}</option>@endforeach</select></label>
                <label>Sitting place<input name="sitting_place" maxlength="255"></label>
            </div>
            <div class="org-form hidden" data-form-fields="levels">
                <label>Level code *<input name="level_code" maxlength="10"></label><label>Level name *<input name="level_name" maxlength="100"></label>
                <label>Hierarchy order *<input type="number" min="1" name="hierarchy_order"></label><label>Reports to level<select name="reports_to_level_id"><option value="">Top level</option>@foreach($levels as $level)<option value="{{ $level->id }}">{{ $level->level_name }}</option>@endforeach</select></label>
            </div>
            <label class="org-status-field">Status<select name="status"><option value="1">Active</option><option value="0">Inactive</option></select></label>
            <footer><button type="button" class="org-secondary" data-org-close>Cancel</button><button class="org-primary">Save changes</button></footer>
        </form>
    </section></div>
</div>
@endsection

@push('styles')
<style>
.org-structure{font-size:14px;color:#14243d}.org-hero,.org-panel{background:var(--theme-card,#fff);border:1px solid #d8e2ef;border-radius:14px;box-shadow:0 8px 28px rgba(15,35,65,.06)}.org-hero{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:24px 28px}.org-eyebrow{color:var(--theme-primary,#159caf)!important;font-size:11px!important;font-weight:800;letter-spacing:.14em;text-transform:uppercase}.org-hero h1{font-size:26px;font-weight:750;margin:3px 0}.org-hero p,.org-panel header p{color:#657691}.org-search{display:flex;width:min(390px,100%)}.org-search input{min-width:0;flex:1;border:1px solid #ced9e8;border-radius:9px 0 0 9px;padding:10px 13px}.org-search button,.org-primary{background:var(--theme-primary,#159caf);color:white;font-weight:700;border:0;border-radius:9px;padding:10px 16px}.org-search button{border-radius:0 9px 9px 0}.org-tabs{display:flex;gap:6px;margin:20px 0 12px;border-bottom:1px solid #d8e2ef}.org-tabs button{padding:12px 17px;border:0;border-bottom:3px solid transparent;background:transparent;color:#60708a;font-weight:700}.org-tabs button.active{color:var(--theme-primary,#159caf);border-color:var(--theme-primary,#159caf)}.org-tabs span{margin-left:6px;border-radius:999px;background:#eaf0f7;padding:2px 7px;font-size:11px}.org-panel header{display:flex;align-items:center;justify-content:space-between;padding:20px 22px;border-bottom:1px solid #e2e9f2}.org-panel h2{font-size:18px;font-weight:750}.org-table-wrap{overflow:auto}.org-panel table{width:100%;border-collapse:collapse}.org-panel th{background:#f2f6fb;color:#435570;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.04em}.org-panel th,.org-panel td{padding:13px 16px;border-bottom:1px solid #e4eaf2;white-space:nowrap}.org-panel tbody tr:hover{background:#f8fbfe}.org-actions{text-align:right!important}.org-actions form{display:inline}.org-icon{width:31px;height:31px;border:1px solid #cdd9e8;border-radius:7px;background:white;color:#28708b;font-size:17px;margin-left:4px}.org-icon.danger{color:#d84b55}.org-status{display:inline-flex;border-radius:999px;background:#f1f3f6;color:#687589;padding:4px 9px;font-size:11px;font-weight:700}.org-status.active{background:#dcf8ee;color:#087c5e}.org-empty{text-align:center!important;color:#74839a;padding:40px!important}.org-alert{margin-top:16px;border:1px solid #f2b8bd;background:#fff1f2;color:#9f2633;border-radius:10px;padding:13px 16px}.org-modal{position:fixed;z-index:90;inset:0;display:grid;place-items:center;padding:20px}.org-modal.hidden,.org-panel.hidden,.org-form.hidden{display:none}.org-modal-backdrop{position:absolute;inset:0;background:rgba(9,23,43,.58);backdrop-filter:blur(2px)}.org-dialog{position:relative;width:min(760px,100%);max-height:90vh;overflow:auto;background:#fff;border-radius:14px;box-shadow:0 24px 70px rgba(0,0,0,.25)}.org-dialog>header{display:flex;justify-content:space-between;align-items:center;background:var(--theme-sidebar-bg,#112238);color:#fff;padding:17px 22px}.org-dialog>header p{font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:#79d7df}.org-dialog>header h2{font-size:18px;font-weight:750}.org-dialog>header button{border:0;background:rgba(255,255,255,.1);color:#fff;border-radius:7px;width:34px;height:34px;font-size:22px}.org-dialog form{padding:22px}.org-form{display:grid;grid-template-columns:1fr 1fr;gap:17px}.org-form label,.org-status-field{display:grid;gap:7px;font-size:12px;font-weight:700}.org-form label.wide{grid-column:1/-1}.org-form input,.org-form select,.org-form textarea,.org-status-field select{border:1px solid #cdd9e8;border-radius:8px;padding:10px 11px;background:#fbfcfe;font:inherit}.org-status-field{margin-top:17px;width:calc(50% - 9px)}.org-dialog footer{display:flex;justify-content:flex-end;gap:10px;margin:22px -22px -22px;padding:15px 22px;background:#f7f9fc;border-top:1px solid #e2e8f0}.org-secondary{border:1px solid #cbd6e5;background:#fff;border-radius:9px;padding:9px 16px;font-weight:700}@media(max-width:760px){.org-hero{align-items:stretch;flex-direction:column}.org-search{width:100%}.org-tabs{overflow:auto}.org-form{grid-template-columns:1fr}.org-status-field{width:100%}}
.org-header-actions{display:flex;align-items:center;justify-content:flex-end;gap:12px;flex-wrap:wrap}@media(max-width:760px){.org-panel header{align-items:flex-start;flex-direction:column}.org-header-actions{width:100%;justify-content:flex-start}}
.org-tab-header{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin:0 0 12px;border:1px solid #d8e2ef;border-radius:12px 12px 0 0;background:#fff;padding:0 18px;box-shadow:0 5px 18px rgba(15,35,65,.04)}
.org-tab-header .org-tabs{flex:1;margin:0;border-bottom:0}.org-tab-header .org-search{flex:none;width:min(390px,42vw);padding:11px 0}.org-tab-header .org-search button{background:var(--apl-teal)!important;color:#fff!important}.org-tab-header .org-search button:hover{background:var(--apl-teal-dark)!important}
@media(max-width:850px){.org-tab-header{align-items:stretch;flex-direction:column;gap:0;padding:0 14px 12px}.org-tab-header .org-tabs{width:100%;overflow-x:auto}.org-tab-header .org-search{width:100%;padding:0}}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{const root=document.querySelector('[data-org-structure]');if(!root)return;const modal=root.querySelector('[data-org-modal]'),form=root.querySelector('[data-org-form]'),method=root.querySelector('[data-org-method]'),title=root.querySelector('[data-org-title]');const activate=(type)=>{root.querySelectorAll('[data-org-tab]').forEach(x=>x.classList.toggle('active',x.dataset.orgTab===type));root.querySelectorAll('[data-org-panel]').forEach(x=>x.classList.toggle('hidden',x.dataset.orgPanel!==type))};root.querySelectorAll('[data-org-tab]').forEach(x=>x.addEventListener('click',()=>activate(x.dataset.orgTab)));const open=(type,record=null)=>{form.reset();form.querySelectorAll('input,select,textarea').forEach(x=>x.disabled=true);root.querySelectorAll('[data-form-fields]').forEach(x=>x.classList.toggle('hidden',x.dataset.formFields!==type));root.querySelector(`[data-form-fields="${type}"]`).querySelectorAll('input,select,textarea').forEach(x=>x.disabled=false);form.querySelector('[name=status]').disabled=false;form.action=record?`{{ url('/admin/organization-structure') }}/${type}/${record.id}`:`{{ url('/admin/organization-structure') }}/${type}`;method.value=record?'PUT':'POST';title.textContent=`${record?'Edit':'Add'} ${type==='levels'?'Designation Level':type.slice(0,-1).replace(/^./,c=>c.toUpperCase())}`;if(record)Object.entries(record).forEach(([key,value])=>{const field=form.querySelector(`[name="${key}"]`);if(field&&!field.disabled)field.value=value??''});modal.classList.remove('hidden');modal.setAttribute('aria-hidden','false')};root.querySelectorAll('[data-org-create]').forEach(x=>x.addEventListener('click',()=>open(x.dataset.orgCreate)));root.querySelectorAll('[data-org-edit]').forEach(x=>x.addEventListener('click',()=>open(x.dataset.orgEdit,JSON.parse(x.dataset.record))));root.querySelectorAll('[data-org-close]').forEach(x=>x.addEventListener('click',()=>{modal.classList.add('hidden');modal.setAttribute('aria-hidden','true')}));root.querySelectorAll('[data-org-panel]').forEach(panel=>{const bulk=panel.querySelector('[data-org-bulk-form]'),all=panel.querySelector('[data-org-select-all]'),boxes=[...panel.querySelectorAll('[data-org-select]')],count=bulk?.querySelector('[data-org-bulk-count]'),apply=bulk?.querySelector('button');const refresh=()=>{const selected=boxes.filter(x=>x.checked);if(count)count.textContent=`${selected.length} selected`;if(apply)apply.disabled=!selected.length;if(all){all.checked=boxes.length>0&&selected.length===boxes.length;all.indeterminate=selected.length>0&&selected.length<boxes.length}};all?.addEventListener('change',()=>{boxes.forEach(x=>x.checked=all.checked);refresh()});boxes.forEach(x=>x.addEventListener('change',refresh));bulk?.addEventListener('submit',event=>{bulk.querySelectorAll('input[name="ids[]"]').forEach(x=>x.remove());const selected=boxes.filter(x=>x.checked);if(!selected.length){event.preventDefault();return}if(bulk.querySelector('[name=action]').value==='delete'&&!confirm('Delete all selected records?')){event.preventDefault();return}selected.forEach(x=>{const input=document.createElement('input');input.type='hidden';input.name='ids[]';input.value=x.value;bulk.appendChild(input)})});refresh()})});
</script>
@endpush
