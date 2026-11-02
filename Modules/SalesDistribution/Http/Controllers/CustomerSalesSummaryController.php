<?php

namespace Modules\SalesDistribution\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\SalesDistribution\Models\CustomerSalesSummary;

class CustomerSalesSummaryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search'));
        $perPage = (int) config('datatable.per_page', 50);
        $matchingCustomerNumbers = $search === '' ? [] : DB::table('tbl_users')
            ->where('user_type', 'USR4')
            ->where('name', 'like', "%{$search}%")
            ->limit(1000)
            ->pluck('unique_id')
            ->map(fn ($value): string => (string) $value)
            ->all();
        $summaries = CustomerSalesSummary::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('customer_number', 'like', "%{$search}%")
                ->when($matchingCustomerNumbers !== [], fn ($query) => $query->orWhereIn('customer_number', $matchingCustomerNumbers))
                ->orWhere('payment_terms_code', 'like', "%{$search}%")
                ->orWhere('payment_terms_description', 'like', "%{$search}%")))
            ->orderByDesc('synced_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $customerNames = DB::table('tbl_users')
            ->where('user_type', 'USR4')
            ->whereIn('unique_id', $summaries->getCollection()->pluck('customer_number')->all())
            ->pluck('name', 'unique_id');
        $summaries->getCollection()->transform(function (CustomerSalesSummary $summary) use ($customerNames): CustomerSalesSummary {
            $summary->setAttribute('customer_name', $customerNames->get($summary->customer_number));

            return $summary;
        });

        $stats=Cache::remember('sales-distribution.customer-summary.stats',now()->addMinutes(10),fn()=>[
            'total'=>CustomerSalesSummary::count(),'active'=>CustomerSalesSummary::where('status',true)->count(),
            'yearToDateSales'=>(float)CustomerSalesSummary::sum('year_to_date_sales'),'currentMonthSales'=>(float)CustomerSalesSummary::sum('current_month_sales'),
            'currentOutstanding'=>(float)CustomerSalesSummary::sum('current_month_outstanding'),
        ]);
        return Inertia::render('modules/salesdistribution/customer-sales-summary/index', [
            'summaries' => $summaries,
            'filters' => ['search' => $search, 'per_page' => $perPage],
            'stats' => $stats,
            'canManage' => $request->user()?->can('sales_distribution.customer_sales_summary.manage') ?? false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        CustomerSalesSummary::create($this->validated($request));
        $this->forgetStats();

        return back()->with('success', 'Customer sales summary created successfully.');
    }

    public function update(Request $request, CustomerSalesSummary $customerSalesSummary): RedirectResponse
    {
        $customerSalesSummary->update($this->validated($request, $customerSalesSummary));
        $this->forgetStats();

        return back()->with('success', 'Customer sales summary updated successfully.');
    }

    public function toggleStatus(Request $request, CustomerSalesSummary $customerSalesSummary): RedirectResponse
    {
        $customerSalesSummary->update(['status' => $request->validate(['active' => ['required', 'boolean']])['active']]);
        $this->forgetStats();

        return back()->with('success', 'Customer sales summary status updated.');
    }

    public function bulkAction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', Rule::exists('tbl_customer_sales_summary', 'id')],
            'action' => ['required', Rule::in(['activate', 'deactivate', 'delete'])],
        ]);
        $query = CustomerSalesSummary::whereIn('id', $data['ids']);
        $data['action'] === 'delete'
            ? $query->delete()
            : $query->update(['status' => $data['action'] === 'activate', 'updated_at' => now()]);
        $this->forgetStats();

        return back()->with('success', 'Customer sales summaries updated successfully.');
    }

    public function import(Request $request): RedirectResponse
    {
        $file = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:20480'],
        ])['file'];
        $rows = $this->spreadsheetRows($file);

        if ($rows === []) {
            return back()->withErrors(['file' => 'The spreadsheet has no data rows.']);
        }

        $aliases = $this->importAliases();
        $numericFields = array_values(array_unique(array_filter($aliases, fn (string $field): bool => ! in_array($field, [
            'customer_number', 'payment_terms_code', 'payment_terms_description',
        ], true))));
        $records = [];
        $invalid = [];
        $now = now();

        foreach ($rows as $index => $row) {
            $mapped = [];
            foreach ($row as $header => $value) {
                if (isset($aliases[$header])) {
                    $mapped[$aliases[$header]] = $value;
                }
            }

            $customerNumber = trim((string) ($mapped['customer_number'] ?? ''));
            if ($customerNumber === '') {
                $invalid[] = 'Row '.($index + 2).': customer code/number is required.';
                continue;
            }

            foreach ($numericFields as $field) {
                $value = trim((string) ($mapped[$field] ?? '0'));
                $value = str_replace([',', '₹', ' '], '', $value);
                if ($value === '' || $value === '-') {
                    $value = '0';
                }
                if (! is_numeric($value)) {
                    $invalid[] = 'Row '.($index + 2).": {$field} must be numeric.";
                    continue 2;
                }
                $mapped[$field] = $value;
            }

            $records[$customerNumber] = [
                'customer_number' => $customerNumber,
                ...array_fill_keys($numericFields, 0),
                ...$mapped,
                'payment_terms_code' => Str::limit(trim((string) ($mapped['payment_terms_code'] ?? '')), 50, ''),
                'payment_terms_description' => Str::limit(trim((string) ($mapped['payment_terms_description'] ?? '')), 255, ''),
                'api_payload' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'synced_at' => $now,
                'status' => true,
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($records === []) {
            return back()->withErrors(['file' => implode(' ', array_slice($invalid, 0, 5)) ?: 'No valid rows were found.']);
        }

        $customerNumbers = array_keys($records);
        $existing = CustomerSalesSummary::query()->whereIn('customer_number', $customerNumbers)->pluck('customer_number')->unique()->values()->all();
        $updated = count($existing);
        $created = count($records) - $updated;
        $updateFields = array_values(array_diff(
            array_keys(reset($records)),
            ['customer_number', 'created_by', 'created_at'],
        ));
        foreach (array_chunk(array_values($records), 250) as $chunk) {
            DB::table('tbl_customer_sales_summary')->upsert(
                $chunk,
                ['customer_number'],
                $updateFields,
            );
        }
        $this->forgetStats();

        $message = "{$created} summaries created and {$updated} updated.";
        if ($invalid !== []) {
            $message .= ' '.count($invalid).' invalid rows skipped: '.implode(' ', array_slice($invalid, 0, 3));
        }

        return back()->with('success', $message);
    }

    public function destroy(CustomerSalesSummary $customerSalesSummary): RedirectResponse
    {
        $customerSalesSummary->delete();
        $this->forgetStats();

        return back()->with('success', 'Customer sales summary deleted permanently.');
    }

    private function validated(Request $request, ?CustomerSalesSummary $summary = null): array
    {
        return $request->validate([
            'customer_number' => ['required', 'string', 'max:50', Rule::unique('tbl_customer_sales_summary', 'customer_number')->ignore($summary?->id)],
            'year_to_date_sales' => ['required', 'numeric'], 'last_year_sales' => ['required', 'numeric'], 'pending_sales' => ['required', 'numeric'],
            'last_month_sales' => ['required', 'numeric'], 'current_month_sales' => ['required', 'numeric'], 'two_months_ago_sales' => ['required', 'numeric'],
            'last_month_outstanding' => ['required', 'numeric'], 'current_month_outstanding' => ['required', 'numeric'], 'two_months_ago_outstanding' => ['required', 'numeric'],
            'last_month_overdue' => ['required', 'numeric'], 'current_month_overdue' => ['required', 'numeric'], 'two_months_ago_overdue' => ['required', 'numeric'],
            'last_month_payment_received' => ['required', 'numeric'], 'current_month_payment_received' => ['required', 'numeric'], 'two_months_ago_payment_received' => ['required', 'numeric'],
            'payment_terms_code' => ['nullable', 'string', 'max:50'], 'payment_terms_description' => ['nullable', 'string', 'max:255'],
            'credit_limit' => ['required', 'numeric'], 'status' => ['required', 'boolean'],
        ]);
    }
    private function forgetStats(): void { Cache::forget('sales-distribution.customer-summary.stats'); }

    /** @return array<string, string> */
    private function importAliases(): array
    {
        return [
            'customer_code' => 'customer_number', 'customercode' => 'customer_number', 'customer_number' => 'customer_number', 'customernumber' => 'customer_number',
            'current_year_sale' => 'year_to_date_sales', 'currentyearsale' => 'year_to_date_sales', 'year_to_date_sales' => 'year_to_date_sales', 'yeartodatesales' => 'year_to_date_sales',
            'last_year_sale' => 'last_year_sales', 'lastyearsale' => 'last_year_sales', 'last_year_sales' => 'last_year_sales',
            'pending_current_year_sale' => 'pending_sales', 'pendingcurrentyearsale' => 'pending_sales', 'pending_sales' => 'pending_sales',
            'last_month_sale' => 'last_month_sales', 'lastmonthsale' => 'last_month_sales', 'last_month_sales' => 'last_month_sales',
            'current_month_sale' => 'current_month_sales', 'currentmonthsale' => 'current_month_sales', 'current_month_sales' => 'current_month_sales',
            'last2last_month_sale' => 'two_months_ago_sales', 'last2lastmonthsale' => 'two_months_ago_sales', 'two_months_ago_sales' => 'two_months_ago_sales',
            'last_month_outstanding' => 'last_month_outstanding', 'lastmonthoutstanding' => 'last_month_outstanding',
            'current_month_outstanding' => 'current_month_outstanding', 'currentmonthoutstanding' => 'current_month_outstanding',
            'last2last_month_outstanding' => 'two_months_ago_outstanding', 'last2lastmonthoutstanding' => 'two_months_ago_outstanding', 'two_months_ago_outstanding' => 'two_months_ago_outstanding',
            'last_month_overdue' => 'last_month_overdue', 'lastmonthoverdue' => 'last_month_overdue',
            'current_month_overdue' => 'current_month_overdue', 'currentmonthoverdue' => 'current_month_overdue',
            'last2last_month_overdue' => 'two_months_ago_overdue', 'last2lastmonthoverdue' => 'two_months_ago_overdue', 'two_months_ago_overdue' => 'two_months_ago_overdue',
            'last_month_payment_rcvd' => 'last_month_payment_received', 'lastmonthpaymentrcvd' => 'last_month_payment_received', 'last_month_payment_received' => 'last_month_payment_received',
            'current_month_payment_rcvd' => 'current_month_payment_received', 'currentmonthpaymentrcvd' => 'current_month_payment_received', 'current_month_payment_received' => 'current_month_payment_received',
            'last2last_month_payment_rcvd' => 'two_months_ago_payment_received', 'last2lastmonthpaymentrcvd' => 'two_months_ago_payment_received', 'two_months_ago_payment_received' => 'two_months_ago_payment_received',
            'zterm' => 'payment_terms_code', 'payment_terms_code' => 'payment_terms_code',
            'text' => 'payment_terms_description', 'payment_terms_description' => 'payment_terms_description',
            'credit_limit' => 'credit_limit', 'creditlimit' => 'credit_limit',
        ];
    }

    /** @return array<int, array<string, string>> */
    private function spreadsheetRows(UploadedFile $file): array
    {
        if (strtolower($file->getClientOriginalExtension()) !== 'xlsx') {
            $handle = fopen($file->getRealPath(), 'rb');
            $raw = [];
            $firstLine = (string) fgets($handle);
            rewind($handle);
            $delimiter = substr_count($firstLine, "\t") > substr_count($firstLine, ',') ? "\t" : ',';
            if (substr_count($firstLine, ';') > substr_count($firstLine, $delimiter)) {
                $delimiter = ';';
            }
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $raw[] = $row;
            }
            fclose($handle);
        } else {
            $zip = new \ZipArchive;
            if ($zip->open($file->getRealPath()) !== true) {
                throw ValidationException::withMessages(['file' => 'Unable to read the Excel file.']);
            }
            $shared = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $strings = simplexml_load_string($xml);
                foreach ($strings->si as $item) {
                    $parts = $item->xpath('.//t') ?: [];
                    $shared[] = implode('', array_map(fn ($part): string => (string) $part, $parts));
                }
            }
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            if ($xml === false) {
                throw ValidationException::withMessages(['file' => 'The first worksheet could not be found.']);
            }
            $sheet = simplexml_load_string($xml);
            $raw = [];
            foreach ($sheet->sheetData->row as $row) {
                $values = [];
                $highestColumn = -1;
                foreach ($row->c as $cell) {
                    preg_match('/([A-Z]+)/', (string) $cell['r'], $match);
                    $column = 0;
                    foreach (str_split($match[1] ?? 'A') as $letter) {
                        $column = ($column * 26) + ord($letter) - 64;
                    }
                    $index = $column - 1;
                    $value = (string) $cell->v;
                    if ((string) $cell['t'] === 's') {
                        $value = $shared[(int) $value] ?? '';
                    } elseif ((string) $cell['t'] === 'inlineStr') {
                        $parts = $cell->xpath('.//t') ?: [];
                        $value = implode('', array_map(fn ($part): string => (string) $part, $parts));
                    }
                    $values[$index] = $value;
                    $highestColumn = max($highestColumn, $index);
                }
                if ($highestColumn >= 0) {
                    $raw[] = array_map(fn (int $index): string => (string) ($values[$index] ?? ''), range(0, $highestColumn));
                }
            }
        }

        if (count($raw) < 2) {
            return [];
        }
        $headers = array_map(fn ($header): string => Str::of((string) $header)->trim()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value(), array_shift($raw));

        return array_values(array_filter(array_map(function (array $row) use ($headers): array {
            $row = array_pad($row, count($headers), '');

            return array_combine($headers, array_slice($row, 0, count($headers)));
        }, $raw), fn (array $row): bool => count(array_filter($row, fn ($value): bool => trim((string) $value) !== '')) > 0));
    }
}
