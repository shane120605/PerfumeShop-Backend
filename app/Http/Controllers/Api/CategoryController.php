<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;

class CategoryController extends Controller
{
    /** GET /api/categories */
    public function index()
    {
        return CategoryResource::collection(Category::withCount('products')->orderBy('name')->get());
    }

    /** GET /api/categories/{category} */
    public function show(Category $category)
    {
        return new CategoryResource($category->loadCount('products'));
    }
}
