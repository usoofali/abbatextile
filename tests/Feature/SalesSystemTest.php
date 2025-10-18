<?php

use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can create a complete sale with items and payment', function () {
    // Create test data
    $shop = Shop::factory()->create();
    $category = Category::factory()->create();
    $salesperson = User::factory()->create([
        'role' => User::ROLE_SALESPERSON,
        'shop_id' => $shop->id,
    ]);

    $product1 = Product::factory()->create([
        'shop_id' => $shop->id,
        'category_id' => $category->id,
        'stock_quantity' => 100,
        'price_per_unit' => 50.00,
    ]);

    $product2 = Product::factory()->create([
        'shop_id' => $shop->id,
        'category_id' => $category->id,
        'stock_quantity' => 50,
        'price_per_unit' => 75.00,
    ]);

    // Create sale
    $sale = Sale::create([
        'shop_id' => $shop->id,
        'salesperson_id' => $salesperson->id,
        'total_amount' => 0,
        'total_profit' => 0,
        'status' => 'pending',
    ]);

    // Create sale items and update stock
    $item1 = SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $product1->id,
        'quantity' => 2,
        'price' => 50.00,
        'subtotal' => 100.00,
        'profit' => 0.00, // Assuming no profit for simplicity
    ]);

    // Update stock
    $product1->decrement('stock_quantity', 2);

    $item2 = SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $product2->id,
        'quantity' => 1,
        'price' => 75.00,
        'subtotal' => 75.00,
        'profit' => 0.00,
    ]);

    // Update stock
    $product2->decrement('stock_quantity', 1);

    // Update sale totals
    $sale->update([
        'total_amount' => $item1->subtotal + $item2->subtotal,
        'total_profit' => $item1->profit + $item2->profit,
    ]);

    // Create payment
    $payment = Payment::create([
        'sale_id' => $sale->id,
        'amount' => $sale->total_amount,
        'mode' => Payment::MODE_CASH,
        'reference' => 'TEST-001',
        'received_by' => $salesperson->id,
    ]);

    // Update sale status
    $sale->updateStatus();

    // Assertions
    expect($sale->total_amount)->toBe('175.00');
    expect($sale->items)->toHaveCount(2);
    expect($sale->payments)->toHaveCount(1);
    expect($sale->isFullyPaid())->toBeTrue();
    expect($sale->fresh()->status)->toBe('paid');

    // Check stock was updated
    $product1->refresh();
    $product2->refresh();
    expect($product1->stock_quantity)->toBe('98.00');
    expect($product2->stock_quantity)->toBe('49.00');
});

test('can handle partial payments', function () {
    $shop = Shop::factory()->create();
    $salesperson = User::factory()->create([
        'role' => User::ROLE_SALESPERSON,
        'shop_id' => $shop->id,
    ]);

    $sale = Sale::create([
        'shop_id' => $shop->id,
        'salesperson_id' => $salesperson->id,
        'total_amount' => 100.00,
        'total_profit' => 10.00,
        'status' => 'pending',
    ]);

    // First payment
    Payment::create([
        'sale_id' => $sale->id,
        'amount' => 60.00,
        'mode' => Payment::MODE_CASH,
        'received_by' => $salesperson->id,
    ]);

    $sale->refresh();
    expect($sale->total_paid)->toBe(60.0);
    expect($sale->balance)->toBe(40.0);
    expect($sale->isPartiallyPaid())->toBeTrue();
    expect($sale->status)->toBe('pending');

    // Second payment
    Payment::create([
        'sale_id' => $sale->id,
        'amount' => 40.00,
        'mode' => Payment::MODE_TRANSFER,
        'reference' => 'TXN-123',
        'received_by' => $salesperson->id,
    ]);

    $sale->refresh();
    expect($sale->total_paid)->toBe(100.0);
    expect($sale->balance)->toBe(0.0);
    expect($sale->isFullyPaid())->toBeTrue();
    expect($sale->status)->toBe('paid');
});

test('can cancel sale and restore stock', function () {
    $shop = Shop::factory()->create();
    $category = Category::factory()->create();
    $salesperson = User::factory()->create([
        'role' => User::ROLE_SALESPERSON,
        'shop_id' => $shop->id,
    ]);

    $product = Product::factory()->create([
        'shop_id' => $shop->id,
        'category_id' => $category->id,
        'stock_quantity' => 50,
        'price_per_unit' => 25.00,
    ]);

    $sale = Sale::create([
        'shop_id' => $shop->id,
        'salesperson_id' => $salesperson->id,
        'total_amount' => 0,
        'total_profit' => 0,
        'status' => 'pending',
    ]);

    SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'price' => 25.00,
        'subtotal' => 250.00,
        'profit' => 0.00,
    ]);

    // Update stock
    $product->decrement('stock_quantity', 10);

    $sale->update(['total_amount' => 250.00]);

    // Verify stock was reduced
    $product->refresh();
    expect($product->stock_quantity)->toBe('40.00');

    // Cancel sale
    $sale->cancel();

    // Verify stock was restored
    $product->refresh();
    expect($product->stock_quantity)->toBe('50.00');
    expect($sale->fresh()->status)->toBe('cancelled');
});
