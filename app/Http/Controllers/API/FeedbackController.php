<?php

namespace App\Http\Controllers\API;

use App\Feedback;
use App\Transformers\FeedbackTransformer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class FeedbackController extends Controller
{
    public function store(Request $request)
    {
        $this->validate($request, [
            'body' => 'required',
            'subject' => 'required'
        ]);

        Feedback::create([
            'user_id' => $request->user()->id,
            'body' => $request->body,
            'subject' => $request->subject
        ]);

        $feedback = Feedback::orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
'message' => 'your feedback has been received.'
//            'feedback' => fractal($feedback, new FeedbackTransformer())
        ]);
    }

    public function index(Request $request)
    {
        $feedback = $request->user()->feedback()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'feedback' => fractal($feedback, new FeedbackTransformer())
        ]);
    }
}
