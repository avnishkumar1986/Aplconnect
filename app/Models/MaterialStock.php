<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialStock extends Model
{
    protected $table = 'tbl_material_stocks';

    protected $fillable = [
        'matnr',
        'plant_code',
        'material_desc',
        'current_stock',
        'stock_value',
        'sap_current_month_consumption',
        'sap_previous_month_consumption',
        'sap_two_months_ago_consumption',
        'auto_monthly_consumption',
        'auto_perday_consumption',
        'man_perday_consumption',
        'status',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'current_stock' => 'decimal:3',
            'stock_value' => 'decimal:3',
            'sap_current_month_consumption' => 'decimal:3',
            'sap_previous_month_consumption' => 'decimal:3',
            'sap_two_months_ago_consumption' => 'decimal:3',
            'auto_monthly_consumption' => 'decimal:3',
            'auto_perday_consumption' => 'decimal:3',
            'man_perday_consumption' => 'decimal:3',
            'status' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }
}
