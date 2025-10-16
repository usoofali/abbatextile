<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'price_per_unit')) {
                $table->decimal('price_per_unit', 10, 2)->nullable()->after('barcode');
            }
        });

        // Migrate existing sale prices to price_per_unit using product.unit_type
        if (Schema::hasColumn('products', 'sale_price_per_yard') && Schema::hasColumn('products', 'sale_price_per_meter') && Schema::hasColumn('products', 'unit_type')) {
            DB::table('products')->orderBy('id')->lazyById()->each(function ($product) {
            $price = $product->unit_type === 'meter' ? $product->sale_price_per_meter : $product->sale_price_per_yard;
            DB::table('products')->where('id', $product->id)->update(['price_per_unit' => $price]);
            });
        }

        Schema::table('products', function (Blueprint $table) {
            // Make price non-null
            if (Schema::hasColumn('products', 'price_per_unit')) {
                $table->decimal('price_per_unit', 10, 2)->nullable(false)->change();
            }

            // Drop cost and per-unit sale columns and unit_type if they exist
            foreach (['cost_price_per_yard', 'cost_price_per_meter', 'sale_price_per_yard', 'sale_price_per_meter', 'unit_type'] as $col) {
                if (Schema::hasColumn('products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost_price_per_yard', 10, 2)->default(0)->after('description');
            $table->decimal('cost_price_per_meter', 10, 2)->default(0)->after('cost_price_per_yard');
            $table->decimal('sale_price_per_yard', 10, 2)->default(0)->after('cost_price_per_meter');
            $table->decimal('sale_price_per_meter', 10, 2)->default(0)->after('sale_price_per_yard');
            $table->string('unit_type')->default('yard')->after('stock_quantity');
        });

        // Attempt to reverse migrate by setting yard prices from price_per_unit
        DB::table('products')->update([
            'sale_price_per_yard' => DB::raw('price_per_unit'),
        ]);

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['price_per_unit']);
        });
    }
};


