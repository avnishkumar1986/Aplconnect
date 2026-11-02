<?php

namespace Modules\SalesDistribution\Models;

use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Model;

class PendingSale extends Model
{
    protected $table = 'tbl_pending_sales';

    protected $fillable = [
        'customer_number', 'plant_code', 'sales_document_number', 'document_date',
        'external_material_group', 'material_group', 'material_number', 'order_quantity',
        'amount', 'open_delivery_amount', 'delivered_quantity', 'material_text_1',
        'material_text_2', 'material_text_3', 'api_payload', 'synced_at', 'status',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date:Y-m-d',
            'order_quantity' => 'decimal:3',
            'amount' => 'decimal:2',
            'open_delivery_amount' => 'decimal:2',
            'delivered_quantity' => 'decimal:3',
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
