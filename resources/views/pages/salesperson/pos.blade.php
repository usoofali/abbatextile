<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\Shop;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Point of Sale'])] class extends Component {
    public $shop;
    public $products = [];
    public $search = '';
    public $cart = [];
    public $totalAmount = 0;
    public $showPaymentModal = false;
    public $paymentAmount = 0;
    public $paymentMode = 'cash';
    public $paymentReference = '';

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
            ->with('category')
            ->where('stock_quantity', '>', 0)
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%')
                      ->orWhere('barcode', 'like', '%' . $this->search . '%');
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
                'price' => $product->price_per_unit,
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

    public function calculateTotals(): void
    {
        $this->totalAmount = 0;

        foreach ($this->cart as $item) {
            $subtotal = $item['quantity'] * $item['price'];
            $this->totalAmount += $subtotal;
        }
    }

    public function processSale(): void
    {
        if (empty($this->cart)) {
            session()->flash('error', 'Cart is empty.');
            return;
        }

        $this->showPaymentModal = true;
        $this->paymentAmount = $this->totalAmount;
    }

    public function completeSale(): void
    {
        if (empty($this->cart)) {
            session()->flash('error', 'Cart is empty.');
            return;
        }

        if ($this->paymentAmount <= 0) {
            session()->flash('error', 'Payment amount must be greater than 0.');
            return;
        }

        DB::transaction(function () {
            $user = Auth::user();
            
            // Create sale record
            $sale = Sale::create([
                'shop_id' => $this->shop->id,
                'salesperson_id' => $user->id,
                'total_amount' => $this->totalAmount,
                'status' => 'pending',
            ]);

            // Create sale items and update stock
            foreach ($this->cart as $item) {
                $product = Product::find($item['product_id']);
                
                // Check stock availability
                if ($product->stock_quantity < $item['quantity']) {
                    throw new \Exception("Insufficient stock for {$product->name}. Available: {$product->stock_quantity} {$product->unit_type}");
                }

                // Create sale item
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'subtotal' => $item['quantity'] * $item['price'],
                ]);

                // Update stock
                $product->decrement('stock_quantity', $item['quantity']);
            }

            // Create payment record
            Payment::create([
                'sale_id' => $sale->id,
                'amount' => $this->paymentAmount,
                'mode' => $this->paymentMode,
                'reference' => $this->paymentReference,
                'received_by' => $user->id,
            ]);

            // Clear cart and reset
            $this->cart = [];
            $this->totalAmount = 0;
            $this->showPaymentModal = false;
            $this->paymentAmount = 0;
            $this->paymentMode = 'cash';
            $this->paymentReference = '';

            session()->flash('success', 'Sale processed successfully!');
        });
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->totalAmount = 0;
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
        $this->paymentAmount = 0;
        $this->paymentMode = 'cash';
        $this->paymentReference = '';
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">Point of Sale</flux:heading>
            <flux:text size="lg" class="text-neutral-600 dark:text-neutral-400">{{ $shop?->name ?? 'No shop assigned' }}</flux:text>
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
                <!-- Search and Barcode -->
                <div class="space-y-4">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search products by name, description, or barcode..."
                        icon="magnifying-glass"
                    />
                </div>

                <!-- Products Grid -->
                <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                    <div class="mb-4">
                        <flux:heading size="xl">Available Products</flux:heading>
                    </div>
                    
                    @if($products->count() > 0)
                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-2">
                            @foreach($products as $product)
                            <div class="rounded-lg border border-neutral-200 p-4 hover:border-blue-300 dark:border-neutral-700 dark:hover:border-blue-600">
                                <!-- Product Image and Details -->
                                <div class="flex gap-3 mb-3">
                                    <!-- Product Image -->
                                    <div class="flex-shrink-0">
                                        @if($product->photo)
                                            <img 
                                                src="{{ Storage::url($product->photo) }}" 
                                                alt="{{ $product->name }}"
                                                class="w-16 h-16 rounded-lg object-cover border border-neutral-200 dark:border-neutral-600"
                                                onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjQiIGhlaWdodD0iNjQiIHZpZXdCb3g9IjAgMCA2NCA2NCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjY0IiBoZWlnaHQ9IjY0IiByeD0iOCIgZmlsbD0iI0YzRjNGMyIvPgo8cGF0aCBkPSJNMzIgMzZDMzUuMzEzNyAzNiAzOCAzMy4zMTM3IDM4IDMwQzM4IDI2LjY4NjMgMzUuMzEzNyAyNCAzMiAyNEMyOC42ODYzIDI0IDI2IDI2LjY4NjMgMjYgMzBDMjYgMzMuMzEzNyAyOC42ODYzIDM2IDMyIDM2WiIgZmlsbD0iIzlDQTBBQyIvPgo8cGF0aCBkPSJNNDQgNDRWMjBDNDQgMTguODk1NCA0My4xMDQ2IDE4IDQyIDE4SDIyQzIwLjg5NTQgMTggMjAgMTguODk1NCAyMCAyMFY0NEMyMCA0NS4xMDQ2IDIwLjg5NTQgNDYgMjIgNDZINDRDNDUuMTA0NiA0NiA0NiA0NS4xMDQ2IDQ2IDQ0VjQ0WiIgZmlsbD0iIzlDQTBBQyIvPgo8L3N2Zz4K'"
                                            >
                                        @else
                                            <div class="w-16 h-16 rounded-lg bg-neutral-200 dark:bg-neutral-700 flex items-center justify-center">
                                                <flux:icon name="photo" class="size-6 text-neutral-400" />
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <!-- Product Info -->
                                    <div class="min-w-0 flex-1">
                                        <flux:text class="font-medium truncate">{{ $product->name }}</flux:text>
                                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400 truncate">
                                            {{ $product->category?->name ?? 'Uncategorized' }}
                                        </flux:text>
                                        
                                        <!-- Stock Status -->
                                        @if($product->stock_quantity <= 0)
                                            <flux:badge variant="danger" size="sm" class="mt-1">
                                                Out of Stock
                                            </flux:badge>
                                        @elseif($product->stock_quantity <= 10)
                                            <flux:badge variant="warning" size="sm" class="mt-1">
                                                Low Stock
                                            </flux:badge>
                                        @else
                                            <flux:badge variant="success" size="sm" class="mt-1">
                                                In Stock
                                            </flux:badge>
                                        @endif
                                    </div>
                                </div>
                                
                                <!-- Product Details -->
                                <div class="mb-3 space-y-2">
                                    <div class="flex justify-between items-center">
                                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Price:</flux:text>
                                        <flux:text class="font-semibold text-green-600 dark:text-green-400">
                                            ₦{{ number_format($product->price_per_unit, 2) }}/{{ $product->unit_type }}
                                        </flux:text>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Stock:</flux:text>
                                        <flux:text class="font-medium @if($product->stock_quantity <= 10) text-amber-600 dark:text-amber-400 @else text-neutral-700 dark:text-neutral-300 @endif">
                                            {{ number_format($product->stock_quantity, 2) }} {{ $product->unit_type }}
                                        </flux:text>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Barcode:</flux:text>
                                        <flux:text class="font-mono text-xs text-neutral-500 dark:text-neutral-400">
                                            {{ $product->barcode }}
                                        </flux:text>
                                    </div>
                                </div>

                                <!-- Add to Cart Button -->
                                <flux:button 
                                    variant="primary"
                                    icon="plus" 
                                    size="sm" 
                                    wire:click="addToCart('{{ $product->id }}')"
                                    class="w-full"
                                    :disabled="$product->stock_quantity <= 0"
                                >
                                    @if($product->stock_quantity <= 0)
                                        Out of Stock
                                    @else
                                        Add to Cart
                                    @endif
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
                        <flux:heading size="xl">Shopping Cart</flux:heading>
                        @if(count($cart) > 0)
                            <flux:button icon="trash" variant="ghost" size="sm" wire:click="clearCart" class="text-red-600">
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
                                                icon="trash"
                                                size="sm" 
                                                wire:click="removeFromCart('{{ $index }}')"
                                                class="ml-auto text-red-600"
                                            >
                                            </flux:button>
                                        </div>

                                        <div class="text-right">
                                            <flux:text class="text-sm font-medium">
                                                ₦{{ number_format($item['quantity'] * $item['price'], 2) }}
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
                                    <flux:text class="font-bold text-lg">₦{{ number_format($totalAmount, 2) }}</flux:text>
                                </div>
                            </div>

                            <div class="mt-4 space-y-2">
                                <flux:button 
                                    icon="credit-card"
                                    variant="primary" 
                                    wire:click="processSale"
                                    class="w-full"
                                >
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

        <!-- Payment Modal -->
        @if($showPaymentModal)
            <flux:modal wire:model="showPaymentModal" class="md:w-96">
                <flux:heading size="xl">Payment Details</flux:heading>
                
                <div class="space-y-4">
                    <div class="bg-neutral-50 dark:bg-neutral-800 p-4 rounded-lg">
                        <div class="flex justify-between">
                            <flux:text class="font-medium">Total Amount:</flux:text>
                            <flux:text class="font-bold">₦{{ number_format($totalAmount, 2) }}</flux:text>
                        </div>
                    </div>

                    <flux:field>
                        <flux:label>Payment Amount</flux:label>
                        <flux:input 
                            wire:model="paymentAmount" 
                            type="number" 
                            step="0.01" 
                            min="0"
                            placeholder="Enter payment amount"
                        />
                    </flux:field>

                    <!-- Payment Mode -->
                    <flux:field>
                        <flux:label>Payment Mode</flux:label>
                        <flux:select wire:model="paymentMode" class="w-full">
                            <option value="cash">💵 Cash</option>
                            <option value="transfer">🏦 Bank Transfer</option>
                            <option value="pos">💳 POS</option>
                            <option value="credit">📝 Credit</option>
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label>Reference (Optional)</flux:label>
                        <flux:input 
                            wire:model="paymentReference" 
                            placeholder="Transaction reference"
                        />
                    </flux:field>
                </div>

                <div class="flex gap-3 mt-6">
                    <flux:button 
                        variant="primary" 
                        wire:click="completeSale"
                        class="flex-1"
                    >
                        Complete Sale
                    </flux:button>
                    <flux:button 
                        variant="ghost" 
                        wire:click="closePaymentModal"
                    >
                        Cancel
                    </flux:button>
                </div>
            </flux:modal>
        @endif
    @else
        <div class="rounded-xl border border-red-200 bg-red-50 p-6 dark:border-red-700 dark:bg-red-900/20">
            <div class="flex items-center gap-2">
                <flux:icon name="exclamation-triangle" class="size-5 text-red-600 dark:text-red-400" />
                <flux:heading size="xl" class="text-red-800 dark:text-red-200">No Shop Assigned</flux:heading>
            </div>
            <flux:text class="mt-2 text-red-700 dark:text-red-300">
                You don't have a shop assigned to you. Please contact the administrator to assign you to a shop.
            </flux:text>
        </div>
    @endif
</div>