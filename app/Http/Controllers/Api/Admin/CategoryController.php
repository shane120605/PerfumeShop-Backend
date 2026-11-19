<?php



namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /** GET /api/admin/categories */
    public function index()
    {
        return CategoryResource::collection(Category::withCount('products')->orderBy('name')->get());
    }

    /** GET /api/admin/categories/{category} */
    public function show(Category $category)
    {
        return new CategoryResource($category->loadCount('products'));
    }

    /** POST /api/admin/categories */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create([
            'name' => $request->validated('name'),
            'slug' => $this->uniqueSlug($request->validated('name')),
            'description' => $request->validated('description'),
        ]);

        return response()->json([
            'message' => 'Category created.',
            'data' => (new CategoryResource($category->loadCount('products')))->resolve(),
        ], 201);
    }

    /** PUT/PATCH /api/admin/categories/{category} */
    public function update(StoreCategoryRequest $request, Category $category): JsonResponse
    {
        $data = ['description' => $request->validated('description')];

        if ($request->validated('name') !== $category->name) {
            $data['name'] = $request->validated('name');
            $data['slug'] = $this->uniqueSlug($request->validated('name'), $category->id);
        }

        $category->update($data);

        return response()->json([
            'message' => 'Category updated.',
            'data' => (new CategoryResource($category->loadCount('products')))->resolve(),
        ]);
    }

    /** DELETE /api/admin/categories/{category}  -> refused while products still use it */
    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->exists()) {
            return response()->json([
                'message' => 'This category still has products. Move or delete those products first.',
            ], 409);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $i = 2;

        while (Category::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
