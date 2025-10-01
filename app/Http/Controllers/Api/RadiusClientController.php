<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mikrotik;

class RadiusClientController extends Controller
{
    public function index()
    {
        return Mikrotik::query()
            ->select('name','host','radius_secret')
            ->whereNotNull('host')
            ->whereNotNull('radius_secret')
            ->get()
            ->values();
    }
}
