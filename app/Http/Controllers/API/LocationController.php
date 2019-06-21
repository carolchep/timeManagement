<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LocationController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();

        $user->lat = $request->lat;
        $user->lng = $request->lng;

        $user->save();

        return response()->json([
            'success' => true
        ]);
    }
}
