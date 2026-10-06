<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiSyncRun extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['started_at' => 'datetime', 'completed_at' => 'datetime']; }
    public function integration() { return $this->belongsTo(ApiIntegration::class, 'api_integration_id'); }
    public function attempts() { return $this->hasMany(ApiSyncAttempt::class); }

    public static function purgeExpired(): int
    {
        return static::query()->where('started_at', '<', now()->subDay())->delete();
    }
}
