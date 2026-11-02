<?php

namespace Modules\SalesDistribution\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSalesSummary extends Model
{
    protected $table = 'tbl_customer_sales_summary';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'year_to_date_sales' => 'decimal:3',
            'last_year_sales' => 'decimal:3',
            'pending_sales' => 'decimal:3',
            'last_month_sales' => 'decimal:3',
            'current_month_sales' => 'decimal:3',
            'two_months_ago_sales' => 'decimal:3',
            'last_month_outstanding' => 'decimal:3',
            'current_month_outstanding' => 'decimal:3',
            'two_months_ago_outstanding' => 'decimal:3',
            'last_month_overdue' => 'decimal:3',
            'current_month_overdue' => 'decimal:3',
            'two_months_ago_overdue' => 'decimal:3',
            'last_month_payment_received' => 'decimal:3',
            'current_month_payment_received' => 'decimal:3',
            'two_months_ago_payment_received' => 'decimal:3',
            'credit_limit' => 'decimal:3',
            'api_payload' => 'array',
            'synced_at' => 'datetime',
            'status' => 'boolean',
        ];
    }
}
