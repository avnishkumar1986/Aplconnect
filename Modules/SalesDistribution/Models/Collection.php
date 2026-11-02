<?php
namespace Modules\SalesDistribution\Models;
use Illuminate\Database\Eloquent\Model;
class Collection extends Model
{
    protected $table = 'tbl_customer_collection_transactions';
    protected $guarded = ['id'];
    protected function casts(): array { return ['posting_date'=>'date:Y-m-d','document_date'=>'date:Y-m-d','amount_received'=>'decimal:3','status'=>'boolean','synced_at'=>'datetime']; }
}
