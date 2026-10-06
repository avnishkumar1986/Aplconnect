<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Designation extends Model
{
    protected $table = 'tbl_designations';
    protected $guarded = ['id'];

    public function level() { return $this->belongsTo(DesignationLevel::class, 'designation_level_id'); }
    public function parentDesignation() { return $this->belongsTo(self::class, 'reports_to_designation_id'); }
    public function childDesignations() { return $this->hasMany(self::class, 'reports_to_designation_id'); }
    public function department() { return $this->belongsTo(Department::class, 'department_id'); }
    public function employees() { return $this->hasMany(UserProfile::class, 'designation_id'); }
}
