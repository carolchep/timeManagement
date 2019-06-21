<?php

namespace App\Http\Controllers\API;

use App\ParcelCategory;
use App\Transformers\ParcelCategoryTransformer;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ParcelCategoryController extends Controller
{
    public function index()
    {
        $categories =  ParcelCategory::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'parcelCategories' => fractal($categories, new ParcelCategoryTransformer())
        ]);
    }
}
