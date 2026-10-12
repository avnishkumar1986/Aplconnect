<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistributorTier extends Model
{
    protected $table = 'tbl_distributor_tiers';
    protected $guarded = ['id'];
    protected $casts = [
        'minimum_annual_sales' => 'decimal:2',
        'maximum_discount_percent' => 'decimal:2',
        'status' => 'boolean',
    ];
}
