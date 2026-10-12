<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistributorProfile extends Model
{
    protected $table = 'tbl_distributor_profiles';
    protected $guarded = ['id'];
    protected $casts = [
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

    public function tier()
    {
        return $this->belongsTo(DistributorTier::class, 'distributor_tier_code', 'code');
    }
}
