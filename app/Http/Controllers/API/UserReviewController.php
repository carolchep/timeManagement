<?php

namespace App\Http\Controllers\API;

use App\Rules\CheckRatingPoints;
use App\Rules\CheckStarRating;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\User;

class UserReviewController extends Controller
{
    public function rateUser(Request $request, User $user)
    {
        $this->validate($request, [
            'stars' => [
                'required',
                'integer',
                new CheckStarRating()
            ]
        ]);

        $data = [
            'reviewer_id' => $request->user()->id,
            'stars' => $request->stars,
            'description' => $request->description
        ];

        $user->reviews()->create($data);

        return response()->json([
            'success' => true,
            'message' => 'user review submitted'
        ]);
    }
}
