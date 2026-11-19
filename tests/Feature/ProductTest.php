<?php

// Tests: public catalog (search / filter / sort), admin product CRUD, image upload

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_list_products_with_the_frontend_shape(): void
    {
        $this->product(['name' => 'Rose Eclat']);
        $this->product(['name' => 'Noir Velvet']);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'category', 'category_id', 'price', 'stock', 'size', 'image', 'imageUrl', 'description', 'notes' => ['top', 'heart', 'base']]],
                'links', 'meta',
            ]);
    }

    public function test_products_can_be_searched_filtered_and_sorted(): void
    {
        $floral = $this->category('Floral');
        $woody = $this->category('Woody');
        $this->product(['name' => 'Rose Eclat', 'category_id' => $floral->id, 'price' => 2450]);
        $this->product(['name' => 'Noir Velvet', 'category_id' => $woody->id, 'price' => 3200]);
        $this->product(['name' => 'Jasmine Mist', 'category_id' => $floral->id, 'price' => 1650]);

        $this->getJson('/api/products?search=Eclat')->assertJsonCount(1, 'data');
        $this->getJson('/api/products?category=Floral')->assertJsonCount(2, 'data');
        $this->getJson('/api/products?category=All')->assertJsonCount(3, 'data');
        $this->getJson('/api/products?min_price=2000&max_price=3000')->assertJsonCount(1, 'data');

        $sorted = $this->getJson('/api/products?sort=price&direction=desc')->json('data');
        $this->assertSame([3200.0, 2450.0, 1650.0], array_map(fn ($p) => (float) $p['price'], $sorted));
    }

    public function test_search_treats_percent_signs_literally(): void
    {
        $this->product(['name' => 'Rose Eclat']);

        $this->getJson('/api/products?search=%25')->assertJsonCount(0, 'data');
    }

    public function test_product_details_and_missing_product(): void
    {
        $product = $this->product(['name' => 'Rose Eclat']);

        $this->getJson('/api/products/'.$product->id)->assertOk()->assertJsonPath('data.name', 'Rose Eclat');
        $this->getJson('/api/products/99999')->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_admin_can_create_a_product(): void
    {
        Sanctum::actingAs($this->admin());
        $category = $this->category('Floral');

        $this->postJson('/api/admin/products', [
            'name' => 'Peony Blush',
            'category_id' => $category->id,
            'price' => 2250,
            'stock' => 11,
            'size' => '50 ml',
            'description' => 'Juicy pink peony.',
            'notes' => ['top' => 'Lychee', 'heart' => 'Peony', 'base' => 'Woods'],
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Peony Blush')
            ->assertJsonPath('data.notes.heart', 'Peony');

        $this->assertDatabaseHas('products', ['name' => 'Peony Blush', 'stock' => 11, 'notes_heart' => 'Peony']);
    }

    public function test_admin_can_create_a_product_using_the_category_name(): void
    {
        Sanctum::actingAs($this->admin());
        $category = $this->category('Woody');

        $this->postJson('/api/admin/products', ['name' => 'Oud', 'category' => 'woody', 'price' => 3800, 'stock' => 4])
            ->assertCreated()
            ->assertJsonPath('data.category', 'Woody');

        $this->assertDatabaseHas('products', ['name' => 'Oud', 'category_id' => $category->id]);
    }

    public function test_product_validation_rejects_bad_input(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/admin/products', ['name' => '', 'price' => -5, 'stock' => -1, 'category_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'price', 'stock', 'category_id']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_admin_can_update_a_product(): void
    {
        Sanctum::actingAs($this->admin());
        $product = $this->product(['name' => 'Old Name', 'price' => 100, 'stock' => 5]);

        $this->putJson('/api/admin/products/'.$product->id, ['name' => 'New Name', 'price' => 150.5, 'stock' => 9])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'New Name', 'stock' => 9]);
        $this->assertEquals('150.50', $product->fresh()->price);
    }

    public function test_admin_can_delete_a_product(): void
    {
        Sanctum::actingAs($this->admin());
        $product = $this->product();

        $this->deleteJson('/api/admin/products/'.$product->id)->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_customer_cannot_manage_products(): void
    {
        Sanctum::actingAs($this->customer());
        $product = $this->product();

        $this->putJson('/api/admin/products/'.$product->id, ['price' => 1])->assertForbidden();
        $this->assertEquals('100.00', $product->fresh()->price);
    }

    public function test_admin_can_upload_and_replace_a_product_image(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('The PHP GD extension is required to fake image uploads.');
        }

        Storage::fake('public');
        Sanctum::actingAs($this->admin());
        $category = $this->category();

        $created = $this->post('/api/admin/products', [
            'name' => 'With Photo',
            'category_id' => $category->id,
            'price' => 1000,
            'stock' => 3,
            'image' => UploadedFile::fake()->image('bottle.jpg', 600, 600),
        ], ['Accept' => 'application/json'])->assertCreated();

        $product = Product::findOrFail($created->json('data.id'));
        Storage::disk('public')->assertExists($product->image_path);
        $this->assertNotNull($created->json('data.imageUrl'));
        $oldPath = $product->image_path;

        // POST is accepted as an update so that multipart uploads work
        $this->post('/api/admin/products/'.$product->id, [
            'image' => UploadedFile::fake()->image('new.png', 600, 600),
        ], ['Accept' => 'application/json'])->assertOk();

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($product->fresh()->image_path);
    }

    public function test_image_upload_rejects_non_images(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->admin());
        $category = $this->category();

        $this->post('/api/admin/products', [
            'name' => 'Bad File',
            'category_id' => $category->id,
            'price' => 1000,
            'stock' => 3,
            'image' => UploadedFile::fake()->create('script.php', 10, 'text/plain'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);
    }

    public function test_admin_category_crud_and_delete_protection(): void
    {
        Sanctum::actingAs($this->admin());

        $id = $this->postJson('/api/admin/categories', ['name' => 'Spicy', 'description' => 'Warm spices'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'spicy')
            ->json('data.id');

        $this->postJson('/api/admin/categories', ['name' => 'Spicy'])->assertUnprocessable();

        $this->putJson('/api/admin/categories/'.$id, ['name' => 'Spicy Warm'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'spicy-warm');

        $this->product(['category_id' => $id]);
        $this->deleteJson('/api/admin/categories/'.$id)->assertStatus(409);

        Product::query()->delete();
        $this->deleteJson('/api/admin/categories/'.$id)->assertOk();
        $this->assertDatabaseMissing('categories', ['id' => $id]);
    }

    public function test_public_can_list_categories(): void
    {
        $category = $this->category('Floral');
        $this->product(['category_id' => $category->id]);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Floral')
            ->assertJsonPath('data.0.products_count', 1);
    }
}
