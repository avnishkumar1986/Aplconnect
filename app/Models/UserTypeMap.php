<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTypeMap extends Model
{
    protected $table = 'tbl_user_type_map';
    protected $guarded = ['id'];

    public function user() { return $this->belongsTo(UserProfile::class, 'user_id'); }
    public function type() { return $this->belongsTo(UserType::class, 'type_code', 'type_code'); }
}
