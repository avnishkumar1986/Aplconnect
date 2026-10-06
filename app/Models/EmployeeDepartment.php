<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class EmployeeDepartment extends Model
{
    protected $table = 'tbl_empdep';
    protected $guarded = ['id'];

    public function user() { return $this->belongsTo(UserProfile::class, 'user_id'); }
    public function company() { return $this->belongsTo(Company::class, 'company_id', 'company_code'); }
    public function sittingLocation() { return $this->belongsTo(Company::class, 'sitting_location_id'); }
    public function department() { return $this->belongsTo(Department::class, 'department_id'); }
    public function designation() { return $this->belongsTo(Designation::class, 'designation_id'); }
    public function role() { return $this->belongsTo(Role::class, 'role_id'); }
    public function reportingTo() { return $this->belongsTo(UserProfile::class, 'reporting_to_user_id'); }
}
