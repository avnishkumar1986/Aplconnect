@php($remark = $cell['remarks'][$remarkKey] ?? null)
@can('procurement.daily_consumption.edit')
    <details class="balance-remarks-editor">
        <summary title="Edit remarks" aria-label="Edit remarks">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 20h9"/><path d="m16.5 3.5 4 4L8 20l-5 1 1-5Z"/></svg>
            <span>{{ filled($remark) ? 'Edit' : 'Add' }}</span>
        </summary>
        <form method="POST" action="{{ route('admin.procurement.material-balance.remarks') }}">
            @csrf @method('PATCH')
            <input type="hidden" name="material" value="{{ $selectedMaterial }}">
            <input type="hidden" name="plant_id" value="{{ $plant->id }}">
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
            <input type="hidden" name="row_key" value="{{ $remarkKey }}">
            <textarea name="remarks" maxlength="1000" rows="3" placeholder="Enter remarks">{{ $remark }}</textarea>
            <button type="submit">Save</button>
        </form>
    </details>
@endcan
@if(filled($remark))<p class="balance-remarks-text">{{ $remark }}</p>@endif
