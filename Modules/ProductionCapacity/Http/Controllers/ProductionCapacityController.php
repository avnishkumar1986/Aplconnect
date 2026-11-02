<?php

namespace Modules\ProductionCapacity\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ProductionCapacityController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('modules/productioncapacity/dashboard');
    }
}
