<?php

use App\Models\Sale;
use App\Models\Shop;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Sales Report'])] class extends Component {
    public $sales;
    public $search = '';
    public $shop;
    public $dateRange = '7'; // days

    public function mount(): void
    {
        $user = Auth::user();
        $this->shop = $user->managedShop;

        if (!$this->shop) {
            session()->flash('error', 'No shop assigned to you.');
            return;
        }

        $this->loadSales();
    }

    public function loadSales(): void
    {
        if (!$this->shop) return;

        $days = (int) $this->dateRange;
        $startDate = now()->subDays($days);

        $this->sales = $this->shop->sales()
            ->with(['product', 'salesperson'])
            ->when($this->search, function ($query) {
                $query->whereHas('product', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                })->orWhereHas('salesperson', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->dateRange !== 'all', function ($query) use ($startDate) {
                $query->where('created_at', '>=', $startDate);
            })
            ->latest()
            ->get();
    }

    public function updatedSearch(): void
    {
        $this->loadSales();
    }

    public function updatedDateRange(): void
    {
        $this->loadSales();
    }
}; ?>

    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">Sales Report</flux:heading>
                <flux:subheading size="lg">{{ $shop?->name ?? 'No shop assigned' }}</flux:subheading>
            </div>
            <flux:button variant="outline" :href="route('manager.dashboard')" wire:navigate>
                <flux:icon name="arrow-left" />
                Back to Dashboard
            </flux:button>
        </div>

        @if($shop)
            <!-- Filters -->
            <div class="flex items-center gap-4">
                <div class="flex-1">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search by product or salesperson..."
                        icon="magnifying-glass"
                    />
                </div>
                <div class="w-48">
                    <flux:select wire:model.live="dateRange">
                        <option value="7">Last 7 days</option>
                        <option value="30">Last 30 days</option>
                        <option value="90">Last 90 days</option>
                        <option value="all">All time</option>
                    </flux:select>
                </div>
            </div>

            <!-- Sales Table -->
            <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-800">
                @if($sales->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[900px]">
                            <thead class="border-b border-neutral-200 dark:border-neutral-700">
                                <tr>
                                    <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Product</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Salesperson</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Quantity</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Unit Price</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Amount</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Profit</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                                @foreach($sales as $sale)
                                    <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/50">
                                        <td class="px-3 sm:px-6 py-3">
                                            <div>
                                                <flux:text class="font-medium">{{ $sale->product->name }}</flux:text>
                                                <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ $sale->product->description }}</flux:text>
                                            </div>
                                        </td>
                                        <td class="px-3 sm:px-6 py-3">
                                            <div class="flex items-center gap-3">
                                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-neutral-200 text-xs font-medium dark:bg-neutral-700">
                                                    {{ $sale->salesperson->initials() }}
                                                </div>
                                                <div>
                                                    <flux:text class="font-medium">{{ $sale->salesperson->name }}</flux:text>
                                                    <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ $sale->salesperson->email }}</flux:text>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 sm:px-6 py-3">
                                            <div class="flex items-center gap-2">
                                                <flux:text class="font-medium">{{ number_format($sale->quantity, 2) }}</flux:text>
                                                <flux:badge variant="outline" size="sm">{{ ucfirst($sale->unit_type) }}</flux:badge>
                                            </div>
                                        </td>
                                        <td class="px-3 sm:px-6 py-3">
                                            <flux:text class="font-medium">${{ number_format($sale->unit_price, 2) }}</flux:text>
                                        </td>
                                        <td class="px-3 sm:px-6 py-3">
                                            <flux:text class="font-medium text-green-600 dark:text-green-400">${{ number_format($sale->total_price, 2) }}</flux:text>
                                        </td>
                                        <td class="px-3 sm:px-6 py-3">
                                            <flux:text class="font-medium text-emerald-600 dark:text-emerald-400">${{ number_format($sale->profit, 2) }}</flux:text>
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
                                <flux:text class="text-2xl font-bold text-green-600 dark:text-green-400">${{ number_format($sales->sum('total_price'), 2) }}</flux:text>
                            </div>
                            <div class="text-center">
                                <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Total Profit</flux:text>
                                <flux:text class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">${{ number_format($sales->sum('profit'), 2) }}</flux:text>
                            </div>
                            <div class="text-center">
                                <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Avg. Sale Value</flux:text>
                                <flux:text class="text-2xl font-bold text-blue-600 dark:text-blue-400">${{ number_format($sales->avg('total_price') ?? 0, 2) }}</flux:text>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="py-12 text-center">
                        <flux:icon name="shopping-cart" class="mx-auto size-12 text-neutral-400" />
                        <flux:heading size="lg" class="mt-4">No sales found</flux:heading>
                        <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">
                            @if($search || $dateRange !== 'all')
                                No sales match your search criteria.
                            @else
                                No sales have been made yet.
                            @endif
                        </flux:text>
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
    </div>
