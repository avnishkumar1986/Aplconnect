<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistributorProfile extends Model
{
    protected $table = 'tbl_distributor_profiles';
    protected $guarded = ['id'];
    protected $casts = [
        'applicable_plants' => 'array',
        'material_groups' => 'array',
        'credit_limit' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }

    public function contactPerson()
    {
        return $this->belongsTo(UserProfile::class, 'contact_person_user_id');
    }
}
