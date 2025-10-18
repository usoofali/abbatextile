<?php

use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Admin Dashboard'])] class extends Component {
    public $totalShops;
    public $totalUsers;
    public $totalSales;
    public $totalRevenue;
    public $averageSaleValue;
    public $totalProducts;
    public $lowStockProducts;
    public $outOfStockProducts;
    public $recentSales;
    public $topShops;
    public $topProducts;
    public $salesTrend;

    public function mount(): void
    {
        $this->loadStats();
    }

    public function loadStats(): void
    {
        $this->totalShops = Shop::count();
        $this->totalUsers = User::where('role', '!=', User::ROLE_ADMIN)->count();
        
        // Only count non-cancelled sales
        $this->totalSales = Sale::where('status', '!=', 'cancelled')->count();
        $this->totalRevenue = Sale::where('status', '!=', 'cancelled')->sum('total_amount');
        
        // Calculate average sale value from non-cancelled sales
        $this->averageSaleValue = $this->totalSales > 0 ? $this->totalRevenue / $this->totalSales : 0;
        
        // Product statistics
        $this->totalProducts = Product::count();
        $this->lowStockProducts = Product::where('stock_quantity', '>', 0)
            ->where('stock_quantity', '<=', 20) // Low stock threshold
            ->count();
        $this->outOfStockProducts = Product::where('stock_quantity', '<=', 0)->count();

        // Recent sales with items relationship - exclude cancelled
        $this->recentSales = Sale::with(['shop', 'salesperson', 'items.product'])
            ->where('status', '!=', 'cancelled')
            ->latest()
            ->limit(10)
            ->get();

        // Top shops with sales transactions relationship - exclude cancelled
        $this->topShops = Shop::withCount([
            'salesTransactions' => function($query) {
                $query->where('status', '!=', 'cancelled');
            }
        ])
        ->withSum([
            'salesTransactions' => function($query) {
                $query->where('status', '!=', 'cancelled');
            }
        ], 'total_amount')
        ->orderByDesc('sales_transactions_sum_total_amount')
        ->limit(5)
        ->get();

        // Top selling products using sale items - exclude cancelled sales
        $this->topProducts = DB::table('sale_items')
            ->select(
                'products.name',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.subtotal) as total_revenue')
            )
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.status', '!=', 'cancelled')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        // Sales trend (last 7 days) - exclude cancelled
        $this->salesTrend = Sale::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(total_amount) as daily_revenue')
            )
            ->where('created_at', '>=', now()->subDays(7))
            ->where('status', '!=', 'cancelled')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <flux:heading size="xl" level="1">Admin Dashboard</flux:heading>
            <flux:subheading size="lg">Overview of all textile shops</flux:subheading>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid gap-4 sm:gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-blue-100 p-2 sm:p-3 dark:bg-blue-900/20">
                    <flux:icon name="building-office" class="size-5 sm:size-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div class="min-w-0 flex-1">
                    <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Shops</flux:text>
                    <flux:heading size="lg" class="font-bold text-base sm:text-lg">{{ $totalShops }}</flux:heading>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-green-100 p-2 sm:p-3 dark:bg-green-900/20">
                    <flux:icon name="users" class="size-5 sm:size-6 text-green-600 dark:text-green-400" />
                </div>
                <div class="min-w-0 flex-1">
                    <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Users</flux:text>
                    <flux:heading size="lg" class="font-bold text-base sm:text-lg">{{ $totalUsers }}</flux:heading>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-purple-100 p-2 sm:p-3 dark:bg-purple-900/20">
                    <flux:icon name="shopping-cart" class="size-5 sm:size-6 text-purple-600 dark:text-purple-400" />
                </div>
                <div class="min-w-0 flex-1">
                    <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Active Sales</flux:text>
                    <flux:heading size="lg" class="font-bold text-base sm:text-lg">{{ number_format($totalSales) }}</flux:heading>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-yellow-100 p-2 sm:p-3 dark:bg-yellow-900/20">
                    <flux:icon name="currency-dollar" class="size-5 sm:size-6 text-yellow-600 dark:text-yellow-400" />
                </div>
                <div class="min-w-0 flex-1">
                    <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Revenue</flux:text>
                    <flux:heading size="lg" class="font-bold text-base sm:text-lg">₦{{ number_format($totalRevenue, 2) }}</flux:text>
                </div>
            </div>
        </div>

        <!-- New Analytics Cards -->
        <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-indigo-100 p-2 sm:p-3 dark:bg-indigo-900/20">
                    <flux:icon name="chart-bar" class="size-5 sm:size-6 text-indigo-600 dark:text-indigo-400" />
                </div>
                <div class="min-w-0 flex-1">
                    <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Avg. Sale Value</flux:text>
                    <flux:heading size="lg" class="font-bold text-base sm:text-lg">₦{{ number_format($averageSaleValue, 2) }}</flux:heading>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-cyan-100 p-2 sm:p-3 dark:bg-cyan-900/20">
                    <flux:icon name="cube" class="size-5 sm:size-6 text-cyan-600 dark:text-cyan-400" />
                </div>
                <div class="min-w-0 flex-1">
                    <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Products</flux:text>
                    <flux:heading size="lg" class="font-bold text-base sm:text-lg">{{ number_format($totalProducts) }}</flux:heading>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-amber-100 p-2 sm:p-3 dark:bg-amber-900/20">
                    <flux:icon name="exclamation-triangle" class="size-5 sm:size-6 text-amber-600 dark:text-amber-400" />
                </div>
                <div class="min-w-0 flex-1">
                    <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Low Stock</flux:text>
                    <flux:heading size="lg" class="font-bold text-base sm:text-lg">{{ number_format($lowStockProducts) }}</flux:heading>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-red-100 p-2 sm:p-3 dark:bg-red-900/20">
                    <flux:icon name="x-circle" class="size-5 sm:size-6 text-red-600 dark:text-red-400" />
                </div>
                <div class="min-w-0 flex-1">
                    <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Out of Stock</flux:text>
                    <flux:heading size="lg" class="font-bold text-base sm:text-lg">{{ number_format($outOfStockProducts) }}</flux:heading>
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-6 grid-cols-1 lg:grid-cols-2">
        <!-- Recent Sales -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">Recent Sales</flux:heading>
            </div>
            
            @if($recentSales->count() > 0)
                <div class="space-y-4">
                    @foreach($recentSales as $sale)
                        <div class="flex items-center justify-between rounded-lg border border-neutral-100 p-3 dark:border-neutral-700">
                            <div class="min-w-0 flex-1 pr-3">
                                <flux:text class="font-medium text-sm sm:text-base truncate">
                                    Sale #{{ substr($sale->id, -8) }}
                                </flux:text>
                                <flux:text class="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400 truncate">
                                    {{ $sale->shop->name }} • {{ $sale->salesperson->name }}
                                </flux:text>
                                <flux:text class="text-xs text-neutral-500 dark:text-neutral-400">
                                    {{ $sale->items->count() }} items
                                </flux:text>
                            </div>
                            <div class="text-right shrink-0">
                                <flux:text class="font-medium text-sm sm:text-base">₦{{ number_format($sale->total_amount, 2) }}</flux:text>
                                <flux:text class="text-xs text-neutral-600 dark:text-neutral-400 block capitalize">
                                    {{ $sale->status }}
                                </flux:text>
                                <flux:text class="text-xs text-neutral-600 dark:text-neutral-400 block">
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
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">Top Selling Products</flux:heading>
            </div>
            
            @if($topProducts->count() > 0)
                <div class="space-y-4">
                    @foreach($topProducts as $product)
                        <div class="flex items-center justify-between rounded-lg border border-neutral-100 p-3 dark:border-neutral-700">
                            <div class="min-w-0 flex-1 pr-3">
                                <flux:text class="font-medium text-sm sm:text-base truncate">{{ $product->name }}</flux:text>
                                <flux:text class="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400">
                                    {{ number_format($product->total_quantity) }} units sold
                                </flux:text>
                            </div>
                            <div class="text-right shrink-0">
                                <flux:text class="font-medium text-sm sm:text-base">₦{{ number_format($product->total_revenue, 2) }}</flux:text>
                                <flux:text class="text-xs text-neutral-600 dark:text-neutral-400 block">
                                    Revenue
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

    <!-- Second Row - Additional Analytics -->
    <div class="grid gap-6 grid-cols-1 lg:grid-cols-2">
        <!-- Top Shops -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">Top Performing Shops</flux:heading>
                <flux:link :href="route('admin.shops.index')" wire:navigate>View All</flux:link>
            </div>
            
            @if($topShops->count() > 0)
                <div class="space-y-4">
                    @foreach($topShops as $shop)
                        <div class="flex items-center justify-between rounded-lg border border-neutral-100 p-3 dark:border-neutral-700">
                            <div class="min-w-0 flex-1 pr-3">
                                <flux:text class="font-medium text-sm sm:text-base truncate">{{ $shop->name }}</flux:text>
                                <flux:text class="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400 truncate">
                                    {{ $shop->location }}
                                </flux:text>
                            </div>
                            <div class="text-right shrink-0">
                                <flux:text class="font-medium text-sm sm:text-base">₦{{ number_format($shop->sales_transactions_sum_total_amount ?? 0, 2) }}</flux:text>
                                <flux:text class="text-xs text-neutral-600 dark:text-neutral-400 block">
                                    {{ $shop->sales_transactions_count }} sales
                                </flux:text>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-8 text-center">
                    <flux:icon name="building-office" class="mx-auto size-12 text-neutral-400" />
                    <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">No shops yet</flux:text>
                </div>
            @endif
        </div>

        <!-- Sales Trend -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">Sales Trend (Last 7 Days)</flux:heading>
            </div>
            
            @if($salesTrend->count() > 0)
                <div class="space-y-4">
                    @foreach($salesTrend as $day)
                        <div class="flex items-center justify-between rounded-lg border border-neutral-100 p-3 dark:border-neutral-700">
                            <div class="min-w-0 flex-1 pr-3">
                                <flux:text class="font-medium text-sm sm:text-base">
                                    {{ \Carbon\Carbon::parse($day->date)->format('M j, Y') }}
                                </flux:text>
                                <flux:text class="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400">
                                    {{ $day->sales_count }} sales
                                </flux:text>
                            </div>
                            <div class="text-right shrink-0">
                                <flux:text class="font-medium text-sm sm:text-base">₦{{ number_format($day->daily_revenue, 2) }}</flux:text>
                                <flux:text class="text-xs text-neutral-600 dark:text-neutral-400 block">
                                    Daily Revenue
                                </flux:text>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-8 text-center">
                    <flux:icon name="chart-bar" class="mx-auto size-12 text-neutral-400" />
                    <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">No sales data for the past week</flux:text>
                </div>
            @endif
        </div>
    </div>
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