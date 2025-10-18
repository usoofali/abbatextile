<?php

use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Manage Shops'])] class extends Component {
    public $shops;
    public $search = '';
    public $showDeleteModal = false;
    public $deleteShopId = null;

    public function mount(): void
    {
        $this->loadShops();
    }

    public function loadShops(): void
    {
        $this->shops = Shop::with(['manager', 'salesTransactions' => function ($query) {
                $query->where('status', '!=', 'cancelled');
            }])
            ->withCount(['salesTransactions' => function ($query) {
                $query->where('status', '!=', 'cancelled');
            }])
            ->withSum(['salesTransactions' => function ($query) {
                $query->where('status', '!=', 'cancelled');
            }], 'total_amount')
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('location', 'like', '%' . $this->search . '%');
            })
            ->latest()
            ->get();
    }

    public function updatedSearch(): void
    {
        $this->loadShops();
    }

    public function deleteShop(string $id): void
    {
        $shop = Shop::findOrFail($id);
        
        // Check if shop has sales transactions (including cancelled ones for deletion logic)
        if ($shop->salesTransactions()->count() > 0) {
            session()->flash('error', 'Cannot delete shop with existing sales records.');
            return;
        }

        // Check if shop has products
        if ($shop->products()->count() > 0) {
            session()->flash('error', 'Cannot delete shop with existing products.');
            return;
        }

        // Delete associated users (managers and salespersons)
        $shop->salespersons()->delete();
        if ($shop->manager) {
            $shop->manager->delete();
        }

        $shop->delete();
        $this->loadShops();
        session()->flash('success', 'Shop deleted successfully.');
    }

    public function promptDelete(string $id): void
    {
        $this->deleteShopId = $id;
        $this->showDeleteModal = true;
    }

    public function confirmDelete(): void
    {
        if ($this->deleteShopId) {
            $this->deleteShop($this->deleteShopId);
        }
        $this->showDeleteModal = false;
        $this->deleteShopId = null;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->deleteShopId = null;
    }
}; ?>
<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <flux:heading size="xl" level="1">Manage Shops</flux:heading>
            <flux:subheading size="lg">View and manage all textile shops</flux:subheading>
        </div>
        <flux:button variant="primary" :href="route('admin.shops.create')" wire:navigate class="max-md:w-full">
            <flux:icon name="plus" />
            Add New Shop
        </flux:button>
    </div>

    <!-- Search and Filters -->
    <div class="flex items-center gap-4 max-md:flex-col">
        <div class="flex-1 w-full">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search shops by name or location..."
                icon="magnifying-glass"
            />
        </div>
    </div>

    <!-- Shops Table -->
    <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-800">
        @if($shops->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px]">
                    <thead class="border-b border-neutral-200 dark:border-neutral-700">
                        <tr>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Shop Details</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Manager</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Sales Stats</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Revenue</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach($shops as $shop)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/50">
                                <td class="px-3 sm:px-6 py-3">
                                    <div>
                                        <flux:text class="font-medium">{{ $shop->name }}</flux:text>
                                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ $shop->location }}</flux:text>
                                        @if($shop->description)
                                            <flux:text class="text-sm text-neutral-500 dark:text-neutral-500">{{ Str::limit($shop->description, 50) }}</flux:text>
                                        @endif
                                        <flux:text class="text-xs text-neutral-500 dark:text-neutral-500">
                                            {{ $shop->products()->count() }} products
                                        </flux:text>
                                    </div>
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    @if($shop->manager)
                                        <div>
                                            <flux:text class="font-medium">{{ $shop->manager->name }}</flux:text>
                                            <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ $shop->manager->email }}</flux:text>
                                        </div>
                                    @else
                                        <flux:text class="text-sm text-neutral-500 dark:text-neutral-500">No manager assigned</flux:text>
                                    @endif
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    <div>
                                        <flux:text class="font-medium">{{ $shop->sales_transactions_count }} active sales</flux:text>
                                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ $shop->salespersons()->count() }} salespersons</flux:text>
                                    </div>
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    <div>
                                        <flux:text class="font-medium text-green-600 dark:text-green-400">₦{{ number_format($shop->sales_transactions_sum_total_amount ?? 0, 2) }}</flux:text>
                                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Active Revenue</flux:text>
                                    </div>
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:gap-2">
                                        <flux:button 
                                        variant="ghost" 
                                        icon="pencil"
                                        size="sm" :href="route('admin.shops.edit', $shop)" wire:navigate class="w-full sm:w-auto">
                                            
                                            Edit
                                        </flux:button>
                                        <flux:button 
                                            variant="ghost" 
                                            icon="trash"
                                            size="sm" 
                                            wire:click="promptDelete('{{ $shop->id }}')"
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
                <flux:icon name="building-office" class="mx-auto size-12 text-neutral-400" />
                <flux:heading size="lg" class="mt-4">No shops found</flux:heading>
                <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">
                    @if($search)
                        No shops match your search criteria.
                    @else
                        Get started by creating your first shop.
                    @endif
                </flux:text>
                @if(!$search)
                    <div class="mt-6">
                        <flux:button variant="primary" :href="route('admin.shops.create')" wire:navigate>
                            <flux:icon name="plus" />
                            Create First Shop
                        </flux:button>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <!-- Delete Confirmation Modal -->
    <flux:modal wire:model="showDeleteModal">
        <div class="p-6">
            <flux:heading size="lg">Delete shop?</flux:heading>
            <flux:text class="mt-2 text-neutral-600 dark:text-neutral-300">
                Are you sure you want to delete this shop? This action cannot be undone.
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