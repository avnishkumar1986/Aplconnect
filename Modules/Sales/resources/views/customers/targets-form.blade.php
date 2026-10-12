<div class="modal-form-page">
<form class="sales-target-form" method="POST" action="{{ route('admin.sales.customers.targets.store') }}" data-sales-target-form>
    @csrf
    <section class="sales-target-intro">
        <div><span>01</span><h3>Select distributor</h3><p>Choose the customer and target year.</p></div>
        <div class="sales-target-grid two">
            <label><b>Distributor customer *</b><select name="customer_id" required><option value="">Select distributor</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id')==$customer->id)>{{ $customer->code }} - {{ $customer->business_name ?: $customer->distributorProfile?->business_name ?: $customer->full_name }}</option>@endforeach</select></label>
            <label><b>Target year *</b><select name="target_year" required>@foreach(range(now()->year-10,now()->year+10) as $year)<option value="{{ $year }}" @selected((int)old('target_year',now()->year)===$year)>{{ $year }}</option>@endforeach</select></label>
        </div>
    </section>

    <section class="sales-target-entry">
        <div class="sales-target-heading"><div><span>02</span><h3>Enter target</h3><p>Set the annual INR target and distribute it month-wise.</p></div><button type="button" data-distribute-target>Distribute annual target</button></div>
        <div class="sales-target-summary">
            <label><b>Annual target (INR) *</b><input type="number" name="yearly_target" min="0.01" step="0.01" value="{{ old('yearly_target') }}" required data-annual-target></label>
            <div><b>Monthly total</b><strong data-monthly-total>INR 0.00</strong><small data-target-balance>Enter an annual target</small></div>
        </div>
        <div class="sales-target-months">
            @foreach([1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'] as $month=>$label)
                <label><b>{{ $label }} *</b><input type="number" name="monthly_targets[{{ $month }}]" min="0" step="0.01" value="{{ old('monthly_targets.'.$month,0) }}" required data-month-target></label>
            @endforeach
        </div>
    </section>

    @if($errors->any())<div class="sales-target-errors">{{ $errors->first() }}</div>@endif
    <footer><a href="#" class="btn-secondary">Cancel</a><button type="submit">Save targets</button></footer>
</form>
<style>
.sales-target-form{color:#102a4c;display:grid;gap:16px;padding:18px}.sales-target-form section{background:#fff;border:1px solid #d9e5ed;border-radius:12px;padding:18px}.sales-target-intro>div:first-child,.sales-target-heading>div{display:grid;grid-template-columns:30px 1fr;column-gap:10px}.sales-target-intro span,.sales-target-heading span{align-items:center;background:#e7f8fa;border-radius:8px;color:#078e9b;display:flex;font-size:11px;font-weight:800;grid-row:1/3;height:30px;justify-content:center}.sales-target-form h3{font-size:15px;margin:1px 0 3px}.sales-target-form p{color:#64748b;font-size:11px}.sales-target-grid{display:grid;gap:14px;margin-top:18px}.sales-target-grid.two{grid-template-columns:repeat(2,minmax(0,1fr))}.sales-target-form label{display:grid;gap:7px}.sales-target-form label b,.sales-target-summary>div>b{font-size:10px;letter-spacing:.04em;text-transform:uppercase}.sales-target-form input,.sales-target-form select{background:#fff;border:1px solid #cbd9e5;border-radius:9px;color:#17314f;height:46px;padding:0 12px;width:100%}.sales-target-heading{align-items:center;display:flex;justify-content:space-between}.sales-target-heading button{background:#fff;border:1px solid #b9d3df;border-radius:8px;color:#0b6471;cursor:pointer;font-size:11px;font-weight:800;padding:10px 13px}.sales-target-summary{background:#f1fafb;border:1px solid #c9e9ec;border-radius:10px;display:grid;gap:14px;grid-template-columns:1fr 1fr;margin-top:18px;padding:14px}.sales-target-summary>div{background:#fff;border:1px solid #dbe7ee;border-radius:9px;display:grid;padding:10px 13px}.sales-target-summary strong{font-size:18px;margin-top:4px}.sales-target-summary small{color:#64748b;font-size:10px}.sales-target-months{display:grid;gap:11px;grid-template-columns:repeat(4,minmax(0,1fr));margin-top:15px}.sales-target-errors{background:#fff1f2;border:1px solid #fecdd3;border-radius:9px;color:#be123c;font-size:12px;padding:12px}.sales-target-form footer{display:flex;gap:10px;justify-content:flex-end}.sales-target-form footer button{border:1px solid #cbd9e5;border-radius:9px;cursor:pointer;font-size:12px;font-weight:800;padding:12px 20px}.sales-target-form footer button[type=submit]{background:#0797a5;border-color:#0797a5;color:#fff}@media(max-width:800px){.sales-target-months{grid-template-columns:repeat(2,1fr)}}@media(max-width:520px){.sales-target-grid.two,.sales-target-summary,.sales-target-months{grid-template-columns:1fr}.sales-target-heading{align-items:flex-start;gap:12px;flex-direction:column}.sales-target-heading button{width:100%}}
</style>
<style>.sales-target-form footer a{border:1px solid #cbd9e5;border-radius:9px;color:#17314f;font-size:12px;font-weight:800;padding:12px 20px;text-decoration:none}</style>
</div>
<script>
(()=>{const form=document.querySelector('[data-sales-target-form]');if(!form)return;const annual=form.querySelector('[data-annual-target]'),months=[...form.querySelectorAll('[data-month-target]')],total=form.querySelector('[data-monthly-total]'),balance=form.querySelector('[data-target-balance]');const money=value=>'INR '+Number(value||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});const update=()=>{const annualPaise=Math.round(Number(annual.value||0)*100),monthlyPaise=months.reduce((sum,input)=>sum+Math.round(Number(input.value||0)*100),0);total.textContent=money(monthlyPaise/100);const difference=annualPaise-monthlyPaise;balance.textContent=difference===0&&annualPaise>0?'Target fully allocated':difference>0?money(difference/100)+' remaining':money(Math.abs(difference)/100)+' over allocated';balance.style.color=difference===0&&annualPaise>0?'#059669':difference<0?'#dc2626':'#64748b'};form.querySelector('[data-distribute-target]').addEventListener('click',()=>{const paise=Math.round(Number(annual.value||0)*100),base=Math.floor(paise/12),remainder=paise-(base*12);months.forEach((input,index)=>input.value=((base+(index<remainder?1:0))/100).toFixed(2));update()});annual.addEventListener('input',update);months.forEach(input=>input.addEventListener('input',update));update()})();
</script>
