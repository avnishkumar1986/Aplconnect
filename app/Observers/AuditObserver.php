<?php

namespace App\Observers;

use App\Models\ActionLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditObserver
{
    public function created(Model $model): void
    {
        $this->write('Created', $model, ['attributes' => $this->safe($model->getAttributes())]);
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        unset($changes['updated_at']);
        if ($changes !== []) {
            $this->write('Updated', $model, ['changes' => $this->safe($changes)]);
        }
    }

    public function deleted(Model $model): void
    {
        $this->write('Deleted', $model, ['attributes' => $this->safe($model->getOriginal())]);
    }

    private function write(string $type, Model $model, array $data): void
    {
        ActionLog::create([
            'type' => $type,
            'title' => Str::headline(class_basename($model)).' '.strtolower($type).' (#'.$model->getKey().')',
            'action_by' => auth()->id(),
            'data' => $data + ['model' => $model::class, 'record_id' => $model->getKey()],
            'ip_address' => request()?->ip(),
            'user_agent' => Str::limit((string) request()?->userAgent(), 1000, ''),
        ]);
    }

    private function safe(array $values): array
    {
        return collect($values)->except(['password', 'token', 'remember_token'])->all();
    }
}
