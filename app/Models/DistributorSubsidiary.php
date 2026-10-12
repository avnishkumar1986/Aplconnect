<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistributorSubsidiary extends Model
{
    protected $table = 'tbl_distributor_subsidiaries';
    protected $guarded = ['id'];
    protected $casts = ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'status' => 'boolean'];

    public function distributor()
    {
        return $this->belongsTo(UserProfile::class, 'distributor_user_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class, 'address_id');
    }

    public function phoneContact()
    {
        return $this->belongsTo(Contact::class, 'phone_contact_id');
    }

    public function emailContact()
    {
        return $this->belongsTo(Contact::class, 'email_contact_id');
    }
}
