<?php

namespace Modules\SalesDistribution\Models;

use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $table = 'tbl_dailysale';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'billing_date' => 'date:Y-m-d',
            'billing_year' => 'integer',
            'billing_month' => 'integer',
            'record_creation_date' => 'date:Y-m-d',
            'billed_quantity' => 'decimal:3',
            'billed_quantity_kg' => 'decimal:3',
            'billed_quantity_liters' => 'decimal:3',
            'billed_quantity_meters' => 'decimal:3',
            'net_value' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'condition_value' => 'decimal:2',
            'api_payload' => 'array',
            'synced_at' => 'datetime',
            'status' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating([HasAuditFields::class, 'creating']);
        static::updating([HasAuditFields::class, 'updating']);
    }
}
