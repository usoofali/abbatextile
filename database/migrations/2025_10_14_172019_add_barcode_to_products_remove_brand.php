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
            // Add barcode field after name if photo doesn't exist yet
            if (Schema::hasColumn('products', 'photo')) {
                $table->string('barcode')->nullable()->after('photo');
            } else {
                $table->string('barcode')->nullable()->after('name');
            }
        });

        // Remove brand relation safely only if present and FK exists
        if (Schema::hasColumn('products', 'brand_id')) {
            try {
                Schema::table('products', function (Blueprint $table) {
                    $table->dropForeign(['brand_id']);
                });
            } catch (Throwable $e) {
                // ignore if FK doesn't exist
            }
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('brand_id');
            });
        }
        
        // Update existing products with barcodes
        DB::table('products')->get()->each(function ($product) {
            DB::table('products')->where('id', $product->id)->update([
                'barcode' => str_pad($product->id, 6, '0', STR_PAD_LEFT)
            ]);
        });
        
        // Make barcode NOT NULL and unique after populating
        Schema::table('products', function (Blueprint $table) {
            $table->string('barcode')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Remove barcode field
            $table->dropColumn('barcode');
            
            // Restore brand relationship
            $table->unsignedBigInteger('brand_id')->nullable()->after('shop_id');
            $table->foreign('brand_id')->references('id')->on('brands')->onDelete('set null');
        });
    }
};
