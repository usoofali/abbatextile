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

        $this->products = $this->shop->products()
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

    public function adjustStock(Product $product, float $delta): void
    {
        if ($product->shop_id !== $this->shop->id) {
            session()->flash('error', 'Unauthorized stock change.');
            return;
        }

        $newQty = max(0, (float) $product->stock_quantity + $delta);
        $product->update(['stock_quantity' => $newQty]);
        $this->loadProducts();
        session()->flash('success', 'Stock updated.');
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
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-800">
                @if($products->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px]">
                            <thead class="border-b border-neutral-200 dark:border-neutral-700">
                                <tr>
                                    <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Product</th>
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
                                            <div class="flex items-center gap-2">
                                                <flux:text class="font-medium">{{ number_format($product->stock_quantity, 2) }}</flux:text>
                                                <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ $product->unit_type }}</flux:text>
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
        @endif
    </div>


