<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModulePackage extends Model
{
    protected $table = 'tbl_modules';

    protected $fillable = ['name', 'slug', 'description', 'version', 'source', 'package_path', 'original_filename', 'mime_type', 'file_size', 'checksum_sha256', 'manifest', 'display_locations', 'status', 'uploaded_by'];

    protected function casts(): array
    {
        return ['manifest' => 'array', 'display_locations' => 'array', 'status' => 'boolean', 'file_size' => 'integer'];
    }

    public function getDisplayLocationsAttribute(mixed $value): array
    {
        $locations = is_array($value) ? $value : json_decode((string) $value, true);

        return is_array($locations) && $locations !== [] ? $locations : ['sidebar'];
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(ModuleUpload::class, 'module_id');
    }
}
