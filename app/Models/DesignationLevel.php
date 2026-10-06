<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignationLevel extends Model
{
    protected $table = 'tbl_designation_levels';
    protected $guarded = ['id'];

    public function designations() { return $this->hasMany(Designation::class, 'designation_level_id'); }
    public function reportsToLevel() { return $this->belongsTo(self::class, 'reports_to_level_id'); }
}
