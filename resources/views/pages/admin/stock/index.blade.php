<?php

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Global Stock Management'])] class extends Component {
    public $shopId = '';
    public $shops;
    public $products;
    public $search = '';

    public function mount(): void
    {
        $this->shops = Shop::orderBy('name')->get();
        $this->loadProducts();
    }

    public function updatedShopId(): void
    {
        $this->loadProducts();
    }

    public function loadProducts(): void
    {
        $query = Product::query()->with(['category', 'shop']);
        if ($this->shopId) {
            $query->where('shop_id', $this->shopId);
        }
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%')
                    ->orWhere('barcode', 'like', '%' . $this->search . '%');
            });
        }
        $this->products = $query->orderBy('name')->get();
    }

    public function updatedSearch(): void
    {
        $this->loadProducts();
    }

    public function adjustStock(Product $product, float $delta): void
    {
        $newQty = max(0, (float) $product->stock_quantity + $delta);
        $product->update(['stock_quantity' => $newQty]);
        $this->loadProducts();
        session()->flash('success', 'Stock updated.');
    }
}; ?>

    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <flux:heading size="xl" level="1">Global Stock Management</flux:heading>
                <flux:subheading size="lg">Admin can view and adjust stock across shops</flux:subheading>
            </div>
        </div>

        <div class="flex items-center gap-4 max-md:flex-col">
            <div class="w-full md:w-72">
                <flux:select wire:model="shopId" label="Filter by Shop">
                    <option value="">All shops</option>
                    @foreach($shops as $shop)
                        <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div class="flex-1 w-full">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search products by name, description, or barcode..."
                    icon="magnifying-glass"
                />
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-800">
            @if($products->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px]">
                        <thead class="border-b border-neutral-200 dark:border-neutral-700">
                            <tr>
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Product</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Shop</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Stock</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Adjust</th>
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
                                        <flux:text class="font-medium">{{ $product->shop?->name }}</flux:text>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3">
                                        <div class="flex items-center gap-2">
                                            <flux:text class="font-medium">{{ number_format($product->stock_quantity, 2) }}</flux:text>
                                            <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ $product->unit_type }}</flux:badge>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3">
                                        <div class="flex items-center gap-2">
                                            <flux:button size="sm" variant="outline" wire:click="adjustStock('{{ $product->id }}', -1)">-1</flux:button>
                                            <flux:button size="sm" variant="outline" wire:click="adjustStock('{{ $product->id }}', 1)">+1</flux:button>
                                            <flux:button size="sm" variant="outline" wire:click="adjustStock('{{ $product->id }}', -5)">-5</flux:button>
                                            <flux:button size="sm" variant="outline" wire:click="adjustStock('{{ $product->id }}', 5)">+5</flux:button>
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
                </div>
            @endif
        </div>
    </div>


