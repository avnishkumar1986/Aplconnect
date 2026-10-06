<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDocument extends Model
{
    protected $table = 'tbl_user_documents';
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }
}
