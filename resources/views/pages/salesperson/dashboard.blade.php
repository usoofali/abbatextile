<?php

use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Sales Dashboard'])] class extends Component {
    public $shop;
    public $totalProducts;
    public $totalSales;
    public $totalRevenue;
    public $averageSaleValue;
    public $todaySales;
    public $todayRevenue;
    public $recentSales;
    public $topProducts;
    public $salesTrend;
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

        // Shop product count
        $this->totalProducts = $this->shop->products()->count();
        
        // Personal sales metrics (using salesTransactions relationship)
        $this->totalSales = $user->salesTransactions()->count();
        $this->totalRevenue = $user->salesTransactions()->sum('total_amount');
        
        // Calculate average sale value
        $this->averageSaleValue = $this->totalSales > 0 ? $this->totalRevenue / $this->totalSales : 0;
        
        // Today's sales
        $this->todaySales = $user->salesTransactions()
            ->whereDate('created_at', today())
            ->count();
            
        $this->todayRevenue = $user->salesTransactions()
            ->whereDate('created_at', today())
            ->sum('total_amount');

        // Recent personal sales with items
        $this->recentSales = $user->salesTransactions()
            ->with(['items.product'])
            ->latest()
            ->limit(10)
            ->get();

        // Top products sold by this salesperson using sale_items
        $this->topProducts = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.salesperson_id', $user->id)
            ->select(
                'products.name', 
                DB::raw('SUM(sale_items.quantity) as total_quantity'), 
                DB::raw('SUM(sale_items.subtotal) as total_revenue')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        // Personal sales trend (last 7 days)
        $this->salesTrend = $user->salesTransactions()
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(total_amount) as daily_revenue')
            )
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        // Low stock products in the shop
        $this->lowStockProducts = $this->shop->products()
            ->where('stock_quantity', '>', 0)
            ->where('stock_quantity', '<=', 10) // Changed to <= for consistency
            ->orderBy('stock_quantity')
            ->limit(5)
            ->get();
    }
}; ?>

<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
    <div class="text-center md:text-left">
        <flux:heading size="xl" level="1" class="text-lg sm:text-xl md:text-2xl">Sales Dashboard</flux:heading>
        <flux:text size="lg" class="text-neutral-600 dark:text-neutral-400 text-sm sm:text-base">
            {{ $shop?->name ?? 'No shop assigned' }}
        </flux:text>
    </div>
    
    <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
            <flux:button variant="primary" :href="route('salesperson.pos')" wire:navigate class="w-full sm:w-auto justify-center py-2 sm:py-2.5 text-sm">
                <flux:icon name="shopping-cart" class="size-4" />
                <span class="sm:inline">Point of Sale</span>
            </flux:button>
            <flux:button variant="outline" :href="route('salesperson.sales.index')" wire:navigate class="w-full sm:w-auto justify-center py-2 sm:py-2.5 text-sm">
                <flux:icon name="chart-bar" class="size-4" />
                <span class="sm:inline">My Sales</span>
            </flux:button>
            <flux:button variant="outline" :href="route('salesperson.payments.index')" wire:navigate class="w-full sm:w-auto justify-center py-2 sm:py-2.5 text-sm">
                <flux:icon name="credit-card" class="size-4" />
                <span class="sm:inline">Payments</span>
            </flux:button>
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
                        <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Available Products</flux:text>
                        <div class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ $totalProducts }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-purple-100 p-2 sm:p-3 dark:bg-purple-900/20">
                        <flux:icon name="shopping-cart" class="size-5 sm:size-6 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">My Total Sales</flux:text>
                        <div class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ number_format($totalSales) }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-green-100 p-2 sm:p-3 dark:bg-green-900/20">
                        <flux:icon name="currency-dollar" class="size-5 sm:size-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">My Total Revenue</flux:text>
                        <div class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">₦{{ number_format($totalRevenue, 2) }}</div>
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
                        <div class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">₦{{ number_format($averageSaleValue, 2) }}</div>
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
                        <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Today's Sales</flux:text>
                        <div class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ $todaySales }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-orange-100 p-2 sm:p-3 dark:bg-orange-900/20">
                        <flux:icon name="currency-dollar" class="size-5 sm:size-6 text-orange-600 dark:text-orange-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Today's Revenue</flux:text>
                        <div class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">₦{{ number_format($todayRevenue, 2) }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-amber-100 p-2 sm:p-3 dark:bg-amber-900/20">
                        <flux:icon name="exclamation-triangle" class="size-5 sm:size-6 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Low Stock Items</flux:text>
                        <div class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ $lowStockProducts->count() }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg bg-cyan-100 p-2 sm:p-3 dark:bg-cyan-900/20">
                        <flux:icon name="trophy" class="size-5 sm:size-6 text-cyan-600 dark:text-cyan-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Top Products</flux:text>
                        <div class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ $topProducts->count() }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 grid-cols-1 lg:grid-cols-2">
            <!-- Recent Sales -->
            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading size="xl">My Recent Sales</flux:heading>
                    <flux:link :href="route('salesperson.sales.index')" wire:navigate>View All</flux:link>
                </div>
                
                @if($recentSales->count() > 0)
                    <div class="space-y-3 sm:space-y-4">
                        @foreach($recentSales as $sale)
                            <div class="flex items-center justify-between rounded-lg border border-neutral-100 p-3 dark:border-neutral-700">
                                <div class="min-w-0 flex-1 pr-3">
                                    <flux:text class="font-medium text-sm sm:text-base truncate">Sale #{{ substr($sale->id, -8) }}</flux:text>
                                    <flux:text class="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ $sale->items->count() }} items • {{ $sale->status }}
                                    </flux:text>
                                </div>
                                <div class="text-right shrink-0">
                                    <flux:text class="font-medium text-sm sm:text-base">₦{{ number_format($sale->total_amount, 2) }}</flux:text>
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
                    <flux:heading size="xl">My Top Products</flux:heading>
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
            <!-- Sales Trend -->
            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading size="xl">My Sales Trend (Last 7 Days)</flux:heading>
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

            <!-- Low Stock Alert -->
            <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading size="xl">Low Stock Products</flux:heading>
                </div>
                
                @if($lowStockProducts->count() > 0)
                    <div class="space-y-3 sm:space-y-4">
                        @foreach($lowStockProducts as $product)
                            <div class="flex items-center justify-between rounded-lg border border-amber-100 bg-amber-50 p-3 dark:border-amber-700 dark:bg-amber-900/20">
                                <div class="min-w-0 flex-1 pr-3">
                                    <flux:text class="font-medium text-sm sm:text-base truncate">{{ $product->name }}</flux:text>
                                    <flux:text class="text-xs sm:text-sm text-amber-700 dark:text-amber-300">
                                        Only {{ $product->stock_quantity }} {{ $product->unit_type }} remaining
                                    </flux:text>
                                </div>
                                <flux:badge variant="amber">Low Stock</flux:badge>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-6 sm:py-8 text-center">
                        <flux:icon name="check-circle" class="mx-auto size-8 sm:size-12 text-green-400" />
                        <flux:text class="mt-2 text-sm sm:text-base text-neutral-600 dark:text-neutral-400">All products are well stocked</flux:text>
                    </div>
                @endif
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
</div>