<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $table = 'tbl_departments';

    protected $guarded = ['id'];

    protected $casts = ['status' => 'boolean'];

    public function designations()
    {
        return $this->hasMany(Designation::class, 'department_id');
    }
}
