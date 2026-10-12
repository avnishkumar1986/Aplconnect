<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistributorTarget extends Model
{
    protected $table = 'tbl_distributor_targets';
    protected $guarded = ['id'];
    protected $casts = ['target_amount' => 'decimal:2'];
    public function user() { return $this->belongsTo(UserProfile::class, 'user_id'); }
}
