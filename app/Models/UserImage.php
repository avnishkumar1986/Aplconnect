<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserImage extends Model
{
    protected $table = 'tbl_user_images';
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }
}
