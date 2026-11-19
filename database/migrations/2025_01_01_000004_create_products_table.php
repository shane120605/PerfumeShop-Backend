<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            // a category that still has products cannot be deleted
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name', 150)->index();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('stock')->default(0);
            $table->string('size', 50)->default('50 ml');
            // 1-4 = bundled photo in the frontend (public/images/perfume-N.png)
            $table->unsignedTinyInteger('image_number')->nullable();
            // path of an uploaded photo (storage/app/public/products/...)
            $table->string('image_path')->nullable();
            $table->string('notes_top', 100)->nullable();
            $table->string('notes_heart', 100)->nullable();
            $table->string('notes_base', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
