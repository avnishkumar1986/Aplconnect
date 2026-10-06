<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserType extends Model
{
    protected $table = 'tbl_usertypes';
    protected $guarded = ['id'];
    public function users()
    {
        return $this->hasMany(UserProfile::class, 'user_type_code');
    }
}
