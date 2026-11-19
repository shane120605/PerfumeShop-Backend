<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    private const SORTABLE = ['id', 'name', 'price', 'stock', 'created_at'];

    /**
     * GET /api/products
     * Query params: search, category (name or slug, "All" = no filter), category_id, min_price, max_price,
     *               in_stock=1, low_stock=1, sort (id|name|price|stock|created_at), direction (asc|desc),
     *               per_page (1-100, default 12), page
     */
    public function index(Request $request)
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', 'in:'.implode(',', self::SORTABLE)],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $query = Product::query()->with('category');

        if (($search = trim((string) $request->query('search', ''))) !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('description', 'like', $like));
        }

        $category = trim((string) $request->query('category', ''));
        if ($category !== '' && strtolower($category) !== 'all') {
            $query->whereHas('category', fn ($q) => $q->where('name', $category)->orWhere('slug', $category));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->query('category_id'));
        }
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->query('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->query('max_price'));
        }
        if ($request->boolean('in_stock')) {
            $query->where('stock', '>', 0);
        }
        if ($request->boolean('low_stock')) {
            $query->where('stock', '<=', (int) config('shop.low_stock_threshold'));
        }

        $sort = $request->query('sort', 'id');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $direction);
        if ($sort !== 'id') {
            $query->orderBy('id');
        }

        $perPage = (int) $request->query('per_page', 12);

        return ProductResource::collection($query->paginate($perPage)->withQueryString());
    }

    /** GET /api/products/{product} */
    public function show(Product $product)
    {
        return new ProductResource($product->load('category'));
    }
}
