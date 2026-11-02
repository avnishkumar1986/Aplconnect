<?php
namespace Modules\SalesDistribution\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Services\MonthlyCollectionSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\SalesDistribution\Models\Collection;

class CollectionController extends Controller
{
    public function index(Request $request): Response
    {
        $search=trim((string)$request->string('search')); $perPage=(int)config('datatable.per_page',50);
        $query=Collection::query()->when($search!=='',fn($q)=>$q->where(fn($q)=>$q->where('customer_number','like',"%{$search}%")->orWhere('document_number','like',"%{$search}%")->orWhere('company_code','like',"%{$search}%")->orWhere('description','like',"%{$search}%")));
        $rows=(clone $query)->orderByDesc('posting_date')->orderByDesc('id')->paginate($perPage)->withQueryString();
        $stats=Cache::remember('sales-distribution.collections.stats',now()->addMinutes(10),fn()=>['total'=>Collection::count(),'active'=>Collection::where('status',true)->count(),'amount'=>(float)Collection::where('status',true)->sum('amount_received')]);
        return Inertia::render('modules/salesdistribution/collections/index',['collections'=>$rows,'filters'=>['search'=>$search,'per_page'=>$perPage],'stats'=>$stats,'canManage'=>$request->user()?->can('sales_distribution.collections.manage')??false]);
    }
    public function store(Request $request): RedirectResponse { Collection::create($this->validated($request)); $this->refresh(); return back()->with('success','Collection created.'); }
    public function update(Request $request, Collection $collection): RedirectResponse { $collection->update($this->validated($request,$collection)); $this->refresh(); return back()->with('success','Collection updated.'); }
    public function toggleStatus(Request $request, Collection $collection): RedirectResponse { $collection->update(['status'=>$request->validate(['active'=>['required','boolean']])['active']]); $this->refresh(); return back()->with('success','Collection status updated.'); }
    public function destroy(Collection $collection): RedirectResponse { $collection->delete(); $this->refresh(); return back()->with('success','Collection deleted.'); }
    public function bulkAction(Request $request): RedirectResponse { $data=$request->validate(['ids'=>['required','array','min:1'],'ids.*'=>['integer','distinct',Rule::exists('tbl_customer_collection_transactions','id')],'action'=>['required',Rule::in(['activate','deactivate','delete'])]]); $q=Collection::whereIn('id',$data['ids']); $data['action']==='delete'?$q->delete():$q->update(['status'=>$data['action']==='activate','updated_at'=>now()]); $this->refresh(); return back()->with('success','Collections updated.'); }
    private function validated(Request $request, ?Collection $row=null): array
    {
        $data=$request->validate(['customer_number'=>['required','string','max:50'],'company_code'=>['nullable','string','max:20'],'document_number'=>['required','string','max:50'],'fiscal_year'=>['required','integer','min:2000','max:2100'],'posting_date'=>['required','date'],'document_date'=>['nullable','date'],'document_type'=>['required','string','max:10'],'amount_received'=>['required','numeric','min:0'],'currency'=>['nullable','string','max:10'],'reference_number'=>['nullable','string','max:255'],'description'=>['nullable','string'],'status'=>['required','boolean']]);
        $data['source_hash']=hash('sha256',implode('|',[$data['customer_number'],$data['company_code']??'',$data['document_number'],$data['posting_date'],number_format((float)$data['amount_received'],3,'.',''),$data['description']??'',Str::uuid()])); $data['synced_at']=now(); return $data;
    }
    private function refresh(): void { Cache::forget('sales-distribution.collections.stats'); app(MonthlyCollectionSummaryService::class)->refresh(); }
}
