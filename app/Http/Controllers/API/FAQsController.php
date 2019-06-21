<?php

namespace App\Http\Controllers\API;

use App\FrequentlyAskedQuestion;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class FAQsController extends Controller
{
    public function index(Request $request)
    {
        $model = new FrequentlyAskedQuestion();
        $query = $model->newQuery();


        return response()->json([
            'success' => true,
            'faqs' => $query->get()
        ]);
    }
}
