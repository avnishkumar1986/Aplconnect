<?php

namespace App\Http\Controllers;

use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ModuleController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('modules.view');
        $this->discoverLocalModules();

        $query = Module::query();

        if ($search = trim((string) $request->q)) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('version', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return view('modules.index', [
            'modules' => $query->latest()->paginate(config('app.table_page_length'))->withQueryString(),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('modules.create');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'version' => ['required', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'module_file' => ['required', 'file', 'mimes:zip', 'max:20480'],
        ]);

        $slug = Str::slug($validated['name']);
        $request->validate(['name' => [Rule::unique('tbl_modules', 'name')]]);
        $file = $request->file('module_file');
        $path = $file->storeAs(
            'modules',
            $slug.'-'.Str::slug($validated['version']).'-'.Str::random(8).'.zip'
        );

        Module::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'version' => $validated['version'],
            'description' => $validated['description'] ?? null,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path' => $path,
            'source_type' => 'upload',
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'status' => $validated['status'],
            'uploaded_by' => auth()->id(),
        ]);

        return back()->with('success', 'Module uploaded successfully.');
    }

    public function destroy(Module $module)
    {
        Gate::authorize('modules.delete');
        if ($module->source_type === 'upload') {
            Storage::delete($module->stored_path);
        }
        $module->delete();

        return back()->with('success', 'Module removed successfully.');
    }

    public function toggleStatus(Module $module)
    {
        Gate::authorize('modules.edit');
        $module->update([
            'status' => $module->status === 'active' ? 'inactive' : 'active',
        ]);

        if (request()->expectsJson()) {
            return response()->json([
                'active' => $module->status === 'active',
                'label' => ucfirst($module->status),
                'module_slug' => $module->slug,
                'message' => $module->name.' has been '.($module->status === 'active' ? 'enabled' : 'disabled').'.',
            ]);
        }

        return back()->with(
            'success',
            $module->name.' has been '.($module->status === 'active' ? 'enabled' : 'disabled').'.'
        );
    }

    private function discoverLocalModules(): void
    {
        $moduleRoot = base_path('Modules');
        File::ensureDirectoryExists($moduleRoot);

        foreach (File::directories($moduleRoot) as $directory) {
            $folder = basename($directory);
            if (str_starts_with($folder, '.')) {
                continue;
            }

            $relativePath = 'Modules/'.$folder;
            $manifestPath = $directory.DIRECTORY_SEPARATOR.'module.json';
            $manifest = [];
            if (File::exists($manifestPath)) {
                $decoded = json_decode(File::get($manifestPath), true);
                $manifest = is_array($decoded) ? $decoded : [];
            }

            $slugBase = Str::slug($manifest['slug'] ?? $folder) ?: 'module';
            $slug = $slugBase;
            $suffix = 2;
            while (Module::where('slug', $slug)->where('stored_path', '!=', $relativePath)->exists()) {
                $slug = $slugBase.'-'.$suffix++;
            }

            $existingModule = Module::where('stored_path', $relativePath)
                ->where('source_type', 'discovered')
                ->first();

            $attributes = [
                'name' => trim((string) ($manifest['name'] ?? Str::headline($folder))),
                'slug' => $slug,
                'version' => trim((string) ($manifest['version'] ?? '1.0.0')),
                'description' => $manifest['description'] ?? 'Automatically detected local module.',
                'original_filename' => $folder,
                'mime_type' => 'application/x-directory',
                'file_size' => collect(File::allFiles($directory))->sum(fn ($file) => $file->getSize()),
                'uploaded_by' => auth()->id(),
            ];

            if (! $existingModule) {
                $attributes['status'] = in_array(($manifest['status'] ?? 'active'), ['active', 'inactive'], true)
                    ? ($manifest['status'] ?? 'active')
                    : 'active';
            }

            Module::updateOrCreate(
                ['stored_path' => $relativePath, 'source_type' => 'discovered'],
                $attributes
            );
        }

        Module::where('source_type', 'discovered')->get()->each(function (Module $module) {
            if (! File::isDirectory(base_path($module->stored_path))) {
                $module->delete();
            }
        });
    }
}
