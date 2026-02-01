<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add purchase_price to products table
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('purchase_price', 10, 2)
                ->nullable()
                ->default(0)
                ->after('price_per_unit')
                ->comment('Cost/purchase price per unit for profit calculation');
        });

        // Add purchase_price to sale_items table
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('purchase_price', 10, 2)
                ->nullable()
                ->default(0)
                ->after('price')
                ->comment('Snapshot of product purchase price at time of sale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('purchase_price');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('purchase_price');
        });
    }
};
