<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
class Login extends Authenticatable
{
    use HasRoles, Notifiable;
    protected $table = 'tbl_login';
    protected $guarded = ['id'];
    protected $hidden = ['password', 'token'];
    protected function casts(): array
    {
        return ['password' => 'hashed', 'status' => 'boolean'];
    }
    public function profile()
    {
        return $this->hasOne(UserProfile::class, 'login_id');
    }
    public function getNameAttribute(): string
    {
        return $this->profile?->full_name ?? $this->username;
    }
    public function getRememberTokenName()
    {
        return 'token';
    }
}
