<?php

use App\Models\Sale;
use App\Models\Shop;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app', ['title' => 'Sales Management'])] class extends Component {
    use WithPagination;

    public $shop;
    public $search = '';
    public $statusFilter = '';
    public $dateFrom = '';
    public $dateTo = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->shop = $user->shop;

        if (!$this->shop) {
            session()->flash('error', 'No shop assigned to you.');
            return;
        }
    }

    public function getSales()
    {
        return $this->shop->salesTransactions()
            ->with(['salesperson', 'items.product', 'payments'])
            ->when($this->search, function ($query) {
                $query->whereHas('salesperson', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->dateFrom, function ($query) {
                $query->whereDate('created_at', '>=', $this->dateFrom);
            })
            ->when($this->dateTo, function ($query) {
                $query->whereDate('created_at', '<=', $this->dateTo);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function cancelSale($saleId): void
    {
        $sale = Sale::find($saleId);
        
        if (!$sale) {
            session()->flash('error', 'Sale not found.');
            return;
        }

        if ($sale->status === 'cancelled') {
            session()->flash('error', 'Sale is already cancelled.');
            return;
        }

        $sale->cancel();
        session()->flash('success', 'Sale cancelled successfully.');
    }

    public function getStatusBadgeClass($status): string
    {
        return match ($status) {
            'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400',
            'paid' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
            'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400',
        };
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">Sales Management</flux:heading>
            <flux:text size="lg" class="text-neutral-600 dark:text-neutral-400">{{ $shop?->name ?? 'No shop assigned' }}</flux:text>
        </div>
        <flux:button variant="outline" :href="route('salesperson.dashboard')" wire:navigate>
            <flux:icon name="arrow-left" />
            Back to Dashboard
        </flux:button>
    </div>

    @if($shop)
        <!-- Filters -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <flux:field>
                    <flux:label>Search Salesperson</flux:label>
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search by salesperson name..."
                        icon="magnifying-glass"
                    />
                </flux:field>

                <flux:field>
                    <flux:label>Status</flux:label>
                    <flux:select wire:model.live="statusFilter">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="cancelled">Cancelled</option>
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>Date From</flux:label>
                    <flux:input
                        wire:model.live="dateFrom"
                        type="date"
                    />
                </flux:field>

                <flux:field>
                    <flux:label>Date To</flux:label>
                    <flux:input
                        wire:model.live="dateTo"
                        type="date"
                    />
                </flux:field>
            </div>
        </div>

        <!-- Sales Table -->
        <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-800">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-neutral-200 dark:border-neutral-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Sale ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Salesperson</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Items</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Total Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-neutral-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @forelse($this->getSales() as $sale)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:text class="font-mono text-sm">{{ Str::limit($sale->id, 8) }}</flux:text>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:text class="text-sm font-medium">{{ $sale->salesperson->name }}</flux:text>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:text class="text-sm">{{ $sale->items->count() }} items</flux:text>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:text class="text-sm font-medium">₦{{ number_format($sale->total_amount, 2) }}</flux:text>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $this->getStatusBadgeClass($sale->status) }}">
                                        {{ ucfirst($sale->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:text class="text-sm">{{ $sale->created_at->format('M d, Y H:i') }}</flux:text>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center gap-2">
                                        <flux:button 
                                            variant="ghost" 
                                            size="sm"
                                            wire:click="$dispatch('show-sale-details', { saleId: '{{ $sale->id }}' })"
                                        >
                                            <flux:icon name="eye" class="size-4" />
                                        </flux:button>
                                        
                                        @if($sale->status !== 'cancelled')
                                            <flux:button 
                                                variant="ghost" 
                                                size="sm"
                                                wire:click="cancelSale('{{ $sale->id }}')"
                                                class="text-red-600"
                                                wire:confirm="Are you sure you want to cancel this sale? This will restore the stock."
                                            >
                                                <flux:icon name="x-mark" class="size-4" />
                                            </flux:button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <flux:icon name="document-text" class="mx-auto size-12 text-neutral-400" />
                                    <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">No sales found</flux:text>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-neutral-200 dark:border-neutral-700">
                {{ $this->getSales()->links() }}
            </div>
        </div>
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