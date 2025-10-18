<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop dependent tables first (they have foreign keys to sales)
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('payments');
        
        // Now drop the sales table
        Schema::dropIfExists('sales');

        // Create new sales table structure
        Schema::create('sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->onDelete('cascade');
            $table->foreignUuid('salesperson_id')->constrained('users')->onDelete('cascade');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->index(['shop_id', 'created_at']);
            $table->index(['salesperson_id', 'created_at']);
            $table->index('status');
        });

        // Create sale_items table
        Schema::create('sale_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->constrained('sales')->onDelete('cascade');
            $table->foreignUuid('product_id')->constrained('products')->onDelete('cascade');
            $table->decimal('quantity', 8, 2);
            $table->decimal('price', 8, 2);
            $table->decimal('subtotal', 8, 2);
            $table->timestamps();

            $table->index(['sale_id', 'product_id']);
        });

        // Create payments table
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->constrained('sales')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->enum('mode', ['cash', 'transfer', 'pos', 'credit'])->default('cash');
            $table->string('reference')->nullable();
            $table->foreignUuid('received_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['sale_id', 'created_at']);
            $table->index('mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop dependent tables first
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('payments');
        
        // Drop the new sales table
        Schema::dropIfExists('sales');

        // Recreate old sales table structure
        Schema::create('sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_id')->constrained('shops')->onDelete('cascade');
            $table->foreignUuid('salesperson_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('product_id')->constrained('products')->onDelete('cascade');
            $table->decimal('quantity', 8, 2);
            $table->string('unit_type');
            $table->decimal('unit_price', 8, 2);
            $table->decimal('total_price', 8, 2);
            $table->decimal('cost_price', 8, 2);
            $table->decimal('profit', 8, 2);
            $table->timestamps();
        });
    }
};
