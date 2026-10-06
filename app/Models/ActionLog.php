<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActionLog extends Model
{
    protected $table = 'tbl_action_logs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function actor()
    {
        return $this->belongsTo(Login::class, 'action_by');
    }
}
