<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Contact extends Model
{
    protected $table = 'tbl_contacts';
    protected $guarded = ['id'];
    protected $casts = ['is_primary' => 'boolean'];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_code');
    }

    public function users()
    {
        return $this->hasMany(UserProfile::class, 'contact_id');
    }
}
