<?php

use App\Models\Sale;
use App\Models\Shop;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'My Sales'])] class extends Component {
    public $sales;
    public $search = '';
    public $shop;
    public $dateFilter = 'all'; // all, today, week, month, year, custom
    public $startDate;
    public $endDate;

    public function mount(): void
    {
        $user = Auth::user();
        $this->shop = $user->shop;

        if (!$this->shop) {
            session()->flash('error', 'No shop assigned to you.');
            return;
        }

        // Set default date range to current month
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->endOfMonth()->format('Y-m-d');

        $this->loadSales();
    }

    public function loadSales(): void
    {
        if (!$this->shop) return;

        $user = Auth::user();
        $query = $user->salesTransactions()
            ->with(['items.product', 'shop'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('items.product', function ($productQuery) {
                        $productQuery->where('name', 'like', '%' . $this->search . '%');
                    })
                    ->orWhere('id', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->dateFilter !== 'all', function ($query) {
                $this->applyDateFilter($query);
            })
            ->latest();

        $this->sales = $query->get();
    }

    private function applyDateFilter($query): void
    {
        match ($this->dateFilter) {
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ]),
            'month' => $query->whereBetween('created_at', [
                now()->startOfMonth(),
                now()->endOfMonth()
            ]),
            'year' => $query->whereBetween('created_at', [
                now()->startOfYear(),
                now()->endOfYear()
            ]),
            'custom' => $query->whereBetween('created_at', [
                $this->startDate . ' 00:00:00',
                $this->endDate . ' 23:59:59'
            ]),
            default => null
        };
    }

    public function applyFilter(): void
    {
        $this->loadSales();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->dateFilter = 'all';
        $this->startDate = now()->startOfMonth()->format('Y-m-d');
        $this->endDate = now()->endOfMonth()->format('Y-m-d');
        $this->loadSales();
    }

    public function updatedSearch(): void
    {
        $this->loadSales();
    }

    public function updatedDateFilter(): void
    {
        // Set default dates when switching to custom filter
        if ($this->dateFilter === 'custom') {
            $this->startDate = now()->startOfMonth()->format('Y-m-d');
            $this->endDate = now()->endOfMonth()->format('Y-m-d');
        }
        $this->loadSales();
    }

    public function updatedStartDate(): void
    {
        if ($this->dateFilter === 'custom') {
            $this->loadSales();
        }
    }

    public function updatedEndDate(): void
    {
        if ($this->dateFilter === 'custom') {
            $this->loadSales();
        }
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">My Sales History</flux:heading>
            <flux:subheading size="lg">{{ $shop?->name ?? 'No shop assigned' }}</flux:subheading>
        </div>
        <flux:button variant="outline" :href="route('salesperson.dashboard')" wire:navigate>
            <flux:icon name="arrow-left" />
            Back to Dashboard
        </flux:button>
    </div>

    @if($shop)
        <!-- Filters -->
        <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <!-- Search -->
                <div class="flex-1">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search sales by product name or sale ID..."
                        icon="magnifying-glass"
                    />
                </div>

                <!-- Date Filters -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:gap-4">
                    <!-- Quick Date Filters -->
                    <div class="flex items-center gap-2">
                        <flux:label value="Date Range:" class="shrink-0 text-sm font-medium" />
                        <flux:select wire:model.live="dateFilter" class="min-w-[120px]">
                            <option value="all">All Time</option>
                            <option value="today">Today</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="year">This Year</option>
                            <option value="custom">Custom Range</option>
                        </flux:select>
                    </div>

                    <!-- Custom Date Range -->
                    @if($dateFilter === 'custom')
                        <div class="flex items-center gap-2">
                            <flux:input
                                type="date"
                                wire:model.live="startDate"
                                class="min-w-[140px]"
                            />
                            <flux:text class="text-neutral-500">to</flux:text>
                            <flux:input
                                type="date"
                                wire:model.live="endDate"
                                class="min-w-[140px]"
                            />
                        </div>
                    @endif

                    <!-- Reset Filters -->
                    <flux:button icon="arrow-path" variant="outline" wire:click="resetFilters" class="shrink-0">
                        Reset
                    </flux:button>
                </div>
            </div>

            <!-- Active Filters Badge -->
            @if($search || $dateFilter !== 'all')
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Active filters:</flux:text>
                    @if($search)
                        <flux:badge variant="outline" size="sm">
                            Search: "{{ $search }}"
                            <button wire:click="$set('search', '')" class="ml-1 hover:text-red-500">
                                <flux:icon name="x-mark" class="size-3" />
                            </button>
                        </flux:badge>
                    @endif
                    @if($dateFilter !== 'all')
                        <flux:badge variant="outline" size="sm">
                            @php
                                $filterLabels = [
                                    'today' => 'Today',
                                    'week' => 'This Week',
                                    'month' => 'This Month',
                                    'year' => 'This Year',
                                    'custom' => 'Custom Range: ' . \Carbon\Carbon::parse($startDate)->format('M j, Y') . ' - ' . \Carbon\Carbon::parse($endDate)->format('M j, Y')
                                ];
                            @endphp
                            {{ $filterLabels[$dateFilter] }}
                            <button wire:click="$set('dateFilter', 'all')" class="ml-1 hover:text-red-500">
                                <flux:icon name="x-mark" class="size-3" />
                            </button>
                        </flux:badge>
                    @endif
                </div>
            @endif
        </div>

        <!-- Sales Table -->
        <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-800">
            @if($sales->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[800px]">
                        <thead class="border-b border-neutral-200 dark:border-neutral-700">
                            <tr>
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Sale ID</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Items</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Amount</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Status</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach($sales as $sale)
                                <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/50">
                                    <td class="px-3 sm:px-6 py-3">
                                        <div>
                                            <flux:text class="font-mono text-sm font-medium">#{{ substr($sale->id, -8) }}</flux:text>
                                            <flux:text class="text-xs text-neutral-600 dark:text-neutral-400">
                                                {{ $sale->items->count() }} items
                                            </flux:text>
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3">
                                        <div class="space-y-1 max-w-xs">
                                            @foreach($sale->items->take(3) as $item)
                                                <div class="flex justify-between text-sm">
                                                    <flux:text class="truncate">{{ $item->product->name }}</flux:text>
                                                    <flux:text class="text-neutral-600 dark:text-neutral-400 ml-2 shrink-0">
                                                        {{ number_format($item->quantity, 2) }} × ₦{{ number_format($item->price, 2) }}
                                                    </flux:text>
                                                </div>
                                            @endforeach
                                            @if($sale->items->count() > 3)
                                                <flux:text class="text-xs text-neutral-500 dark:text-neutral-400">
                                                    +{{ $sale->items->count() - 3 }} more items
                                                </flux:text>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3">
                                        <flux:text class="font-medium text-green-600 dark:text-green-400">
                                            ₦{{ number_format($sale->total_amount, 2) }}
                                        </flux:text>
                                        @if($sale->total_paid > 0)
                                            <flux:text class="text-xs text-blue-600 dark:text-blue-400 block">
                                                Paid: ₦{{ number_format($sale->total_paid, 2) }}
                                            </flux:text>
                                        @endif
                                    </td>
                                    <td class="px-3 sm:px-6 py-3">
                                        @php
                                            $statusColors = [
                                                'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400',
                                                'paid' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
                                                'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
                                            ];
                                            $color = $statusColors[$sale->status] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400';
                                        @endphp
                                        <flux:badge :class="$color" class="capitalize">
                                            {{ $sale->status }}
                                        </flux:badge>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3">
                                        <div>
                                            <flux:text class="text-sm font-medium">{{ $sale->created_at->format('M j, Y') }}</flux:text>
                                            <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ $sale->created_at->format('g:i A') }}</flux:text>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Summary -->
                <div class="border-t border-neutral-200 p-6 dark:border-neutral-700">
                    <div class="grid gap-4 md:grid-cols-4">
                        <div class="text-center">
                            <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Total Sales</flux:text>
                            <flux:text class="text-2xl font-bold">{{ $sales->count() }}</flux:text>
                        </div>
                        <div class="text-center">
                            <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Total Revenue</flux:text>
                            <flux:text class="text-2xl font-bold text-green-600 dark:text-green-400">
                                ₦{{ number_format($sales->sum('total_amount'), 2) }}
                            </flux:text>
                        </div>
                        <div class="text-center">
                            <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Total Paid</flux:text>
                            <flux:text class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                                ₦{{ number_format($sales->sum('total_paid'), 2) }}
                            </flux:text>
                        </div>
                        <div class="text-center">
                            <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Average Sale</flux:text>
                            <flux:text class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                                ₦{{ number_format($sales->avg('total_amount') ?? 0, 2) }}
                            </flux:text>
                        </div>
                    </div>
                </div>
            @else
                <div class="py-12 text-center">
                    <flux:icon name="shopping-cart" class="mx-auto size-12 text-neutral-400" />
                    <flux:heading size="lg" class="mt-4">No sales found</flux:heading>
                    <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">
                        @if($search || $dateFilter !== 'all')
                            No sales match your current filters.
                        @else
                            You haven't made any sales yet.
                        @endif
                    </flux:text>
                    @if(!$search && $dateFilter === 'all')
                        <div class="mt-6">
                            <flux:button variant="primary" :href="route('salesperson.pos')" wire:navigate>
                                <flux:icon name="shopping-cart" />
                                Start Your First Sale
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