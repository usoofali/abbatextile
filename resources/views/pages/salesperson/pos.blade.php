<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Point of Sale'])] class extends Component {
    public $shop;
    public $products = [];
    public $selectedProducts = [];
    public $search = '';
    public $cart = [];
    public $totalAmount = 0;
    public $totalProfit = 0;

    public function mount(): void
    {
        $user = Auth::user();
        $this->shop = $user->shop;

        if (!$this->shop) {
            session()->flash('error', 'No shop assigned to you.');
            return;
        }

        $this->loadProducts();
    }

    public function loadProducts(): void
    {
        if (!$this->shop) return;

        $this->products = $this->shop->products()
            ->where('stock_quantity', '>', 0)
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%');
            })
            ->orderBy('name')
            ->get();
    }

    public function updatedSearch(): void
    {
        $this->loadProducts();
    }

    public function addToCart($productId): void
    {
        $product = $this->products->firstWhere('id', $productId);
        if (!$product) return;

        $existingItem = collect($this->cart)->firstWhere('product_id', $productId);
        
        if ($existingItem) {
            // Update existing item
            $this->cart = collect($this->cart)->map(function ($item) use ($productId) {
                if ($item['product_id'] == $productId) {
                    $item['quantity'] += 1;
                }
                return $item;
            })->toArray();
        } else {
            // Add new item
            $this->cart[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'unit_type' => $product->unit_type,
                'quantity' => 1,
                'unit_price' => $product->unit_type === 'meter' ? $product->sale_price_per_meter : $product->sale_price_per_yard,
                'cost_price' => $product->unit_type === 'meter' ? $product->cost_price_per_meter : $product->cost_price_per_yard,
            ];
        }

        $this->calculateTotals();
    }

    public function removeFromCart($index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
        $this->calculateTotals();
    }

    public function updateQuantity($index, $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeFromCart($index);
            return;
        }

        $this->cart[$index]['quantity'] = $quantity;
        $this->calculateTotals();
    }

    public function updateUnitType($index, $unitType): void
    {
        $product = Product::find($this->cart[$index]['product_id']);
        if (!$product) return;

        $this->cart[$index]['unit_type'] = $unitType;
        $this->cart[$index]['unit_price'] = $unitType === 'meter' ? $product->sale_price_per_meter : $product->sale_price_per_yard;
        $this->cart[$index]['cost_price'] = $unitType === 'meter' ? $product->cost_price_per_meter : $product->cost_price_per_yard;

        $this->calculateTotals();
    }

    public function calculateTotals(): void
    {
        $this->totalAmount = 0;
        $this->totalProfit = 0;

        foreach ($this->cart as $item) {
            $subtotal = $item['quantity'] * $item['unit_price'];
            $costTotal = $item['quantity'] * $item['cost_price'];
            
            $this->totalAmount += $subtotal;
            $this->totalProfit += ($subtotal - $costTotal);
        }
    }

    public function processSale(): void
    {
        if (empty($this->cart)) {
            session()->flash('error', 'Cart is empty.');
            return;
        }

        DB::transaction(function () {
            $user = Auth::user();
            
            foreach ($this->cart as $item) {
                $product = Product::find($item['product_id']);
                
                // Check stock availability
                if ($product->stock_quantity < $item['quantity']) {
                    session()->flash('error', "Insufficient stock for {$product->name}. Available: {$product->stock_quantity} {$product->unit_type}");
                    return;
                }

                // Create sale record
                Sale::create([
                    'shop_id' => $this->shop->id,
                    'salesperson_id' => $user->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_type' => $item['unit_type'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['quantity'] * $item['unit_price'],
                    'cost_price' => $item['cost_price'],
                    'profit' => $item['quantity'] * ($item['unit_price'] - $item['cost_price']),
                ]);

                // Update stock
                $product->decrement('stock_quantity', $item['quantity']);
            }

            // Clear cart
            $this->cart = [];
            $this->totalAmount = 0;
            $this->totalProfit = 0;

            session()->flash('success', 'Sale processed successfully!');
        });
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->totalAmount = 0;
        $this->totalProfit = 0;
    }
}; ?>

    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">Point of Sale</flux:heading>
                <flux:subheading size="lg">{{ $shop?->name ?? 'No shop assigned' }}</flux:subheading>
            </div>
            <flux:button variant="outline" :href="route('salesperson.dashboard')" wire:navigate>
                <flux:icon name="arrow-left" />
                Back to Dashboard
            </flux:button>
        </div>

        @if($shop)
            <div class="grid gap-6 grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
                <!-- Products Selection -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Search -->
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search products..."
                        icon="magnifying-glass"
                    />

                    <!-- Products Grid -->
                    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                        <div class="mb-4">
                            <flux:heading size="lg">Available Products</flux:heading>
                        </div>
                        
                        @if($products->count() > 0)
                            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                                @foreach($products as $product)
                                    <div class="rounded-lg border border-neutral-200 p-4 hover:border-blue-300 dark:border-neutral-700 dark:hover:border-blue-600">
                                        <div class="mb-2">
                                            <flux:text class="font-medium">{{ $product->name }}</flux:text>
                                            @if($product->description)
                                                <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ Str::limit($product->description, 50) }}</flux:text>
                                            @endif
                                            @if($product->brand)
                                                <flux:badge variant="outline" size="xs" class="mt-1">{{ $product->brand->name }}</flux:badge>
                                            @endif
                                            @if($product->category)
                                                <flux:badge variant="secondary" size="xs" class="mt-1">{{ $product->category->name }}</flux:badge>
                                            @endif
                                        </div>
                                        
                                        <div class="mb-3 space-y-1">
                                            <div class="flex justify-between text-sm">
                                                <flux:text class="text-neutral-600 dark:text-neutral-400">Yard:</flux:text>
                                                <flux:text class="font-medium">${{ number_format($product->sale_price_per_yard, 2) }}</flux:text>
                                            </div>
                                            <div class="flex justify-between text-sm">
                                                <flux:text class="text-neutral-600 dark:text-neutral-400">Meter:</flux:text>
                                                <flux:text class="font-medium">${{ number_format($product->sale_price_per_meter, 2) }}</flux:text>
                                            </div>
                                            <div class="flex justify-between text-sm">
                                                <flux:text class="text-neutral-600 dark:text-neutral-400">Stock:</flux:text>
                                                <flux:text class="font-medium">{{ number_format($product->stock_quantity, 2) }} {{ $product->unit_type }}</flux:text>
                                            </div>
                                        </div>

                                        <flux:button 
                                            variant="primary" 
                                            size="sm" 
                                            wire:click="addToCart({{ $product->id }})"
                                            class="w-full"
                                            @if($product->stock_quantity <= 0) disabled @endif
                                        >
                                            <flux:icon name="plus" />
                                            Add to Cart
                                        </flux:button>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="py-12 text-center">
                                <flux:icon name="cube" class="mx-auto size-12 text-neutral-400" />
                                <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">
                                    @if($search)
                                        No products match your search.
                                    @else
                                        No products available in stock.
                                    @endif
                                </flux:text>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Cart -->
                <div class="space-y-6">
                    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                        <div class="mb-4 flex items-center justify-between">
                            <flux:heading size="lg">Shopping Cart</flux:heading>
                            @if(count($cart) > 0)
                                <flux:button variant="ghost" size="sm" wire:click="clearCart" class="text-red-600">
                                    <flux:icon name="trash" />
                                    Clear
                                </flux:button>
                            @endif
                        </div>

                        @if(count($cart) > 0)
                            <div class="space-y-3 mb-4 max-h-96 overflow-y-auto">
                                @foreach($cart as $index => $item)
                                    <div class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-700">
                                        <div class="mb-2">
                                            <flux:text class="font-medium">{{ $item['name'] }}</flux:text>
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <!-- Unit Type -->
                                            <flux:select wire:model.live="cart.{{ $index }}.unit_type" size="sm">
                                                <option value="yard">Yard</option>
                                                <option value="meter">Meter</option>
                                            </flux:select>

                                            <!-- Quantity -->
                                            <div class="flex items-center gap-2">
                                                <flux:button 
                                                    variant="ghost" 
                                                    size="sm" 
                                                    wire:click="updateQuantity({{ $index }}, {{ $item['quantity'] - 1 }})"
                                                    class="h-8 w-8 p-0"
                                                >
                                                    <flux:icon name="minus" class="size-4" />
                                                </flux:button>
                                                <flux:input 
                                                    wire:model.live.debounce.300ms="cart.{{ $index }}.quantity"
                                                    type="number"
                                                    min="0.01"
                                                    step="0.01"
                                                    class="text-center w-16"
                                                />
                                                <flux:button 
                                                    variant="ghost" 
                                                    size="sm" 
                                                    wire:click="updateQuantity({{ $index }}, {{ $item['quantity'] + 1 }})"
                                                    class="h-8 w-8 p-0"
                                                >
                                                    <flux:icon name="plus" class="size-4" />
                                                </flux:button>
                                                <flux:button 
                                                    variant="ghost" 
                                                    size="sm" 
                                                    wire:click="removeFromCart({{ $index }})"
                                                    class="ml-auto text-red-600"
                                                >
                                                    <flux:icon name="trash" class="size-4" />
                                                </flux:button>
                                            </div>

                                            <div class="text-right">
                                                <flux:text class="text-sm font-medium">
                                                    ${{ number_format($item['quantity'] * $item['unit_price'], 2) }}
                                                </flux:text>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Totals -->
                            <div class="border-t border-neutral-200 pt-4 dark:border-neutral-700">
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <flux:text class="font-medium">Total Amount:</flux:text>
                                        <flux:text class="font-bold text-lg">${{ number_format($totalAmount, 2) }}</flux:text>
                                    </div>
                                    <div class="flex justify-between">
                                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Estimated Profit:</flux:text>
                                        <flux:text class="text-sm font-medium text-green-600 dark:text-green-400">${{ number_format($totalProfit, 2) }}</flux:text>
                                    </div>
                                </div>

                                <div class="mt-4 space-y-2">
                                    <flux:button 
                                        variant="primary" 
                                        wire:click="processSale"
                                        class="w-full"
                                    >
                                        <flux:icon name="credit-card" />
                                        Process Sale
                                    </flux:button>
                                </div>
                            </div>
                        @else
                            <div class="py-12 text-center">
                                <flux:icon name="shopping-cart" class="mx-auto size-12 text-neutral-400" />
                                <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">Cart is empty</flux:text>
                                <flux:text class="text-sm text-neutral-500 dark:text-neutral-500">Add products to get started</flux:text>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div class="rounded-xl border border-red-200 bg-red-50 p-6 dark:border-red-700 dark:bg-red-900/20">
                <div class="flex items-center gap-2">
                    <flux:icon name="exclamation-triangle" class="size-5 text-red-600 dark:text-red-400" />
                    <flux:heading size="lg" class="text-red-800 dark:text-red-200">No Shop Assigned</flux:heading>
                </div>
                <flux:text class="mt-2 text-red-700 dark:text-red-300">
                    You don't have a shop assigned to you. Please contact the administrator to assign you to a shop.
                </flux:text>
            </div>
        @endif
    </div>
