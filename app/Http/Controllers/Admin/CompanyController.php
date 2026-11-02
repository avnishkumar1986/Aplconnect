<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Material;
use App\Models\MaterialStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    /** @var array<string, string> */
    private const COMPANY_MATERIAL_TYPES = [
        '1000' => 'ZRAW',
        '5000' => 'YRAW',
    ];

    public function index(Request $request): Response
    {
        $options = config('datatable.page_size_options');
        $perPage = (int) $request->integer('per_page', config('datatable.per_page'));
        if (! in_array($perPage, $options, true)) {
            $perPage = (int) config('datatable.per_page');
        }
        $search = trim((string) $request->string('search'));
        $sort = (string) $request->string('sort', 'name');
        $direction = $request->string('direction')->lower()->value() === 'desc' ? 'desc' : 'asc';
        $sortable = ['code' => 'company_code', 'name' => 'company_name', 'type' => 'record_type', 'status' => 'status', 'created_at' => 'created_at'];
        if (! isset($sortable[$sort])) {
            $sort = 'name';
        }

        $companies = Company::query()
            ->with([
                'address' => fn ($query) => $query->whereIn('user_type', ['USR9', 'USR10', 'USR11']),
                'contact' => fn ($query) => $query->whereIn('usr_type', ['USR9', 'USR10', 'USR11']),
            ])
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('company_code', 'like', "%{$search}%")->orWhere('company_name', 'like', "%{$search}%")->orWhere('gstin', 'like', "%{$search}%")->orWhere('pan', 'like', "%{$search}%")))
            ->orderByRaw('CASE WHEN record_type = 1 THEN 0 ELSE 1 END')
            ->orderBy($sortable[$sort], $direction)
            ->paginate($perPage)
            ->withQueryString();

        $companies->getCollection()->each(function (Company $company): void {
            $expectedType = $this->detailUserTypeForRecord($company->record_type);
            if ($company->address && ($company->address->user_type !== $expectedType || (string) $company->address->user_id !== $company->company_code)) {
                $company->setRelation('address', null);
            }
            if ($company->contact && ($company->contact->usr_type !== $expectedType || (string) $company->contact->user_id !== $company->company_code)) {
                $company->setRelation('contact', null);
            }
        });

        return Inertia::render('admin/companies/index', [
            'companies' => $companies,
            'parentCompanies' => Company::query()->where('status', true)->where('record_type', 1)->orderBy('company_name')->get(['company_code', 'company_name']),
            'filters' => ['search' => $search, 'per_page' => $perPage, 'sort' => $sort, 'direction' => $direction],
            'pageSizeOptions' => $options,
            'stats' => ['total' => Company::count(), 'active' => Company::where('status', true)->count()],
            'canManage' => $request->user()?->can('users.manage') ?? false,
        ]);
    }

    public function hierarchy(Request $request): Response
    {
        $companies = Company::query()
            ->where('company_code', '!=', '0')
            ->orderBy('record_type')
            ->orderBy('company_name')
            ->get(['id', 'company_code', 'company_name', 'record_type', 'parent_company_id', 'status']);

        $childrenByParent = $companies->whereNotNull('parent_company_id')->groupBy('parent_company_id');
        $roots = $companies->filter(fn (Company $company): bool => $company->record_type === 1 && $company->parent_company_id === null);
        $knownCodes = $companies->pluck('company_code');
        $orphans = $companies->filter(fn (Company $company): bool => $company->record_type !== 3 && $company->parent_company_id !== null && ! $knownCodes->contains($company->parent_company_id));

        $view = in_array($request->string('view')->value(), ['plants', 'materials'], true) ? $request->string('view')->value() : 'companies';
        $materialsByCompany = collect();
        if ($view === 'materials') {
            $companies->where('record_type', 1)->each(function (Company $company) use ($childrenByParent, $materialsByCompany): void {
                $materialType = self::COMPANY_MATERIAL_TYPES[$company->company_code] ?? null;
                if ($materialType === null) {
                    return;
                }

                $materials = Material::query()
                    ->where('mtart', $materialType)
                    ->orderBy('matkl')
                    ->orderBy('maktx')
                    ->orderBy('matnr')
                    ->get(['matnr', 'matkl', 'maktx', 'status']);

                $plantCodes = $childrenByParent->get($company->company_code, collect())
                    ->where('record_type', 2)
                    ->pluck('company_code');
                $stockByMaterial = MaterialStock::query()
                    ->whereIn('plant_code', $plantCodes)
                    ->whereIn('matnr', $materials->pluck('matnr'))
                    ->select('matnr')
                    ->selectRaw('SUM(current_stock) as total_stock')
                    ->groupBy('matnr')
                    ->pluck('total_stock', 'matnr');

                $groups = $materials
                    ->groupBy(fn (Material $material): string => filled($material->matkl) ? $material->matkl : 'Unclassified')
                    ->map(fn (Collection $group, $code): array => [
                        'code' => (string) $code,
                        'total' => $group->count(),
                        'items' => $group->map(fn (Material $material): array => [
                            'code' => $material->matnr,
                            'name' => $material->maktx ?: $material->matnr,
                            'stock' => (float) ($stockByMaterial->get($material->matnr) ?? 0),
                            'active' => (bool) $material->status,
                        ])->values()->all(),
                    ])->values();

                $materialsByCompany->put($company->company_code, [
                    'type' => $materialType,
                    'total' => $materials->count(),
                    'stock' => (float) $materials->sum(fn (Material $material): float => (float) ($stockByMaterial->get($material->matnr) ?? 0)),
                    'groups' => $groups,
                ]);
            });
        }

        return Inertia::render('admin/companies/hierarchy', [
            'view' => $view,
            'materialsByCompany' => $materialsByCompany,
            'hierarchy' => $roots->map(fn (Company $company): array => $this->hierarchyNode($company, $childrenByParent))->values(),
            'orphans' => $orphans->map(fn (Company $company): array => $this->hierarchyNode($company, $childrenByParent))->values(),
            'stats' => [
                'companies' => $companies->where('record_type', 1)->count(),
                'plants' => $companies->where('record_type', 2)->count(),
                'head_offices' => $companies->where('record_type', 3)->count(),
                'active' => $companies->where('status', true)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data): void {
            $company = Company::create($this->companyData($data));
            $address = Address::create($this->addressData($data, $company));
            $contact = Contact::create($this->contactData($data, $company));
            $company->update(['address_id' => $address->id, 'contact_id' => $contact->id]);
        });

        return back()->with('success', 'Company created successfully.');
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $data = $this->validated($request, $company);
        DB::transaction(function () use ($company, $data): void {
            $company->update($this->companyData($data));
            $address = $company->address;
            if (! $address || $address->user_type !== $data['usr_type'] || (string) $address->user_id !== $company->company_code) {
                $address = new Address;
            }
            $address->fill($this->addressData($data, $company))->save();
            $contact = $company->contact;
            if (! $contact || $contact->usr_type !== $data['usr_type'] || (string) $contact->user_id !== $company->company_code) {
                $contact = new Contact;
            }
            $contact->fill($this->contactData($data, $company))->save();
            $company->update(['address_id' => $address->id, 'contact_id' => $contact->id]);
        });

        return back()->with('success', 'Company updated successfully.');
    }

    public function toggleStatus(Request $request, Company $company): RedirectResponse
    {
        $company->update(['status' => $request->boolean('active')]);

        return back()->with('success', 'Company status updated.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $company->delete();

        return back()->with('success', 'Company deleted successfully.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer', Rule::exists('tbl_company', 'id')], 'action' => ['required', Rule::in(['activate', 'deactivate', 'delete'])]]);
        $query = Company::whereIn('id', $data['ids']);
        match ($data['action']) {
            'activate' => $query->update(['status' => true]), 'deactivate' => $query->update(['status' => false]), 'delete' => $query->delete()
        };

        return back()->with('success', 'Bulk action completed successfully.');
    }

    private function validated(Request $request, ?Company $company = null): array
    {
        return $request->validate([
            'company_code' => ['required', 'string', 'max:30', Rule::unique('tbl_company', 'company_code')->ignore($company?->id)],
            'company_name' => ['required', 'string', 'max:255', Rule::unique('tbl_company', 'company_name')->ignore($company?->id)],
            'usr_type' => ['required', 'string', Rule::in(['USR9', 'USR10', 'USR11'])],
            'parent_company_id' => ['nullable', 'string', 'max:30', Rule::exists('tbl_company', 'company_code'), Rule::notIn([$company?->company_code])],
            'gstin' => ['nullable', 'string', 'size:15'], 'pan' => ['nullable', 'string', 'size:10'], 'status' => ['required', 'boolean'],
            'address_line_1' => ['nullable', 'string', 'max:255'], 'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'], 'state' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'], 'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'], 'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'google_maps_url' => ['nullable', 'url', 'max:8000'],
            'email' => ['nullable', 'email:rfc', 'max:255'], 'contact_number' => ['nullable', 'string', 'max:30'],
        ], ['parent_company_id.not_in' => 'A company cannot be its own parent.']);
    }

    private function companyData(array $data): array
    {
        return collect($data)->only(['company_code', 'company_name', 'parent_company_id', 'gstin', 'pan', 'status'])->merge([
            'record_type' => match ($data['usr_type']) {
                'USR9' => 2,
                'USR11' => 3,
                default => 1,
            },
        ])->all();
    }

    private function addressData(array $data, Company $company): array
    {
        return collect($data)->only(['address_line_1', 'address_line_2', 'city', 'state', 'district', 'postal_code', 'country', 'latitude', 'longitude', 'google_maps_url'])->merge([
            'user_type' => $data['usr_type'], 'user_id' => $company->company_code, 'address_type' => 'primary',
            'is_primary' => true, 'status' => true,
        ])->all();
    }

    private function contactData(array $data, Company $company): array
    {
        return collect($data)->only(['email', 'contact_number'])->merge([
            'usr_type' => $data['usr_type'], 'user_id' => $company->company_code, 'contact_type' => 'primary',
            'is_primary' => true, 'is_verified' => false, 'status' => true,
        ])->all();
    }

    private function detailUserTypeForRecord(int $recordType): string
    {
        return match ($recordType) {
            2 => 'USR9',
            3 => 'USR11',
            default => 'USR10',
        };
    }

    /**
     * @param  Collection<string, Collection<int, Company>>  $childrenByParent
     * @return array{id: int, code: string, name: string, type: int, parent_code: string|null, active: bool, children: array<int, mixed>}
     */
    private function hierarchyNode(Company $company, $childrenByParent): array
    {
        return [
            'id' => $company->id,
            'code' => $company->company_code,
            'name' => $company->company_name,
            'type' => $company->record_type,
            'parent_code' => $company->parent_company_id,
            'active' => $company->status,
            'children' => $childrenByParent->get($company->company_code, collect())
                ->sortBy(fn (Company $child): int => $child->record_type === 3 ? 0 : $child->record_type)
                ->map(fn (Company $child): array => $this->hierarchyNode($child, $childrenByParent))
                ->values()
                ->all(),
        ];
    }
}
