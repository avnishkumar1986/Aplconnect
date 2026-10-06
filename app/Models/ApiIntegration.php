<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiIntegration extends Model
{
    protected $guarded = ['id', 'last_success_at', 'last_failure_at', 'last_error'];

    protected function casts(): array
    {
        return ['verify_ssl' => 'boolean', 'status' => 'boolean', 'last_success_at' => 'datetime', 'last_failure_at' => 'datetime'];
    }

    public function runs()
    {
        return $this->hasMany(ApiSyncRun::class);
    }

    public static function currentEnvironmentCode(): string
    {
        return config('api-integrations.environment') === 'production' ? 'production' : 'quality';
    }

    public static function currentEnvironmentProfile(): ?self
    {
        return static::query()->where('code', static::currentEnvironmentCode())->first();
    }
}
