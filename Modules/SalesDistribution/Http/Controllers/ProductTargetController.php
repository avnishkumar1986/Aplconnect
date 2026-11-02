<?php

namespace Modules\SalesDistribution\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\SalesDistribution\Models\ProductTarget;

class ProductTargetController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $year = trim((string) $request->string('fiscal_year'));
        $perPage = (int) config('datatable.per_page', 50);
        $query = ProductTarget::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('customer_name', 'like', "%{$search}%")
                ->orWhere('customer_code', 'like', "%{$search}%")
                ->orWhere('state', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")))
            ->when($year !== '', fn ($query) => $query->where('fiscal_year', $year));

        $targets = (clone $query)->latest('id')->paginate($perPage)->withQueryString();
        $hierarchyCodes = $targets->getCollection()
            ->flatMap(fn (ProductTarget $target): array => [
                $target->l3_code, $target->l4_code, $target->l5_code, $target->l6_code,
            ])
            ->filter()
            ->unique()
            ->values();
        $employeeNames = DB::table('tbl_users')
            ->where('user_type', 'USR6')
            ->whereIn('unique_id', $hierarchyCodes)
            ->pluck('name', 'unique_id');
        $targets->getCollection()->transform(function (ProductTarget $target) use ($employeeNames): ProductTarget {
            foreach (['l3', 'l4', 'l5', 'l6'] as $level) {
                $code = (string) ($target->getAttribute("{$level}_code") ?? '');
                $target->setAttribute("{$level}_name", $code !== '' ? $employeeNames->get($code) : null);
            }

            return $target;
        });

        return Inertia::render('modules/salesdistribution/product-targets/index', [
            'targets' => $targets,
            'filters' => ['search' => $search, 'fiscal_year' => $year, 'per_page' => $perPage],
            'fiscalYears' => ProductTarget::query()->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year'),
            'stats' => [
                'records' => (clone $query)->count(),
                'customers' => (clone $query)->distinct('customer_code')->count('customer_code'),
                'total' => (float) (clone $query)->where('status', true)->sum('total_sale'),
            ],
            'hierarchyEmployees' => DB::table('tbl_users')
                ->where('user_type', 'USR6')
                ->where('status', true)
                ->whereNotNull('unique_id')
                ->where('unique_id', '<>', '')
                ->orderBy('name')
                ->get(['unique_id as code', 'name'])
                ->map(fn ($employee): array => [
                    'code' => (string) $employee->code,
                    'name' => (string) $employee->name,
                ]),
            'productColumns' => ProductTarget::productColumns(),
            'canManage' => $request->user()?->can('sales_distribution.product_targets.manage') ?? false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ProductTarget::create([...$this->validated($request), 'created_by' => $request->user()?->id, 'updated_by' => $request->user()?->id]);

        return back()->with('success', 'Product target created.');
    }

    public function update(Request $request, ProductTarget $productTarget): RedirectResponse
    {
        $productTarget->update([...$this->validated($request, $productTarget), 'updated_by' => $request->user()?->id]);

        return back()->with('success', 'Product target updated.');
    }

    public function destroy(ProductTarget $productTarget): RedirectResponse
    {
        $productTarget->delete();

        return back()->with('success', 'Product target deleted.');
    }

    public function import(Request $request): RedirectResponse
    {
        $file = $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:20480']])['file'];
        $handle = fopen($file->getRealPath(), 'rb');
        $headers = fgetcsv($handle);
        if (! is_array($headers)) {
            return back()->withErrors(['file' => 'The CSV header row is missing.']);
        }
        $headers = array_map(fn ($value) => strtolower(trim((string) $value)), $headers);
        $required = ['id', 'customer_name', 'customer_code', 'fiscal_year', 'total_sale'];
        if (array_diff($required, $headers) !== []) {
            fclose($handle);
            return back()->withErrors(['file' => 'CSV columns must include: '.implode(', ', $required).'.']);
        }

        $records = [];
        $invalid = [];
        $now = now();
        $rowNumber = 1;
        while (($values = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count($values) !== count($headers)) {
                $invalid[] = "Row {$rowNumber}: column count does not match the header.";
                continue;
            }
            $row = array_combine($headers, $values);
            $sourceId = filter_var($row['id'] ?? null, FILTER_VALIDATE_INT);
            if ($sourceId === false || trim((string) ($row['customer_name'] ?? '')) === '' || trim((string) ($row['customer_code'] ?? '')) === '') {
                $invalid[] = "Row {$rowNumber}: id, customer_name, and customer_code are required.";
                continue;
            }
            $record = [
                'source_id' => $sourceId,
                'customer_name' => trim((string) $row['customer_name']),
                'customer_code' => trim((string) $row['customer_code']),
                'state' => $this->nullable($row['state'] ?? null),
                'city' => $this->nullable($row['city'] ?? null),
                'month' => $this->nullable($row['month'] ?? null),
                'fiscal_year' => trim((string) ($row['fiscal_year'] ?? '')),
                'l3_code' => $this->nullable($row['l3_code'] ?? null),
                'l4_code' => $this->nullable($row['l4_code'] ?? null),
                'l5_code' => $this->nullable($row['l5_code'] ?? null),
                'l6_code' => $this->nullable($row['l6_code'] ?? null),
                'status' => true,
                'updated_by' => $request->user()?->id,
                'updated_at' => $now,
            ];
            foreach ([...ProductTarget::productColumns(), 'total_sale'] as $column) {
                $value = str_replace([',', ' '], '', trim((string) ($row[$column] ?? '0')));
                if ($value === '') {
                    $value = '0';
                }
                if (! is_numeric($value)) {
                    $invalid[] = "Row {$rowNumber}: {$column} must be numeric.";
                    continue 2;
                }
                $record[$column] = $value;
            }
            $record['created_by'] = $request->user()?->id;
            $record['created_at'] = $now;
            $records[$sourceId] = $record;
        }
        fclose($handle);

        if ($records === []) {
            return back()->withErrors(['file' => implode(' ', array_slice($invalid, 0, 5)) ?: 'No valid rows were found.']);
        }
        $existing = ProductTarget::query()->whereIn('source_id', array_keys($records))->count();
        $updateColumns = array_values(array_diff(array_keys(reset($records)), ['source_id', 'created_by', 'created_at']));
        foreach (array_chunk(array_values($records), 250) as $chunk) {
            DB::table('tbl_product_targets')->upsert($chunk, ['source_id'], $updateColumns);
        }

        $message = (count($records) - $existing)." targets created and {$existing} updated.";
        if ($invalid !== []) {
            $message .= ' '.count($invalid).' invalid rows skipped: '.implode(' ', array_slice($invalid, 0, 3));
        }

        return back()->with('success', $message);
    }

    private function validated(Request $request, ?ProductTarget $target = null): array
    {
        $rules = [
            'source_id' => ['nullable', 'integer', Rule::unique('tbl_product_targets', 'source_id')->ignore($target?->id)],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_code' => ['required', 'string', 'max:50'],
            'state' => ['nullable', 'string', 'max:100'], 'city' => ['nullable', 'string', 'max:100'],
            'month' => ['nullable', 'string', 'max:20'], 'fiscal_year' => ['required', 'string', 'max:10'],
            'l3_code' => ['nullable', 'string', 'max:50'], 'l4_code' => ['nullable', 'string', 'max:50'],
            'l5_code' => ['nullable', 'string', 'max:50'], 'l6_code' => ['nullable', 'string', 'max:50'],
            'total_sale' => ['required', 'numeric'], 'status' => ['required', 'boolean'],
        ];
        foreach (ProductTarget::productColumns() as $column) {
            $rules[$column] = ['required', 'numeric'];
        }

        return $request->validate($rules);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' || strtoupper($value) === 'NULL' ? null : $value;
    }
}
