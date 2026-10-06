<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Education extends Model
{
    protected $table = 'tbl_education';
    protected $guarded = ['id'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'uploaded_at' => 'datetime', 'is_current' => 'boolean'];
    public function user()
    {
        return $this->belongsTo(UserProfile::class, 'user_id');
    }
}
