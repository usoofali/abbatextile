<?php

use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Stock Management'])] class extends Component {
    public $shop;
    public $products;
    public $search = '';
    public $stockFilter = '';
    public $lowStockThreshold = 5;
    
    // Modal properties
    public $showAdjustModal = false;
    public $selectedProduct = null;
    public $adjustmentType = 'add';
    public $adjustmentQuantity = 1;

    public function mount(): void
    {
        $this->shop = Auth::user()->managedShop;
        if (!$this->shop) {
            session()->flash('error', 'No shop assigned to you.');
            return;
        }
        $this->loadProducts();
    }

    public function loadProducts(): void
    {
        if (!$this->shop) return;

        $query = $this->shop->products()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%')
                    ->orWhere('barcode', 'like', '%' . $this->search . '%');
            })
            ->orderBy('name');

        if ($this->stockFilter === 'out') {
            $query->where('stock_quantity', '<=', 0);
        } elseif ($this->stockFilter === 'low') {
            $query->where('stock_quantity', '>', 0)
                  ->where('stock_quantity', '<=', $this->lowStockThreshold);
        }

        $this->products = $query->get();
    }

    public function updatedSearch(): void
    {
        $this->loadProducts();
    }

    public function openAdjustModal(Product $product): void
    {
        // Verify the product belongs to the manager's shop
        if ($product->shop_id !== $this->shop->id) {
            session()->flash('error', 'Unauthorized stock change.');
            return;
        }

        $this->selectedProduct = $product;
        $this->adjustmentType = 'add';
        $this->adjustmentQuantity = 1;
        $this->showAdjustModal = true;
    }

    public function closeAdjustModal(): void
    {
        $this->showAdjustModal = false;
        $this->selectedProduct = null;
        $this->adjustmentType = 'add';
        $this->adjustmentQuantity = 1;
    }

    public function adjustStock(): void
    {
        $this->validate([
            'adjustmentQuantity' => 'required|numeric|min:0.01',
        ]);

        if (!$this->selectedProduct || $this->selectedProduct->shop_id !== $this->shop->id) {
            session()->flash('error', 'Unauthorized stock change.');
            return;
        }

        $delta = $this->adjustmentType === 'add' 
            ? (float) $this->adjustmentQuantity 
            : -(float) $this->adjustmentQuantity;

        $newQty = max(0, (float) $this->selectedProduct->stock_quantity + $delta);
        $this->selectedProduct->update(['stock_quantity' => $newQty]);
        
        $this->loadProducts();
        $this->closeAdjustModal();
        
        session()->flash('success', "Stock {$this->adjustmentType}ed successfully. New quantity: {$newQty}");
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <flux:heading size="xl" level="1">Stock Management</flux:heading>
            <flux:subheading size="lg">{{ $shop?->name ?? 'No shop assigned' }}</flux:subheading>
        </div>
    </div>

    @if($shop)
        <div class="flex items-center gap-4 max-md:flex-col">
            <div class="flex-1 w-full">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search products by name, description, or barcode..."
                    icon="magnifying-glass"
                />
            </div>
            <div class="w-full md:w-64">
                <flux:select wire:model.live="stockFilter" label="Stock Level">
                    <option value="">All</option>
                    <option value="low">Low stock</option>
                    <option value="out">Out of stock</option>
                </flux:select>
            </div>
            <div class="w-full md:w-40" x-show="@js($stockFilter==='low')">
                <flux:input type="number" min="1" step="1" wire:model.live="lowStockThreshold" label="Low stock ≤" />
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-800">
            @if($products->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px]">
                        <thead class="border-b border-neutral-200 dark:border-neutral-700">
                            <tr>
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Product</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Stock</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Status</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach($products as $product)
                                <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/50">
                                    <td class="px-3 sm:px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            @if($product->photo)
                                                <img src="{{ Storage::disk('public')->url($product->photo) }}" alt="{{ $product->name }}" class="h-12 w-12 shrink-0 rounded object-cover" />
                                            @else
                                                <div class="h-12 w-12 shrink-0 rounded bg-neutral-100 dark:bg-neutral-700 flex items-center justify-center">
                                                    <flux:icon name="photo" class="text-neutral-400" />
                                                </div>
                                            @endif
                                            <div>
                                                <flux:text class="font-medium">{{ $product->name }}</flux:text>
                                                <div class="flex items-center gap-2 mt-1">
                                                    <flux:badge variant="outline" size="sm">{{ ucfirst($product->unit_type) }}</flux:badge>
                                                    @if($product->barcode)
                                                        <flux:badge variant="outline" size="xs" class="text-xs">#{{ $product->barcode }}</flux:badge>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3">
                                        <div class="flex items-center gap-2">
                                            <flux:text class="font-medium">{{ number_format($product->stock_quantity, 2) }}</flux:text>
                                            <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ $product->unit_type }}</flux:text>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3">
                                        @php
                                            $qty = (float) $product->stock_quantity;
                                            $low = (int) $lowStockThreshold;
                                        @endphp
                                        @if($qty <= 0)
                                            <flux:badge variant="red">Out of stock</flux:badge>
                                        @elseif($qty <= $low)
                                            <flux:badge variant="amber">Low stock</flux:badge>
                                        @else
                                            <flux:badge variant="green">In stock</flux:badge>
                                        @endif
                                    </td>
                                    <td class="px-3 sm:px-6 py-3">
                                        <flux:button 
                                            size="sm" 
                                            variant="outline" 
                                            wire:click="openAdjustModal('{{ $product->id }}')"
                                        >
                                            <flux:icon name="adjustments-horizontal" class="size-4" />
                                            Adjust Stock
                                        </flux:button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-12 text-center">
                    <flux:icon name="cube" class="mx-auto size-12 text-neutral-400" />
                    <flux:heading size="lg" class="mt-4">No products found</flux:heading>
                </div>
            @endif
        </div>

        <!-- Adjust Stock Modal -->
        <flux:modal wire:model.self="showAdjustModal" class="md:max-w-md">
            <div class="space-y-6">
                <!-- Modal Header -->
                <div>
                    <flux:heading size="lg">Adjust Stock</flux:heading>
                    <flux:text class="mt-2">Update the stock quantity for this product.</flux:text>
                </div>

                <!-- Product Info -->
                <div class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                    <div class="flex items-center gap-3">
                        @if($selectedProduct?->photo)
                            <img src="{{ Storage::disk('public')->url($selectedProduct->photo) }}" alt="{{ $selectedProduct->name }}" class="h-12 w-12 shrink-0 rounded object-cover" />
                        @else
                            <div class="h-12 w-12 shrink-0 rounded bg-neutral-100 dark:bg-neutral-700 flex items-center justify-center">
                                <flux:icon name="photo" class="text-neutral-400" />
                            </div>
                        @endif
                        <div>
                            <flux:text class="font-medium">{{ $selectedProduct?->name }}</flux:text>
                            <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">
                                Current stock: {{ number_format($selectedProduct?->stock_quantity ?? 0, 2) }} {{ $selectedProduct?->unit_type }}
                            </flux:text>
                        </div>
                    </div>
                </div>

                <!-- Adjustment Form -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <flux:label for="adjustmentType">Adjustment Type</flux:label>
                        <flux:select wire:model="adjustmentType" id="adjustmentType">
                            <option value="add">Add Stock</option>
                            <option value="subtract">Subtract Stock</option>
                        </flux:select>
                    </div>
                    
                    <div>
                        <flux:label for="adjustmentQuantity">Quantity</flux:label>
                        <flux:input 
                            wire:model="adjustmentQuantity" 
                            id="adjustmentQuantity"
                            type="number"
                            step="0.01"
                            min="0.01"
                            placeholder="Enter quantity"
                        />
                    </div>
                </div>

                <!-- Preview -->
                @if($adjustmentQuantity && $selectedProduct)
                    @php
                        $newQuantity = $adjustmentType === 'add' 
                            ? $selectedProduct->stock_quantity + (float) $adjustmentQuantity
                            : max(0, $selectedProduct->stock_quantity - (float) $adjustmentQuantity);
                    @endphp
                    <div class="rounded-lg bg-neutral-50 p-3 dark:bg-neutral-800">
                        <flux:text class="text-sm font-medium">
                            New stock will be: 
                            <span class="{{ $newQuantity < 0 ? 'text-red-600' : 'text-green-600' }}">
                                {{ number_format($newQuantity, 2) }} {{ $selectedProduct->unit_type }}
                            </span>
                        </flux:text>
                        @if($newQuantity < 0)
                            <flux:text class="text-xs text-red-600 mt-1">
                                Warning: Stock cannot be negative. It will be set to 0.
                            </flux:text>
                        @endif
                    </div>
                @endif

                <!-- Modal Actions -->
                <div class="flex gap-3">
                    <flux:spacer />
                    <flux:button variant="outline" wire:click="closeAdjustModal">Cancel</flux:button>
                    <flux:button 
                        variant="primary" 
                        wire:click="adjustStock"
                        wire:loading.attr="disabled"
                    >
                        <flux:icon name="check" class="size-4" />
                        Confirm Adjustment
                    </flux:button>
                </div>
            </div>
        </flux:modal>
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