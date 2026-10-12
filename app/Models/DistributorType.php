<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistributorType extends Model
{
    protected $table = 'tbl_distributor_types';
    protected $guarded = ['id'];
    protected $casts = ['status' => 'boolean'];
}
