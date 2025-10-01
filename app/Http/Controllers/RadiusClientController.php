<?php

namespace App\Http\Controllers;

use App\Models\Mikrotik;
use Illuminate\Http\JsonResponse;

class RadiusClientController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Mikrotik::all(['id', 'host as ip', 'name', 'radius_secret as secret'])
        );
    }
}
