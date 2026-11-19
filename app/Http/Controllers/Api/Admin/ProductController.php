<?php


namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /** POST /api/admin/products   (JSON or multipart/form-data with an "image" file) */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $this->payload($request);
        $data['size'] = $data['size'] ?? '50 ml';

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data)->load('category');

        return response()->json([
            'message' => 'Product created.',
            'data' => (new ProductResource($product))->resolve(),
        ], 201);
    }

    /**
     * PUT/PATCH /api/admin/products/{product}
     * POST      /api/admin/products/{product}   (same thing - use this one when uploading a file,
     *                                            because PHP cannot read files sent with PUT)
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $data = $this->payload($request);
        if (array_key_exists('size', $data) && $data['size'] === null) {
            unset($data['size']); // size cannot be empty
        }

        if ($request->boolean('remove_image') && $product->image_path) {
            Storage::disk('public')->delete($product->image_path);
            $data['image_path'] = null;
        }

        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        return response()->json([
            'message' => 'Product updated.',
            'data' => (new ProductResource($product->fresh('category')))->resolve(),
        ]);
    }

    /** DELETE /api/admin/products/{product}  (old orders keep their copied name + price) */
    public function destroy(Product $product): JsonResponse
    {
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted.']);
    }

    /** turns validated input into database columns (only fields that were sent) */
    private function payload(FormRequest $request): array
    {
        $validated = $request->validated();
        $data = Arr::only($validated, ['category_id', 'name', 'price', 'stock', 'size', 'description']);

        if (array_key_exists('notes', $validated) && is_array($validated['notes'])) {
            foreach (['top', 'heart', 'base'] as $part) {
                if (array_key_exists($part, $validated['notes'])) {
                    $data['notes_'.$part] = $validated['notes'][$part];
                }
            }
        }

        return $data;
    }
}
