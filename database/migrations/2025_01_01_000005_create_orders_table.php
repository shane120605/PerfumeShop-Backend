<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('order_number')->unique(); // public id, e.g. 12345
            // if a customer account is deleted the order stays (sales history)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name', 120);
            $table->string('customer_email');
            $table->string('phone', 30);
            $table->string('shipping_address');
            $table->string('city', 120);
            $table->string('payment_method', 50);
            $table->string('status', 20)->default('pending')->index(); // pending | delivered | cancelled
            $table->decimal('subtotal', 10, 2);
            $table->decimal('shipping_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
