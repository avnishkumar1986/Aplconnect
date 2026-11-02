<?php

namespace Modules\SalesDistribution\Models;

use App\Models\User;

class Customer extends User
{
    protected static function booted(): void
    {
        static::addGlobalScope('customer', fn ($query) => $query->where('user_type', 'USR4'));
    }
}
