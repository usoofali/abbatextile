<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Manager Dashboard'])] class extends Component {
    public $shop;
    public $totalProducts;
    public $totalSales;
    public $totalRevenue;
    public $totalProfit;
    public $recentSales;
    public $topProducts;
    public $lowStockProducts;
    public $salespersons;

    public function mount(): void
    {
        $this->loadDashboardData();
    }

    public function loadDashboardData(): void
    {
        $user = Auth::user();
        $this->shop = $user->managedShop;

        if (!$this->shop) {
            session()->flash('error', 'No shop assigned to you.');
            return;
        }

        $this->totalProducts = $this->shop->products()->count();
        $this->totalSales = $this->shop->sales()->count();
        $this->totalRevenue = $this->shop->sales()->sum('total_price');
        $this->totalProfit = $this->shop->sales()->sum('profit');

        $this->recentSales = $this->shop->sales()
            ->with(['salesperson', 'product'])
            ->latest()
            ->limit(10)
            ->get();

        $this->topProducts = Sale::select('products.name', DB::raw('SUM(sales.quantity) as total_quantity'), DB::raw('SUM(sales.total_price) as total_revenue'), DB::raw('SUM(sales.profit) as total_profit'))
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->where('sales.shop_id', $this->shop->id)
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        $this->lowStockProducts = $this->shop->products()
            ->where('stock_quantity', '<', 10)
            ->orderBy('stock_quantity')
            ->get();

        $this->salespersons = $this->shop->salespersons()->withCount('sales')->withSum('sales', 'total_price')->get();
    }
}; ?>

    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">Manager Dashboard</flux:heading>
                <flux:subheading size="lg">{{ $shop?->name ?? 'No shop assigned' }}</flux:subheading>
            </div>
            <div class="flex gap-3">
                <flux:button variant="primary" :href="route('manager.products.create')" wire:navigate>
                    <flux:icon name="plus" />
                    Add Product
                </flux:button>
                <flux:button variant="outline" :href="route('manager.sales.index')" wire:navigate>
                    <flux:icon name="chart-bar" />
                    View Sales
                </flux:button>
            </div>
        </div>

        @if($shop)
            <!-- Stats Cards -->
            <div class="grid gap-6 grid-cols-1 md:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                    <div class="flex items-center gap-4">
                        <div class="rounded-lg bg-blue-100 p-3 dark:bg-blue-900/20">
                            <flux:icon name="cube" class="size-6 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div>
                            <flux:text class="text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Products</flux:text>
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
                            <flux:text class="text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Sales</flux:text>
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
                            <flux:text class="text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Revenue</flux:text>
                            <flux:heading size="lg" class="font-bold">${{ number_format($totalRevenue, 2) }}</flux:heading>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                    <div class="flex items-center gap-4">
                        <div class="rounded-lg bg-emerald-100 p-3 dark:bg-emerald-900/20">
                            <flux:icon name="arrow-trending-up" class="size-6 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div>
                            <flux:text class="text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Profit</flux:text>
                            <flux:heading size="lg" class="font-bold">${{ number_format($totalProfit, 2) }}</flux:heading>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 grid-cols-1 lg:grid-cols-2">
                <!-- Recent Sales -->
                <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                    <div class="mb-4 flex items-center justify-between">
                        <flux:heading size="lg">Recent Sales</flux:heading>
                        <flux:link :href="route('manager.sales.index')" wire:navigate>View All</flux:link>
                    </div>
                    
                    @if($recentSales->count() > 0)
                        <div class="space-y-4">
                            @foreach($recentSales as $sale)
                                <div class="flex items-center justify-between rounded-lg border border-neutral-100 p-3 dark:border-neutral-700">
                                    <div>
                                        <flux:text class="font-medium">{{ $sale->product->name }}</flux:text>
                                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">
                                            Sold by {{ $sale->salesperson->name }}
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

                <!-- Top Products -->
                <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                    <div class="mb-4">
                        <flux:heading size="lg">Top Selling Products</flux:heading>
                    </div>
                    
                    @if($topProducts->count() > 0)
                        <div class="space-y-4">
                            @foreach($topProducts as $product)
                                <div class="flex items-center justify-between rounded-lg border border-neutral-100 p-3 dark:border-neutral-700">
                                    <div>
                                        <flux:text class="font-medium">{{ $product->name }}</flux:text>
                                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">
                                            {{ $product->total_quantity }} units sold
                                        </flux:text>
                                    </div>
                                    <div class="text-right">
                                        <flux:text class="font-medium">${{ number_format($product->total_revenue, 2) }}</flux:text>
                                        <flux:text class="text-sm text-green-600 dark:text-green-400">
                                            ${{ number_format($product->total_profit, 2) }} profit
                                        </flux:text>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="py-8 text-center">
                            <flux:icon name="cube" class="mx-auto size-12 text-neutral-400" />
                            <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">No sales data available</flux:text>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Low Stock Alert -->
            @if($lowStockProducts->count() > 0)
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 dark:border-amber-700 dark:bg-amber-900/20">
                    <div class="mb-4 flex items-center gap-2">
                        <flux:icon name="exclamation-triangle" class="size-5 text-amber-600 dark:text-amber-400" />
                        <flux:heading size="lg" class="text-amber-800 dark:text-amber-200">Low Stock Alert</flux:heading>
                    </div>
                    <div class="grid gap-3 grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
                        @foreach($lowStockProducts as $product)
                            <div class="rounded-lg border border-amber-200 bg-white p-3 dark:border-amber-700 dark:bg-neutral-800">
                                <flux:text class="font-medium">{{ $product->name }}</flux:text>
                                <flux:text class="text-sm text-amber-700 dark:text-amber-300">
                                    Only {{ $product->stock_quantity }} {{ $product->unit_type }} remaining
                                </flux:text>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Salespersons Performance -->
            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="mb-4">
                    <flux:heading size="lg">Salespersons Performance</flux:heading>
                </div>
                
                @if($salespersons->count() > 0)
                    <div class="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
                        @foreach($salespersons as $salesperson)
                            <div class="rounded-lg border border-neutral-100 p-4 dark:border-neutral-700">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-neutral-200 text-sm font-medium dark:bg-neutral-700">
                                        {{ $salesperson->initials() }}
                                    </div>
                                    <div>
                                        <flux:text class="font-medium">{{ $salesperson->name }}</flux:text>
                                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ $salesperson->email }}</flux:text>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ $salesperson->sales_count }} sales</flux:text>
                                    <flux:text class="text-sm font-medium">${{ number_format($salesperson->sales_sum_total_price ?? 0, 2) }}</flux:text>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-8 text-center">
                        <flux:icon name="users" class="mx-auto size-12 text-neutral-400" />
                        <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">No salespersons assigned</flux:text>
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
