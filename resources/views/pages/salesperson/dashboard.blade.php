<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Sales Dashboard'])] class extends Component {
    public $shop;
    public $totalProducts;
    public $totalSales;
    public $totalRevenue;
    public $recentSales;
    public $lowStockProducts;

    public function mount(): void
    {
        $this->loadDashboardData();
    }

    public function loadDashboardData(): void
    {
        $user = Auth::user();
        $this->shop = $user->shop;

        if (!$this->shop) {
            session()->flash('error', 'No shop assigned to you.');
            return;
        }

        $this->totalProducts = $this->shop->products()->count();
        $this->totalSales = $user->sales()->count();
        $this->totalRevenue = $user->sales()->sum('total_price');

        $this->recentSales = $user->sales()
            ->with(['product'])
            ->latest()
            ->limit(10)
            ->get();

        $this->lowStockProducts = $this->shop->products()
            ->where('stock_quantity', '<', 10)
            ->orderBy('stock_quantity')
            ->limit(5)
            ->get();
    }
}; ?>

    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <flux:heading size="xl" level="1">Sales Dashboard</flux:heading>
                <flux:subheading size="lg">{{ $shop?->name ?? 'No shop assigned' }}</flux:subheading>
            </div>
            <div class="flex gap-3 max-md:w-full">
                <flux:button variant="primary" :href="route('salesperson.pos')" wire:navigate class="max-md:w-full">
                    <flux:icon name="shopping-cart" />
                    Point of Sale
                </flux:button>
                <flux:button variant="outline" :href="route('salesperson.sales.index')" wire:navigate class="max-md:w-full">
                    <flux:icon name="chart-bar" />
                    My Sales
                </flux:button>
            </div>
        </div>

        @if($shop)
            <!-- Stats Cards -->
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                    <div class="flex items-center gap-4">
                        <div class="rounded-lg bg-blue-100 p-3 dark:bg-blue-900/20">
                            <flux:icon name="cube" class="size-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <flux:text class="text-sm font-medium text-neutral-600 dark:text-neutral-400">Available Products</flux:text>
                            <flux:heading size="lg" class="font-bold">{{ $totalProducts }}</flux:heading>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                    <div class="flex items-center gap-4">
                        <div class="rounded-lg bg-purple-100 p-3 dark:bg-purple-900/20">
                            <flux:icon name="shopping-cart" class="size-6 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div>
                            <flux:text class="text-sm font-medium text-neutral-600 dark:text-neutral-400">My Sales</flux:text>
                            <flux:heading size="lg" class="font-bold">{{ number_format($totalSales) }}</flux:heading>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                    <div class="flex items-center gap-4">
                        <div class="rounded-lg bg-green-100 p-3 dark:bg-green-900/20">
                            <flux:icon name="currency-dollar" class="size-6 text-green-600 dark:text-green-400" />
                        </div>
                        <div>
                            <flux:text class="text-sm font-medium text-neutral-600 dark:text-neutral-400">My Revenue</flux:text>
                            <flux:heading size="lg" class="font-bold">${{ number_format($totalRevenue, 2) }}</flux:heading>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- Recent Sales -->
                <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                    <div class="mb-4 flex items-center justify-between">
                        <flux:heading size="lg">My Recent Sales</flux:heading>
                        <flux:link :href="route('salesperson.sales.index')" wire:navigate>View All</flux:link>
                    </div>
                    
                    @if($recentSales->count() > 0)
                        <div class="space-y-4">
                            @foreach($recentSales as $sale)
                                <div class="flex items-center justify-between rounded-lg border border-neutral-100 p-3 dark:border-neutral-700">
                                    <div>
                                        <flux:text class="font-medium">{{ $sale->product->name }}</flux:text>
                                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">
                                            {{ $sale->quantity }} {{ $sale->unit_type }}
                                        </flux:text>
                                    </div>
                                    <div class="text-right">
                                        <flux:text class="font-medium">${{ number_format($sale->total_price, 2) }}</flux:text>
                                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">
                                            {{ $sale->created_at->diffForHumans() }}
                                        </flux:text>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="py-8 text-center">
                            <flux:icon name="shopping-cart" class="mx-auto size-12 text-neutral-400" />
                            <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">No sales yet</flux:text>
                        </div>
                    @endif
                </div>

                <!-- Low Stock Alert -->
                <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                    <div class="mb-4">
                        <flux:heading size="lg">Low Stock Products</flux:heading>
                    </div>
                    
                    @if($lowStockProducts->count() > 0)
                        <div class="space-y-4">
                            @foreach($lowStockProducts as $product)
                                <div class="flex items-center justify-between rounded-lg border border-amber-100 bg-amber-50 p-3 dark:border-amber-700 dark:bg-amber-900/20">
                                    <div>
                                        <flux:text class="font-medium">{{ $product->name }}</flux:text>
                                        <flux:text class="text-sm text-amber-700 dark:text-amber-300">
                                            Only {{ $product->stock_quantity }} {{ $product->unit_type }} remaining
                                        </flux:text>
                                    </div>
                                    <flux:badge variant="amber">Low Stock</flux:badge>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="py-8 text-center">
                            <flux:icon name="check-circle" class="mx-auto size-12 text-green-400" />
                            <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">All products are well stocked</flux:text>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="mb-4">
                    <flux:heading size="lg">Quick Actions</flux:heading>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <flux:button variant="primary" size="lg" :href="route('salesperson.pos')" wire:navigate class="h-20">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="shopping-cart" class="size-8" />
                            <flux:text class="font-medium">Start Sale</flux:text>
                        </div>
                    </flux:button>
                    
                    <flux:button variant="outline" size="lg" :href="route('salesperson.sales.index')" wire:navigate class="h-20">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="chart-bar" class="size-8" />
                            <flux:text class="font-medium">View Sales</flux:text>
                        </div>
                    </flux:button>
                    
                    <flux:button variant="outline" size="lg" disabled class="h-20">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="document-text" class="size-8" />
                            <flux:text class="font-medium">Print Receipt</flux:text>
                        </div>
                    </flux:button>
                </div>
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
