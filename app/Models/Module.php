<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Module extends Model
{
    protected $table = 'tbl_modules';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['file_size' => 'integer'];
    }
}
