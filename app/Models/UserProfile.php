<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $table = 'tbl_users';
    protected $guarded = ['id'];
    protected $casts = ['date_of_birth' => 'date', 'anniversary_date' => 'date'];

    public function employmentDepartment() { return $this->hasOne(EmployeeDepartment::class, 'user_id'); }
    public function distributorProfile() { return $this->hasOne(DistributorProfile::class, 'user_id'); }
    public function userType() { return $this->belongsTo(UserType::class, 'type_code', 'type_code'); }
    public function company() { return $this->hasOneThrough(Company::class, EmployeeDepartment::class, 'user_id', 'company_code', 'id', 'company_id'); }
    public function sittingLocation() { return $this->hasOneThrough(Company::class, EmployeeDepartment::class, 'user_id', 'id', 'id', 'sitting_location_id'); }
    public function designation() { return $this->hasOneThrough(Designation::class, EmployeeDepartment::class, 'user_id', 'id', 'id', 'designation_id'); }
    public function department() { return $this->hasOneThrough(Department::class, EmployeeDepartment::class, 'user_id', 'id', 'id', 'department_id'); }
    public function reportingTo() { return $this->hasOneThrough(self::class, EmployeeDepartment::class, 'user_id', 'id', 'id', 'reporting_to_user_id'); }
    public function contact() { return $this->belongsTo(Contact::class, 'contact_id'); }
    public function contacts() { return $this->hasMany(Contact::class, 'employee_id', 'id'); }
    public function emailContacts() { return $this->contacts()->where('contact_type', '3'); }
    public function address() { return $this->belongsTo(Address::class, 'address_id'); }
    public function addresses() { return $this->hasMany(Address::class, 'employee_id', 'id'); }
    public function primaryEducation() { return $this->belongsTo(Education::class, 'education_id'); }
    public function education() { return $this->hasMany(Education::class, 'user_id'); }
    public function login() { return $this->belongsTo(Login::class, 'login_id'); }
    public function createdBy() { return $this->belongsTo(Login::class, 'created_by'); }
    public function updatedBy() { return $this->belongsTo(Login::class, 'updated_by'); }
    public function images() { return $this->hasMany(UserImage::class, 'user_id'); }
    public function documents() { return $this->hasMany(UserDocument::class, 'user_id'); }
    public function profileImage() { return $this->hasOne(UserImage::class, 'user_id')->where('image_type', 'profileimg'); }
    public function coverImage() { return $this->hasOne(UserImage::class, 'user_id')->where('image_type', 'wallimage'); }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getCompanyIdAttribute() { return $this->employmentDepartment?->company_id; }
    public function getSittingLocationIdAttribute() { return $this->employmentDepartment?->sitting_location_id; }
    public function getDepartmentIdAttribute() { return $this->employmentDepartment?->department_id; }
    public function getDesignationIdAttribute() { return $this->employmentDepartment?->designation_id; }
    public function getRoleIdAttribute() { return $this->employmentDepartment?->role_id; }
    public function getReportingToUserIdAttribute() { return $this->employmentDepartment?->reporting_to_user_id; }

    public function syncReportingManager(): void
    {
        $parentDesignationId = $this->designation?->reports_to_designation_id;
        if (! $parentDesignationId) {
            $this->employmentDepartment?->update(['reporting_to_user_id' => null]);
            return;
        }
        $managerId = EmployeeDepartment::query()
            ->where('user_id', '<>', $this->id)
            ->where('status', '1')
            ->where('company_id', $this->company_id)
            ->where('designation_id', $parentDesignationId)
            ->when($this->department_id, fn ($query) => $query->orderByRaw('department_id = ? desc', [$this->department_id]))
            ->when($this->sitting_location_id, fn ($query) => $query->orderByRaw('sitting_location_id = ? desc', [$this->sitting_location_id]))
            ->orderBy('user_id')->value('user_id');
        $this->employmentDepartment?->update(['reporting_to_user_id' => $managerId]);
    }
}
