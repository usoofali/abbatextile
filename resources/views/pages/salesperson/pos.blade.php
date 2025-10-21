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
    public $showScanner = false;

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


    public function addByBarcode(string $barcode): void
    {
        if (!$this->shop) {
            return;
        }

        $trimmed = trim($barcode);
        if ($trimmed === '') {
            return;
        }

        $product = $this->shop->products()
            ->with('category')
            ->where('barcode', $trimmed)
            ->first();

        if (!$product) {
            session()->flash('error', "No product found for barcode: {$trimmed}");
            return;
        }

        if ($product->stock_quantity <= 0) {
            session()->flash('error', "{$product->name} is out of stock.");
            return;
        }

        // FIX: Use the same approach as addToCart with collection
        $existingItem = collect($this->cart)->firstWhere('product_id', $product->id);
        
        if ($existingItem) {
            // Update existing item - use the same collection map approach
            $this->cart = collect($this->cart)->map(function ($item) use ($product) {
                if ($item['product_id'] == $product->id) {
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
        session()->flash('success', "Added {$product->name} to cart");
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
        $quantity = is_numeric($quantity) ? (float) $quantity : 0;
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

        // Check stock availability before processing the transaction
        $stockIssues = [];
        foreach ($this->cart as $index => $item) {
            $product = Product::find($item['product_id']);
            
            if (!$product) {
                $stockIssues[] = "Product '{$item['name']}' no longer exists.";
                continue;
            }

            if ($product->stock_quantity < $item['quantity']) {
                $stockIssues[] = "Insufficient stock for {$product->name}. Available: {$product->stock_quantity} {$product->unit_type}, Requested: {$item['quantity']}";
            }
        }

        // If there are stock issues, show them to the user and stop the process
        if (!empty($stockIssues)) {
            $errorMessage = "Stock issues detected:\n" . implode("\n", $stockIssues);
            session()->flash('error', $errorMessage);
            $this->closePaymentModal();
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

            // Update sale status based on payment
            $sale->updateStatus();
            $this->loadProducts();

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
    public function updatedShowScanner($value)
    {
        if (!$value) {
            // Modal was closed - you could dispatch a browser event here if needed
            $this->dispatch('scanner-closed');
        }
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
                    <div class="flex items-center gap-3">
                        <flux:button variant="ghost" size="sm" icon="qr-code" wire:click="$set('showScanner', true)">
                            Scan Barcode
                        </flux:button>
                        <flux:text class="text-sm text-neutral-500 dark:text-neutral-400">
                            Use your device camera to scan and add items.
                        </flux:text>
                    </div>
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
                                            <flux:badge color="red" size="sm" class="mt-1">
                                                Out of Stock
                                            </flux:badge>
                                        @elseif($product->stock_quantity <= 20)
                                            <flux:badge color="amber" size="sm" class="mt-1">
                                                Low Stock
                                            </flux:badge>
                                        @else
                                            <flux:badge color="green" size="sm" class="mt-1">
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
                                        <flux:text class="font-medium @if($product->stock_quantity <= 20) text-amber-600 dark:text-amber-400 @else text-neutral-700 dark:text-neutral-300 @endif">
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
                                                wire:change="updateQuantity({{ $index }}, $event.target.value)"
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
        
        <!-- Scanner Modal -->
        <!-- Scanner Modal -->
    @if($showScanner)
    <flux:modal wire:model="showScanner" :dismissible="false" :closable="false" class="w-full max-w-xs sm:max-w-sm mx-auto">
        <flux:heading size="xl">Scan Barcode</flux:heading>
        <div
            x-data="barcodeScanner($wire)"
            x-init="
                $nextTick(() => start());
                $wire.on('scanner-closed', () => stop());
                // Watch for modal close via LiveWire
                $watch('$wire.showScanner', (value) => {
                    if (!value) stop();
                });
            "
            x-on:keydown.escape.window="stop(); $wire.set('showScanner', false)"
            x-on:click="activateAudio()"
            class="space-y-3 max-h-[70vh] overflow-hidden"
        >
            <div class="rounded-lg overflow-hidden border border-neutral-200 dark:border-neutral-700 relative">
                <video x-ref="video" playsinline class="w-full h-36 sm:h-48 object-cover bg-black"></video>
                <div class="absolute inset-0 pointer-events-none flex items-center justify-center">
                    <div class="w-2/3 h-16 sm:h-24 border-2 border-green-400/70 rounded"></div>
                </div>
            </div>
            <div class="flex items-center justify-between text-sm">
                <div class="flex items-center gap-2">
                    <span class="inline-block size-2 rounded-full" x-bind:class="running ? 'bg-green-500' : 'bg-neutral-400'"></span>
                    <span x-text="running ? 'Scanning…' : 'Idle'"></span>
                </div>
                <div class="flex items-center gap-2">
                    <flux:button size="xs" variant="ghost" x-on:click="toggleTorch()" x-show="supportsTorch">Toggle Torch</flux:button>
                    <flux:button size="xs" variant="ghost" x-on:click="switchCamera()" x-show="candidates.length > 1">Switch Camera</flux:button>
                    <!-- <flux:button size="xs" variant="ghost" x-on:click="playBeep()">Test Beep</flux:button> -->
                </div>
            </div>
            <template x-if="lastCode">
                <div class="p-2 rounded bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300 text-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-medium">Detected:</span> <span class="font-mono" x-text="lastCode"></span>
                        </div>
                        <div class="text-xs bg-green-200 dark:bg-green-800 px-2 py-1 rounded-full" x-text="'Scan #' + (scanCounts[lastCode] || 0)"></div>
                    </div>
                </div>
            </template>
            <div class="flex gap-2 mt-2">
                <flux:button variant="ghost" x-on:click="stop(); $wire.set('showScanner', false)" class="flex-1">Close Scanner</flux:button>
            </div>
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
    <!-- Flash Message -->
    @if (session()->has('error'))
        <div class="fixed bottom-4 right-4 z-50">
        <x-ui.alert variant="error" :timeout="5000">
            {{ session('error') }}
        </x-ui.alert>
    </div>
    @endif
    @if (session()->has('success'))
        <div class="fixed bottom-4 right-4 z-50">
        <x-ui.alert variant="success" :timeout="5000">
            {{ session('success') }}
        </x-ui.alert>
    </div>
    @endif
</div>

@push('scripts')
<script>
(function registerBarcodeScanner(){
    const define = () => Alpine.data('barcodeScanner', ($wire) => ({
        stream: null,
        track: null,
        running: false,
        detector: null,
        candidates: [],
        currentDeviceIndex: 1,
        supportsTorch: false,
        lastCode: '',
        lastScanAt: 0,
        throttleMs: 1200,
        beepAudio: null,
        zxingReader: null,
        scanCounts: {},
        // Add a flag to track if we should be running
        shouldRun: false,
        
        initBeep() {
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                this.beepAudio = audioContext;
            } catch (e) {
                this.beepAudio = null;
            }
        },
        
        activateAudio() {
            if (this.beepAudio && this.beepAudio.state === 'suspended') {
                this.beepAudio.resume();
            }
        },
        
        playBeep() {
            //console.log('Playing beep sound...');
            try {
                if (!this.beepAudio) {
                    this.initBeep();
                }
                if (this.beepAudio) {
                    if (this.beepAudio.state === 'suspended') {
                        this.beepAudio.resume().then(() => {
                            this.createBeepSound();
                        });
                    } else {
                        this.createBeepSound();
                    }
                } else {
                    this.systemBeep();
                }
            } catch (e) {
                //console.log('Beep failed:', e);
                this.systemBeep();
            }
        },
        
        createBeepSound() {
            try {
                const oscillator = this.beepAudio.createOscillator();
                const gainNode = this.beepAudio.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(this.beepAudio.destination);
                
                oscillator.frequency.setValueAtTime(1000, this.beepAudio.currentTime);
                gainNode.gain.setValueAtTime(0.1, this.beepAudio.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, this.beepAudio.currentTime + 0.2);
                
                oscillator.start(this.beepAudio.currentTime);
                oscillator.stop(this.beepAudio.currentTime + 0.2);
            } catch (e) {
                //console.log('Oscillator beep failed:', e);
                this.systemBeep();
            }
        },
        
        systemBeep() {
            try {
                const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIG2m98OScTgwOUarm7blmGgU7k9n1unEiBS13yO/eizEIHWq+8+OWT');
                audio.volume = 0.7;
                audio.play().catch(() => {
                    const beep2 = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIG2m98OScTgwOUarm7blmGgU7k9n1unEiBS13yO/eizEIHWq+8+OWT');
                    beep2.volume = 0.5;
                    beep2.play().catch(() => {
                        //console.log('All beep methods failed');
                    });
                });
            } catch (e) {
                //console.log('System beep failed:', e);
            }
        },
        
        async initDetector() {
            if ('BarcodeDetector' in window) {
                try {
                    const formats = ['ean_13','ean_8','code_128','code_39','upc_a','upc_e','qr_code'];
                    this.detector = new window.BarcodeDetector({ formats });
                    //console.log('Using native BarcodeDetector API');
                } catch (e) {
                    //console.log('BarcodeDetector failed:', e);
                    this.detector = null;
                }
            } else {
                //console.log('BarcodeDetector not supported, will use ZXing fallback');
            }
        },
        
        async enumerateCameras() {
            try {
                const devices = await navigator.mediaDevices.enumerateDevices();
                this.candidates = devices.filter(d => d.kind === 'videoinput');
                if (this.candidates.length === 0) {
                    this.candidates = [{ deviceId: undefined }];
                }
            } catch (e) {
                this.candidates = [{ deviceId: undefined }];
            }
        },
        
        async start() {
            // Set flag to indicate we should be running
            this.shouldRun = true;
            this.initBeep();
            await this.initDetector();
            await this.enumerateCameras();
            
            const constraints = {
                video: {
                    deviceId: this.candidates[this.currentDeviceIndex]?.deviceId || undefined,
                    facingMode: 'environment',
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            };
            
            try {
                this.stream = await navigator.mediaDevices.getUserMedia(constraints);
                const video = this.$refs.video;
                video.srcObject = this.stream;
                await video.play();
                this.track = this.stream.getVideoTracks()[0] || null;
                this.supportsTorch = !!(this.track && this.track.getCapabilities && this.track.getCapabilities().torch);
                this.running = true;
                
                if (this.detector) {
                    this.loop();
                } else {
                    await this.startZxingFallback(video);
                }
            } catch (e) {
                //console.log('Failed to start camera:', e);
                this.running = false;
                this.shouldRun = false;
            }
        },
        
        async startZxingFallback(videoEl) {
            try {
                //console.log('Using ZXing fallback for barcode detection');
                if (!window.ZXing) {
                    await this.loadZxing();
                }
                const codeReader = new ZXing.BrowserMultiFormatReader();
                this.zxingReader = codeReader;
                
                const devices = await codeReader.listVideoInputDevices();
                const deviceId = devices?.[this.currentDeviceIndex]?.deviceId;
                
                await codeReader.decodeFromVideoDevice(deviceId ?? null, videoEl, (result, err) => {
                    // Check if we should still be running
                    if (!this.shouldRun || !this.running) { 
                        return; 
                    }
                    if (result?.text) {
                        const now = Date.now();
                        const value = result.text.trim();
                        //console.log('ZXing: Detected barcode:', value);
                        
                        const isNewBarcode = value !== this.lastCode;
                        const isThrottleExpired = (now - this.lastScanAt) > this.throttleMs;
                        
                        if (value && (isNewBarcode || isThrottleExpired)) {
                            this.lastCode = value;
                            this.lastScanAt = now;
                            this.scanCounts[value] = (this.scanCounts[value] || 0) + 1;
                            //console.log('ZXing: Adding barcode to cart:', value, `(Scan #${this.scanCounts[value]})`);
                            this.playBeep();
                            $wire.addByBarcode(value);
                        } else {
                            //console.log('ZXing: Ignoring duplicate scan (throttled):', value);
                        }
                    }
                });
            } catch (e) {
                //console.log('ZXing fallback error:', e);
                try {
                    const codeReader = new ZXing.BrowserMultiFormatReader();
                    this.zxingReader = codeReader;
                    await codeReader.decodeFromVideoDevice(null, videoEl, (result, err) => {
                        // Check if we should still be running
                        if (!this.shouldRun || !this.running) { 
                            return; 
                        }
                        if (result?.text) {
                            const now = Date.now();
                            const value = result.text.trim();
                            //console.log('ZXing (fallback): Detected barcode:', value);
                            
                            const isNewBarcode = value !== this.lastCode;
                            const isThrottleExpired = (now - this.lastScanAt) > this.throttleMs;
                            
                            if (value && (isNewBarcode || isThrottleExpired)) {
                                this.lastCode = value;
                                this.lastScanAt = now;
                                this.scanCounts[value] = (this.scanCounts[value] || 0) + 1;
                                //console.log('ZXing (fallback): Adding barcode to cart:', value, `(Scan #${this.scanCounts[value]})`);
                                this.playBeep();
                                $wire.addByBarcode(value);
                            } else {
                                //console.log('ZXing (fallback): Ignoring duplicate scan (throttled):', value);
                            }
                        }
                    });
                } catch (e2) {
                    //console.log('ZXing fallback also failed:', e2);
                }
            }
        },
        
        async loadZxing() {
            return new Promise((resolve) => {
                const script = document.createElement('script');
                script.src = 'https://unpkg.com/@zxing/library@0.20.0/umd/index.min.js';
                script.onload = resolve;
                document.head.appendChild(script);
            });
        },
        
        stop() {
            //console.log('Stopping scanner...');
            // Set flag to stop all operations
            this.shouldRun = false;
            this.running = false;
            
            if (this.track) {
                this.track.stop();
                this.track = null;
            }
            if (this.stream) {
                this.stream.getTracks().forEach(t => t.stop());
                this.stream = null;
            }
            if (this.zxingReader) {
                try { 
                    this.zxingReader.reset(); 
                    this.zxingReader = null;
                } catch (e) {
                    //console.log('Error resetting ZXing:', e);
                }
            }
            // Clear video element
            const video = this.$refs.video;
            if (video) {
                video.srcObject = null;
            }
            // Reset scan counts
            this.scanCounts = {};
            this.lastCode = '';
            //console.log('Scanner stopped');
        },
        
        toggleTorch() {
            if (!this.track || !this.supportsTorch) { return; }
            const caps = this.track.getCapabilities();
            const settings = this.track.getSettings();
            const torchOn = !settings.torch;
            this.track.applyConstraints({ advanced: [{ torch: torchOn }] });
        },
        
        async switchCamera() {
            if (this.candidates.length <= 1) { return; }
            this.stop();
            this.currentDeviceIndex = (this.currentDeviceIndex + 1) % this.candidates.length;
            await this.start();
        },
        
        async loop() {
            // Check if we should still be running
            if (!this.shouldRun || !this.running) { 
                return; 
            }
            const video = this.$refs.video;
            if (this.detector && video.readyState >= 2) {
                try {
                    const barcodes = await this.detector.detect(video);
                    if (barcodes && barcodes.length > 0) {
                        const now = Date.now();
                        const value = (barcodes[0].rawValue || '').trim();
                        //console.log('Desktop: Detected barcode:', value);
                        
                        const isNewBarcode = value !== this.lastCode;
                        const isThrottleExpired = (now - this.lastScanAt) > this.throttleMs;
                        
                        if (value && (isNewBarcode || isThrottleExpired)) {
                            this.lastCode = value;
                            this.lastScanAt = now;
                            this.scanCounts[value] = (this.scanCounts[value] || 0) + 1;
                            //console.log('Desktop: Adding barcode to cart:', value, `(Scan #${this.scanCounts[value]})`);
                            this.playBeep();
                            $wire.addByBarcode(value);
                        } else {
                            //console.log('Desktop: Ignoring duplicate scan (throttled):', value);
                        }
                    }
                } catch (e) {
                    //console.log('Desktop: Barcode detection error:', e);
                }
            }
            // Only continue if we should still be running
            if (this.shouldRun && this.running) {
                requestAnimationFrame(() => this.loop());
            }
        },
    }));

    if (window.Alpine && typeof window.Alpine.data === 'function') {
        define();
    } else {
        document.addEventListener('alpine:init', define, { once: true });
    }
})();
</script>
@endpush