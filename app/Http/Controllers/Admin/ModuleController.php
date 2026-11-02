<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModulePackage;
use App\Models\ModuleUpload;
use App\Services\Modules\ModuleRegistryService;
use App\Services\Modules\ModulePermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ModuleController extends Controller
{
    public function __construct(private readonly ModulePermissionService $modulePermissions) {}

    public function index(Request $request, ModuleRegistryService $registry): Response
    {
        $options = config('datatable.page_size_options');
        $perPage = (int) $request->integer('per_page', config('datatable.per_page'));
        if (! in_array($perPage, $options, true)) {
            $perPage = (int) config('datatable.per_page');
        }
        $search = trim((string) $request->string('search'));

        $modules = ModulePackage::query()->withCount('uploads')->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")))->latest()->paginate($perPage)->withQueryString();
        $modules->getCollection()->transform(function (ModulePackage $module) use ($registry) {
            $displayLocations = is_array($module->display_locations)
                ? array_values(array_intersect($module->display_locations, ['sidebar', 'tabs', 'dropdown']))
                : [];

            $module->setAttribute('display_locations', $displayLocations ?: ['sidebar']);
            $module->setAttribute('installed', $registry->isInstalled($module));
            $module->setAttribute('service_details', $registry->details($module));

            return $module;
        });

        $activeModules = ModulePackage::query()
            ->where('status', true)
            ->get()
            ->filter(fn (ModulePackage $module): bool => $registry->isInstalled($module))
            ->count();

        return Inertia::render('admin/modules/index', [
            'modules' => $modules,
            'filters' => ['search' => $search, 'per_page' => $perPage],
            'pageSizeOptions' => $options,
            'stats' => ['total' => ModulePackage::count(), 'active' => $activeModules],
            'canManage' => $request->user()?->can('users.manage') ?? false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $file = $request->file('package');
        $slug = Str::slug($data['slug'] ?: $data['name']);
        if ($slug === '' || ModulePackage::where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['slug' => $slug === '' ? 'Enter a valid module name or slug.' : 'This module slug already exists.']);
        }
        $path = $file->storeAs("modules/{$slug}/{$data['version']}", Str::uuid().'.zip', 'local');
        $checksum = hash_file('sha256', $file->getRealPath());

        try {
            DB::transaction(function () use ($request, $data, $file, $slug, $path, $checksum) {
                $module = ModulePackage::create([
                    ...$data, 'slug' => $slug, 'source' => 'upload', 'package_path' => $path,
                    'original_filename' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(), 'checksum_sha256' => $checksum,
                    'manifest' => $this->manifest($file->getRealPath()), 'uploaded_by' => $request->user()?->id,
                ]);
                ModuleUpload::create(['module_id' => $module->id, 'version' => $module->version, 'package_path' => $path, 'original_filename' => $file->getClientOriginalName(), 'file_size' => $file->getSize(), 'checksum_sha256' => $checksum, 'uploaded_by' => $request->user()?->id, 'uploaded_at' => now()]);
                $this->modulePermissions->sync($module);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'Module uploaded successfully.');
    }

    public function update(Request $request, ModulePackage $module): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'boolean'],
            'display_locations' => ['required', 'array', 'min:1'],
            'display_locations.*' => ['required', 'string', Rule::in(['sidebar', 'tabs', 'dropdown'])],
        ]);
        $module->update($data);
        $this->modulePermissions->sync($module->fresh());

        return back()->with('success', 'Module updated successfully.');
    }

    public function toggleStatus(Request $request, ModulePackage $module): RedirectResponse
    {
        $module->update(['status' => $request->boolean('active')]);
        $this->modulePermissions->sync($module->fresh());

        return back()->with('success', 'Module status updated.');
    }

    public function download(ModulePackage $module): StreamedResponse
    {
        abort_if($module->source === 'service', 404, 'Built-in services do not have a downloadable package.');
        abort_unless(Storage::disk('local')->exists($module->package_path), 404);

        return Storage::disk('local')->download($module->package_path, $module->original_filename);
    }

    public function destroy(ModulePackage $module): RedirectResponse
    {
        if ($module->source === 'service') {
            return back()->withErrors(['module' => 'Built-in Laravel services cannot be deleted. Disable the service instead.']);
        }
        $paths = $module->uploads()->pluck('package_path')->push($module->package_path)->unique()->all();
        DB::transaction(function () use ($module) {
            $module->uploads()->delete();
            $module->delete();
        });
        Storage::disk('local')->delete($paths);

        return back()->with('success', 'Module and its uploaded packages deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'regex:/^[a-z0-9-]+$/', Rule::unique('tbl_modules', 'slug')],
            'description' => ['nullable', 'string', 'max:2000'],
            'version' => ['required', 'string', 'max:40', 'regex:/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?$/'],
            'package' => ['required', 'file', 'mimes:zip', 'max:102400'],
            'status' => ['required', 'boolean'],
            'display_locations' => ['required', 'array', 'min:1'],
            'display_locations.*' => ['required', 'string', Rule::in(['sidebar', 'tabs', 'dropdown'])],
        ]);
    }

    private function manifest(string $path): ?array
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            return null;
        }
        $contents = $zip->getFromName('module.json');
        $zip->close();
        if ($contents === false) {
            return null;
        }
        $manifest = json_decode($contents, true);

        return is_array($manifest) ? $manifest : null;
    }
}
