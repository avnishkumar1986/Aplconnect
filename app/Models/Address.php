<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $table = 'tbl_addresses';
    protected $guarded = ['id'];
    protected $casts = [
        'is_current_permanent_same' => 'boolean',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_code');
    }

    public function users()
    {
        return $this->hasMany(UserProfile::class, 'address_id');
    }

    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->address_line_1,
            $this->address_line_2,
            $this->city,
            $this->district,
            $this->state,
            $this->postal_code,
            $this->country,
        ])->filter(fn ($part) => filled($part))->unique()->implode(', ');
    }
}
