<?php

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Manage Products'])] class extends Component {
    public $products;
    public $search = '';
    public $shop;
    public $showDeleteModal = false;
    public $productToDelete = null;
    public $showBarcodeModal = false;
    public $barcodeSearch = '';
    public $selectedProducts = [];
    public $barcodeProducts = [];

    public function mount(): void
    {
        $user = Auth::user();
        $this->shop = $user->managedShop;
        
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
            ->with(['category', 'saleItems'])
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->get();
    }

    public function updatedSearch(): void
    {
        $this->loadProducts();
    }

    public function updatedBarcodeSearch(): void
    {
        $this->searchBarcodeProducts();
    }

    public function searchBarcodeProducts(): void
    {
        if (!$this->shop) return;

        $this->barcodeProducts = $this->shop->products()
            ->where(function($query) {
                $query->where('name', 'like', '%' . $this->barcodeSearch . '%')
                      ->orWhere('barcode', 'like', '%' . $this->barcodeSearch . '%');
            })
            ->limit(10)
            ->get();
    }

    public function updatedSelectedProducts(): void
    {
        // This will be called when selectedProducts changes
    }

    public function toggleProductSelection($productId): void
    {
        $productId = (string) $productId;
        
        if (in_array($productId, $this->selectedProducts)) {
            $this->selectedProducts = array_values(array_diff($this->selectedProducts, [$productId]));
        } else {
            $this->selectedProducts = array_values([...$this->selectedProducts, $productId]);
        }
        
        // Force reactivity
        $this->dispatch('selected-products-updated');
    }


    public function generateBarcodes(): void
    {
        if (empty($this->selectedProducts)) {
            session()->flash('error', 'Please select at least one product to generate barcodes.');
            return;
        }

        // Verify all selected products belong to the manager's shop
        $validProductIds = $this->shop->products()
            ->whereIn('id', $this->selectedProducts)
            ->pluck('id')
            ->map(fn($id) => (string) $id)
            ->toArray();

        if (count($validProductIds) !== count($this->selectedProducts)) {
            session()->flash('error', 'Some selected products are not available in your shop.');
            return;
        }

        // Close the modal first
        $this->closeBarcodeModal();

        // Store the selected products in session and redirect
        session(['barcode_products' => $validProductIds]);
        
        // Redirect to the barcode printable Volt component
        $this->redirectRoute('manager.products.barcodes');
    }

    public function openBarcodeModal(): void
    {
        $this->showBarcodeModal = true;
        $this->barcodeSearch = '';
        $this->selectedProducts = [];
        $this->barcodeProducts = [];
    }

    public function closeBarcodeModal(): void
    {
        $this->showBarcodeModal = false;
        $this->barcodeSearch = '';
        $this->selectedProducts = [];
        $this->barcodeProducts = [];
    }

    public function confirmDelete(): void
    {
        if (!$this->productToDelete) {
            return;
        }

        // Verify the product belongs to the manager's shop
        if ($this->productToDelete->shop_id !== $this->shop->id) {
            session()->flash('error', 'You can only delete products from your shop.');
            $this->showDeleteModal = false;
            return;
        }

        if ($this->productToDelete->saleItems()->count() > 0) {
            session()->flash('error', 'Cannot delete product with existing sales records.');
            $this->showDeleteModal = false;
            return;
        }

        $this->productToDelete->delete();
        $this->loadProducts();
        $this->showDeleteModal = false;
        session()->flash('success', 'Product deleted successfully.');
    }

    public function promptDelete($productId): void
    {
        $product = Product::find($productId);
        
        // Verify the product belongs to the manager's shop
        if (!$product || $product->shop_id !== $this->shop->id) {
            session()->flash('error', 'Product not found in your shop.');
            return;
        }

        $this->productToDelete = $product;
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->productToDelete = null;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <flux:heading size="xl" level="1">Manage Products</flux:heading>
            <flux:subheading size="lg">{{ $shop?->name ?? 'No shop assigned' }}</flux:subheading>
        </div>
        @if($shop)
            <div class="flex flex-col sm:flex-row gap-2 max-md:w-full">
                <flux:button icon="qr-code" variant="outline" wire:click="openBarcodeModal" class="max-md:w-full">
                    Generate Barcodes
                </flux:button>
                <flux:button variant="primary" :href="route('manager.products.create')" wire:navigate class="max-md:w-full">
                    <flux:icon name="plus" />
                    Add Product
                </flux:button>
            </div>
        @endif
    </div>

    @if($shop)
        <!-- Search -->
        <div class="flex items-center gap-4 max-md:flex-col">
            <div class="flex-1 w-full">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search products by name or description..."
                    icon="magnifying-glass"
                />
            </div>
        </div>

        <!-- Products Table -->
        <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-800">
            @if($products->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px]">
                        <thead class="border-b border-neutral-200 dark:border-neutral-700">
                            <tr>
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Product</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Price / Unit</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Stock</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Sales Stats</th>
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
                                                @if($product->description)
                                                    <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ Str::limit($product->description, 50) }}</flux:text>
                                                @endif
                                                <div class="flex items-center gap-2 mt-1">
                                                    <flux:badge variant="outline" size="sm">{{ ucfirst($product->unit_type) }}</flux:badge>
                                                    @if($product->stock_quantity < 20)
                                                        <flux:badge variant="amber" size="sm">Low Stock</flux:badge>
                                                    @endif
                                                    <div class="flex items-center gap-1 mt-1">
                                                        @if($product->category)
                                                            <flux:badge variant="secondary" size="xs" class="text-xs">{{ $product->category->name }}</flux:badge>
                                                        @endif
                                                        @if($product->barcode)
                                                            <flux:badge variant="outline" size="xs" class="text-xs">#{{ $product->barcode }}</flux:badge>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3">
                                        <div class="text-sm">
                                            <flux:text class="font-medium">₦{{ number_format($product->price_per_unit, 2) }} / {{ $product->unit_type }}</flux:text>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3">
                                        <div class="flex items-center gap-2">
                                            <flux:text class="font-medium">{{ number_format($product->stock_quantity, 2) }}</flux:text>
                                            <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ $product->unit_type }}</flux:text>
                                        </div>
                                        <flux:text class="text-xs text-neutral-600 dark:text-neutral-400">
                                            Value: ₦{{ number_format($product->current_value, 2) }}
                                        </flux:text>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3">
                                        <div class="text-sm">
                                            <flux:text class="font-medium">{{ number_format($product->total_sold) }} sold</flux:text>
                                            <flux:text class="text-neutral-600 dark:text-neutral-400">
                                                ₦{{ number_format($product->total_revenue, 2) }} revenue
                                            </flux:text>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3">
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:gap-2">
                                            <flux:button 
                                                variant="ghost"
                                                icon="pencil"
                                                size="sm" 
                                                :href="route('manager.products.edit', $product)" 
                                                wire:navigate 
                                                class="w-full sm:w-auto"
                                            >
                                                Edit
                                            </flux:button>
                                            <flux:button 
                                                variant="ghost" 
                                                icon="trash"
                                                size="sm" 
                                                wire:click="promptDelete('{{ $product->id }}')"
                                                class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 w-full sm:w-auto"
                                            >
                                                Delete
                                            </flux:button>
                                        </div>
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
                    <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">
                        @if($search)
                            No products match your search criteria.
                        @else
                            Get started by adding your first product.
                        @endif
                    </flux:text>
                    @if(!$search)
                        <div class="mt-6">
                            <flux:button variant="primary" :href="route('manager.products.create')" wire:navigate>
                                <flux:icon name="plus" />
                                Add First Product
                            </flux:button>
                        </div>
                    @endif
                </div>
            @endif
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

    <!-- Barcode Generation Modal -->
    <flux:modal wire:model="showBarcodeModal" max-width="4xl">
        <div class="p-6">
            <flux:heading size="xl">Generate Product Barcodes</flux:heading>
            <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">
                Select products to generate printable barcode labels
            </flux:text>

            <!-- Search Products -->
            <div class="mt-6">
                <flux:field>
                    <flux:label>Search Products</flux:label>
                    <flux:input
                        wire:model.live.debounce.300ms="barcodeSearch"
                        placeholder="Search by product name or barcode..."
                        icon="magnifying-glass"
                    />
                </flux:field>
            </div>

            <!-- Selected Products Count -->
            @if(count($selectedProducts) > 0)
                <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <flux:text class="text-blue-800 dark:text-blue-200 font-medium">
                        {{ count($selectedProducts) }} product(s) selected for barcode generation
                    </flux:text>
                </div>
            @endif

            <!-- Products List -->
            <div class="mt-6 max-h-96 overflow-y-auto border border-neutral-200 dark:border-neutral-700 rounded-lg">
                @if(count($barcodeProducts) > 0)
                    <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach($barcodeProducts as $product)
                            <div class="p-4 hover:bg-neutral-50 dark:hover:bg-neutral-700/50">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        wire:click="toggleProductSelection('{{ $product->id }}')"
                                        {{ in_array((string) $product->id, $selectedProducts) ? 'checked' : '' }}
                                        class="rounded border-neutral-300 text-blue-600 focus:ring-blue-500 dark:border-neutral-600 dark:bg-neutral-800"
                                    >
                                    <div class="flex-1">
                                        <flux:text class="font-medium">{{ $product->name }}</flux:text>
                                        <div class="flex items-center gap-2 mt-1">
                                            @if($product->barcode)
                                                <flux:badge variant="outline" size="sm">Barcode: {{ $product->barcode }}</flux:badge>
                                            @else
                                                <flux:badge variant="red" size="sm">No Barcode</flux:badge>
                                            @endif
                                            <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">
                                                ₦{{ number_format($product->price_per_unit, 2) }} / {{ $product->unit_type }}
                                            </flux:text>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-8 text-center">
                        <flux:icon name="magnifying-glass" class="mx-auto size-8 text-neutral-400" />
                        <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">
                            @if($barcodeSearch)
                                No products found matching "{{ $barcodeSearch }}"
                            @else
                                Start typing to search for products
                            @endif
                        </flux:text>
                    </div>
                @endif
            </div>

            <!-- Action Buttons -->
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="outline" wire:click="closeBarcodeModal">
                    Cancel
                </flux:button>
                <flux:button 
                    variant="primary" 
                    wire:click="generateBarcodes"
                    :disabled="count($selectedProducts) === 0"
                    icon="qr-code"
                >
                    Generate Barcodes ({{ count($selectedProducts) }})
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal wire:model="showDeleteModal">
        <div class="p-6">
            <flux:heading size="lg">Delete product?</flux:heading>
            <flux:text class="mt-2 text-neutral-600 dark:text-neutral-300">
                Are you sure you want to delete this product? This action cannot be undone.
            </flux:text>
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="outline" wire:click="cancelDelete">Cancel</flux:button>
                <flux:button variant="primary" class="bg-red-600 hover:bg-red-700 text-white dark:bg-red-500 dark:hover:bg-red-400" wire:click="confirmDelete">Delete</flux:button>
            </div>
        </div>
    </flux:modal>

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