<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $table = 'tbl_company';
    protected $guarded = ['id'];
    const CREATED_AT = 'Created_at';
    const UPDATED_AT = 'Updated_at';

    public function parent() { return $this->belongsTo(self::class, 'parent_company_id', 'company_code'); }
    public function children() { return $this->hasMany(self::class, 'parent_company_id', 'company_code'); }
    public function address() { return $this->belongsTo(Address::class, 'address_id'); }
    public function contact() { return $this->belongsTo(Contact::class, 'contact_id'); }
    public function contacts() { return $this->hasMany(Contact::class, 'company_code'); }
    public function addresses() { return $this->hasMany(Address::class, 'company_code'); }
    public function employees() { return $this->belongsToMany(UserProfile::class, 'tbl_user_plants', 'plant_id', 'user_id'); }

    public function getEmailAttribute(): string
    {
        $email = $this->contacts->first(fn ($contact) => (string) $contact->contact_type === '3');
        if (! $email && (string) $this->contact?->contact_type === '3') $email = $this->contact;

        return $email?->contact_value ?? '—';
    }
}
