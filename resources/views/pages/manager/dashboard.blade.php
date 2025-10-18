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
    public $averageSaleValue;
    public $lowStockProducts;
    public $outOfStockProducts;
    public $recentSales;
    public $topProducts;
    public $salespersons;
    public $salesTrend;
    public $todaySales;

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
        
        // Only count non-cancelled sales
        $this->totalSales = $this->shop->salesTransactions()
            ->where('status', '!=', 'cancelled')
            ->count();
            
        $this->totalRevenue = $this->shop->salesTransactions()
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');
        
        // Calculate average sale value from non-cancelled sales
        $this->averageSaleValue = $this->totalSales > 0 ? $this->totalRevenue / $this->totalSales : 0;
        
        // Stock alerts
        $this->lowStockProducts = $this->shop->products()
            ->where('stock_quantity', '>', 0)
            ->where('stock_quantity', '<=', 20)
            ->count();
            
        $this->outOfStockProducts = $this->shop->products()
            ->where('stock_quantity', '<=', 0)
            ->count();

        // Today's sales (non-cancelled only)
        $this->todaySales = $this->shop->salesTransactions()
            ->where('status', '!=', 'cancelled')
            ->whereDate('created_at', today())
            ->sum('total_amount');

        // Recent sales with items relationship (non-cancelled only)
        $this->recentSales = $this->shop->salesTransactions()
            ->where('status', '!=', 'cancelled')
            ->with(['salesperson', 'items.product'])
            ->latest()
            ->limit(10)
            ->get();

        // Top selling products using sale items from non-cancelled sales
        $this->topProducts = DB::table('sale_items')
            ->select(
                'products.name',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.subtotal) as total_revenue')
            )
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.shop_id', $this->shop->id)
            ->where('sales.status', '!=', 'cancelled')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        // Salespersons performance (non-cancelled sales only)
        $this->salespersons = $this->shop->salespersons()
            ->withCount([
                'salesTransactions' => function($query) {
                    $query->where('status', '!=', 'cancelled');
                }
            ])
            ->withSum([
                'salesTransactions' => function($query) {
                    $query->where('status', '!=', 'cancelled');
                }
            ], 'total_amount')
            ->get();

        // Sales trend (last 7 days, non-cancelled only)
        $this->salesTrend = Sale::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(total_amount) as daily_revenue')
            )
            ->where('shop_id', $this->shop->id)
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <flux:heading size="xl" level="1">Manager Dashboard</flux:heading>
            <flux:subheading size="lg">{{ $shop?->name ?? 'No shop assigned' }}</flux:subheading>
        </div>
    </div>

    @if($shop)
        <!-- Stats Cards -->
        <div class="grid gap-4 sm:gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-blue-100 p-2 sm:p-3 dark:bg-blue-900/20">
                        <flux:icon name="cube" class="size-5 sm:size-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Products</flux:text>
                        <flux:heading size="lg" class="font-bold text-base sm:text-lg">{{ $totalProducts }}</flux:heading>
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
                    <div class="rounded-lg bg-green-100 p-2 sm:p-3 dark:bg-green-900/20">
                        <flux:icon name="currency-dollar" class="size-5 sm:size-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Revenue</flux:text>
                        <flux:heading size="lg" class="font-bold text-base sm:text-lg">₦{{ number_format($totalRevenue, 2) }}</flux:text>
                    </div>
                </div>
            </div>

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
        </div>

        <!-- Additional Stats Cards -->
        <div class="grid gap-4 sm:gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-yellow-100 p-2 sm:p-3 dark:bg-yellow-900/20">
                        <flux:icon name="clock" class="size-5 sm:size-6 text-yellow-600 dark:text-yellow-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Today's Revenue</flux:text>
                        <flux:heading size="lg" class="font-bold text-base sm:text-lg">₦{{ number_format($todaySales, 2) }}</flux:heading>
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
                        <flux:heading size="lg" class="font-bold text-base sm:text-lg">{{ $lowStockProducts }}</flux:heading>
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
                        <flux:heading size="lg" class="font-bold text-base sm:text-lg">{{ $outOfStockProducts }}</flux:heading>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-cyan-100 p-2 sm:p-3 dark:bg-cyan-900/20">
                        <flux:icon name="users" class="size-5 sm:size-6 text-cyan-600 dark:text-cyan-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Sales Team</flux:text>
                        <flux:heading size="lg" class="font-bold text-base sm:text-lg">{{ $salespersons->count() }}</flux:heading>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 grid-cols-1 lg:grid-cols-2">
            <!-- Recent Sales -->
            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading size="lg" class="text-lg sm:text-xl">Recent Sales</flux:heading>
                    <flux:link :href="route('manager.sales.index')" wire:navigate>View All</flux:link>
                </div>
                
                @if($recentSales->count() > 0)
                    <div class="space-y-3 sm:space-y-4">
                        @foreach($recentSales as $sale)
                            <div class="flex items-center justify-between rounded-lg border border-neutral-100 p-3 dark:border-neutral-700">
                                <div class="min-w-0 flex-1 pr-3">
                                    <flux:text class="font-medium text-sm sm:text-base truncate">
                                        Sale #{{ substr($sale->id, -8) }}
                                    </flux:text>
                                    <flux:text class="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400 truncate">
                                        Sold by {{ $sale->salesperson->name }}
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
                    <div class="py-6 sm:py-8 text-center">
                        <flux:icon name="shopping-cart" class="mx-auto size-8 sm:size-12 text-neutral-400" />
                        <flux:text class="mt-2 text-sm sm:text-base text-neutral-600 dark:text-neutral-400">No sales yet</flux:text>
                    </div>
                @endif
            </div>

            <!-- Top Products -->
            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading size="lg" class="text-lg sm:text-xl">Top Selling Products</flux:heading>
                    <flux:link :href="route('manager.products.index')" wire:navigate>View All</flux:link>
                </div>
                
                @if($topProducts->count() > 0)
                    <div class="space-y-3 sm:space-y-4">
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
                    <div class="py-6 sm:py-8 text-center">
                        <flux:icon name="cube" class="mx-auto size-8 sm:size-12 text-neutral-400" />
                        <flux:text class="mt-2 text-sm sm:text-base text-neutral-600 dark:text-neutral-400">No sales data available</flux:text>
                    </div>
                @endif
            </div>
        </div>

        <!-- Second Row - Additional Analytics -->
        <div class="grid gap-6 grid-cols-1 lg:grid-cols-2">
            <!-- Salespersons Performance -->
            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading size="lg" class="text-lg sm:text-xl">Sales Team Performance</flux:heading>
                </div>
                
                @if($salespersons->count() > 0)
                    <div class="space-y-3 sm:space-y-4">
                        @foreach($salespersons as $salesperson)
                            <div class="flex items-center justify-between rounded-lg border border-neutral-100 p-3 dark:border-neutral-700">
                                <div class="flex items-center gap-3 min-w-0 flex-1 pr-3">
                                    <div class="flex h-8 w-8 sm:h-10 sm:w-10 items-center justify-center rounded-full bg-neutral-200 text-xs sm:text-sm font-medium dark:bg-neutral-700">
                                        {{ $salesperson->initials() }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <flux:text class="font-medium text-sm sm:text-base truncate">{{ $salesperson->name }}</flux:text>
                                        <flux:text class="text-xs text-neutral-600 dark:text-neutral-400 truncate">
                                            {{ $salesperson->sales_transactions_count }} sales
                                        </flux:text>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <flux:text class="font-medium text-sm sm:text-base">₦{{ number_format($salesperson->sales_transactions_sum_total_amount ?? 0, 2) }}</flux:text>
                                    <flux:text class="text-xs text-neutral-600 dark:text-neutral-400 block">
                                        Revenue
                                    </flux:text>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-6 sm:py-8 text-center">
                        <flux:icon name="users" class="mx-auto size-8 sm:size-12 text-neutral-400" />
                        <flux:text class="mt-2 text-sm sm:text-base text-neutral-600 dark:text-neutral-400">No salespersons assigned</flux:text>
                    </div>
                @endif
            </div>

            <!-- Sales Trend -->
            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading size="lg" class="text-lg sm:text-xl">Sales Trend (Last 7 Days)</flux:heading>
                </div>
                
                @if($salesTrend->count() > 0)
                    <div class="space-y-3 sm:space-y-4">
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
                    <div class="py-6 sm:py-8 text-center">
                        <flux:icon name="chart-bar" class="mx-auto size-8 sm:size-12 text-neutral-400" />
                        <flux:text class="mt-2 text-sm sm:text-base text-neutral-600 dark:text-neutral-400">No sales data for the past week</flux:text>
                    </div>
                @endif
            </div>
        </div>

        <!-- Stock Alerts Section -->
        @if($lowStockProducts > 0 || $outOfStockProducts > 0)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 sm:p-6 dark:border-amber-700 dark:bg-amber-900/20">
                <div class="mb-4 flex items-center gap-2">
                    <flux:icon name="exclamation-triangle" class="size-5 text-amber-600 dark:text-amber-400" />
                    <flux:heading size="lg" class="text-amber-800 dark:text-amber-200">Stock Alerts</flux:heading>
                </div>
                <div class="grid gap-3 grid-cols-1 sm:grid-cols-2">
                    @if($lowStockProducts > 0)
                        <div class="rounded-lg border border-amber-200 bg-white p-3 dark:border-amber-700 dark:bg-neutral-800">
                            <div class="flex items-center gap-2">
                                <flux:icon name="exclamation-triangle" class="size-4 text-amber-600" />
                                <flux:text class="font-medium text-amber-700 dark:text-amber-300">Low Stock Products</flux:text>
                            </div>
                            <flux:text class="text-sm text-amber-700 dark:text-amber-300 mt-1">
                                {{ $lowStockProducts }} products need restocking
                            </flux:text>
                        </div>
                    @endif
                    @if($outOfStockProducts > 0)
                        <div class="rounded-lg border border-red-200 bg-white p-3 dark:border-red-700 dark:bg-neutral-800">
                            <div class="flex items-center gap-2">
                                <flux:icon name="x-circle" class="size-4 text-red-600" />
                                <flux:text class="font-medium text-red-700 dark:text-red-300">Out of Stock</flux:text>
                            </div>
                            <flux:text class="text-sm text-red-700 dark:text-red-300 mt-1">
                                {{ $outOfStockProducts }} products are out of stock
                            </flux:text>
                        </div>
                    @endif
                </div>
                <div class="mt-4">
                    <flux:button variant="outline" :href="route('manager.stock.index')" wire:navigate class="border-amber-300 text-amber-700 hover:bg-amber-100 dark:border-amber-600 dark:text-amber-300 dark:hover:bg-amber-900/30">
                        <flux:icon name="cube" class="size-4" />
                        Manage Stock
                    </flux:button>
                </div>
            </div>
        @endif
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