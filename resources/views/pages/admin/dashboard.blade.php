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
    public $totalProfit;
    public $totalProducts;
    public $lowStockProducts;
    public $outOfStockProducts;
    public $recentSales;
    public $topShops;
    public $topProducts;
    public $salesTrend;
    public $shopSummaries;

    public function mount(): void
    {
        $this->loadStats();
    }

    public function loadStats(): void
    {
        $this->totalShops = Shop::count();
        $this->totalUsers = User::where('role', '!=', User::ROLE_ADMIN)->count();
        
        // Sales statistics
        $activeSales = Sale::where('status', '!=', 'cancelled')->get();
        $this->totalSales = $activeSales->count();
        $this->totalRevenue = $activeSales->sum('total_amount');
        $this->totalProfit = $activeSales->sum(fn($sale) => $sale->total_profit);
        
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

        // Load detailed shop summaries
        $this->loadShopSummaries();
    }

    public function loadShopSummaries(): void
    {
        $this->shopSummaries = Shop::with(['manager', 'salespersons'])
            ->withCount([
                'products',
                'salesTransactions' => function($query) {
                    $query->where('status', '!=', 'cancelled');
                }
            ])
            ->withSum([
                'salesTransactions' => function($query) {
                    $query->where('status', '!=', 'cancelled');
                }
            ], 'total_amount')
            ->withCount([
                'products as low_stock_products' => function($query) {
                    $query->where('stock_quantity', '>', 0)
                          ->where('stock_quantity', '<=', 20);
                },
                'products as out_of_stock_products' => function($query) {
                    $query->where('stock_quantity', '<=', 0);
                }
            ])
            ->withSum('products', 'stock_quantity')
            ->get()
            ->map(function($shop) {
                // Get recent sales for this shop
                $recentSales = Sale::with(['salesperson', 'items.product'])
                    ->where('shop_id', $shop->id)
                    ->where('status', '!=', 'cancelled')
                    ->latest()
                    ->limit(5)
                    ->get();

                // Get top products for this shop
                $topProducts = DB::table('sale_items')
                    ->select(
                        'products.name',
                        'products.stock_quantity',
                        DB::raw('SUM(sale_items.quantity) as total_quantity'),
                        DB::raw('SUM(sale_items.subtotal) as total_revenue')
                    )
                    ->join('products', 'sale_items.product_id', '=', 'products.id')
                    ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                    ->where('products.shop_id', $shop->id)
                    ->where('sales.status', '!=', 'cancelled')
                    ->groupBy('products.id', 'products.name', 'products.stock_quantity')
                    ->orderByDesc('total_revenue')
                    ->limit(3)
                    ->get();

                // Get sales trend for this shop (last 7 days)
                $salesTrend = Sale::select(
                        DB::raw('DATE(created_at) as date'),
                        DB::raw('COUNT(*) as sales_count'),
                        DB::raw('SUM(total_amount) as daily_revenue')
                    )
                    ->where('shop_id', $shop->id)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->where('status', '!=', 'cancelled')
                    ->groupBy(DB::raw('DATE(created_at)'))
                    ->orderBy('date')
                    ->get();

                // Calculate additional metrics
                $totalInventoryValue = $shop->products()->sum(DB::raw('stock_quantity * price_per_unit'));
                $totalProfit = Sale::where('shop_id', $shop->id)
                    ->where('status', '!=', 'cancelled')
                    ->get()
                    ->sum(fn($sale) => $sale->total_profit);

                return [
                    'shop' => $shop,
                    'recent_sales' => $recentSales,
                    'top_products' => $topProducts,
                    'sales_trend' => $salesTrend,
                    'total_inventory_value' => $totalInventoryValue,
                    'total_profit' => $totalProfit,
                ];
            });
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

    <!-- Overall Stats Cards -->
    <div class="grid gap-4 sm:gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center gap-3">
                <div class="rounded-none bg-blue-100 p-2 sm:p-3 dark:bg-blue-900/20">
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
                <div class="rounded-none bg-green-100 p-2 sm:p-3 dark:bg-green-900/20">
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
                <div class="rounded-none bg-purple-100 p-2 sm:p-3 dark:bg-purple-900/20">
                    <flux:icon name="chart-bar" class="size-5 sm:size-6 text-purple-600 dark:text-purple-400" />
                </div>
                <div class="min-w-0 flex-1">
                    <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Profit</flux:text>
                    <flux:heading size="lg" class="font-bold text-base sm:text-lg">₦{{ number_format($totalProfit, 2) }}</flux:heading>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center gap-3">
                <div class="rounded-none bg-yellow-100 p-2 sm:p-3 dark:bg-yellow-900/20">
                    <flux:icon name="currency-dollar" class="size-5 sm:size-6 text-yellow-600 dark:text-yellow-400" />
                </div>
                <div class="min-w-0 flex-1">
                    <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Sales</flux:text>
                    <flux:heading size="lg" class="font-bold text-base sm:text-lg">₦{{ number_format($totalRevenue, 2) }}</flux:heading>
                </div>
            </div>
        </div>
    </div>

    <!-- Shop Summaries Section -->
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl">Shop Performance Overview</flux:heading>
                <flux:text class="text-neutral-600 dark:text-neutral-400">Individual shop analytics and performance metrics</flux:text>
            </div>
        </div>

        @foreach($shopSummaries as $summary)
            <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-800">
                <!-- Shop Header -->
                <div class="border-b border-neutral-200 p-6 dark:border-neutral-700">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-purple-600 text-white font-bold text-lg">
                                {{ substr($summary['shop']->name, 0, 2) }}
                            </div>
                            <div>
                                <flux:heading size="lg">{{ $summary['shop']->name }}</flux:heading>
                                <flux:text class="text-neutral-600 dark:text-neutral-400">{{ $summary['shop']->location }}</flux:text>
                                @if($summary['shop']->manager)
                                    <flux:text class="text-sm text-neutral-500 dark:text-neutral-400">
                                        Manager: {{ $summary['shop']->manager->name }}
                                    </flux:text>
                                @endif
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <flux:badge variant="outline" size="sm">
                                {{ $summary['shop']->salespersons_count }} Salespersons
                            </flux:badge>
                            <flux:badge variant="outline" size="sm">
                                {{ $summary['shop']->products_count }} Products
                            </flux:badge>
                        </div>
                    </div>
                </div>

                <!-- Shop Stats Grid -->
                <div class="p-6">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <!-- Sales Stats -->
                        <div class="rounded-none border border-neutral-100 p-4 dark:border-neutral-700">
                            <div class="flex items-center gap-3">
                                <div class="rounded-none bg-green-100 p-2 dark:bg-green-900/20">
                                    <flux:icon name="shopping-cart" class="size-5 text-green-600 dark:text-green-400" />
                                </div>
                                <div>
                                    <flux:text class="text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Sales</flux:text>
                                    <flux:text class="text-xl font-bold text-green-600 dark:text-green-400">
                                        {{ number_format($summary['shop']->sales_transactions_count) }}
                                    </flux:text>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-none border border-neutral-100 p-4 dark:border-neutral-700">
                            <div class="flex items-center gap-3">
                                <div class="rounded-none bg-blue-100 p-2 dark:bg-blue-900/20">
                                    <flux:icon name="currency-dollar" class="size-5 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <flux:text class="text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Sales</flux:text>
                                    <flux:text class="text-xl font-bold text-blue-600 dark:text-blue-400">
                                        ₦{{ number_format($summary['shop']->sales_transactions_sum_total_amount ?? 0, 2) }}
                                    </flux:text>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-none border border-neutral-100 p-4 dark:border-neutral-700">
                            <div class="flex items-center gap-3">
                                <div class="rounded-none bg-purple-100 p-2 dark:bg-purple-900/20">
                                    <flux:icon name="chart-bar" class="size-5 text-purple-600 dark:text-purple-400" />
                                </div>
                                <div>
                                    <flux:text class="text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Profit</flux:text>
                                    <flux:text class="text-xl font-bold text-purple-600 dark:text-purple-400">
                                        ₦{{ number_format($summary['total_profit'], 2) }}
                                    </flux:text>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-none border border-neutral-100 p-4 dark:border-neutral-700">
                            <div class="flex items-center gap-3">
                                <div class="rounded-none bg-amber-100 p-2 dark:bg-amber-900/20">
                                    <flux:icon name="cube" class="size-5 text-amber-600 dark:text-amber-400" />
                                </div>
                                <div>
                                    <flux:text class="text-sm font-medium text-neutral-600 dark:text-neutral-400">Inventory Value</flux:text>
                                    <flux:text class="text-xl font-bold text-amber-600 dark:text-amber-400">
                                        ₦{{ number_format($summary['total_inventory_value'], 2) }}
                                    </flux:text>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stock Status -->
                    <div class="mt-6 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-none border border-neutral-100 p-4 dark:border-neutral-700">
                            <div class="flex items-center gap-3">
                                <div class="rounded-none bg-cyan-100 p-2 dark:bg-cyan-900/20">
                                    <flux:icon name="cube" class="size-5 text-cyan-600 dark:text-cyan-400" />
                                </div>
                                <div>
                                    <flux:text class="text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Products</flux:text>
                                    <flux:text class="text-lg font-bold">{{ $summary['shop']->products_count }}</flux:text>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-none border border-neutral-100 p-4 dark:border-neutral-700">
                            <div class="flex items-center gap-3">
                                <div class="rounded-none bg-yellow-100 p-2 dark:bg-yellow-900/20">
                                    <flux:icon name="exclamation-triangle" class="size-5 text-yellow-600 dark:text-yellow-400" />
                                </div>
                                <div>
                                    <flux:text class="text-sm font-medium text-neutral-600 dark:text-neutral-400">Low Stock</flux:text>
                                    <flux:text class="text-lg font-bold text-yellow-600 dark:text-yellow-400">
                                        {{ $summary['shop']->low_stock_products }}
                                    </flux:text>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-none border border-neutral-100 p-4 dark:border-neutral-700">
                            <div class="flex items-center gap-3">
                                <div class="rounded-none bg-red-100 p-2 dark:bg-red-900/20">
                                    <flux:icon name="x-circle" class="size-5 text-red-600 dark:text-red-400" />
                                </div>
                                <div>
                                    <flux:text class="text-sm font-medium text-neutral-600 dark:text-neutral-400">Out of Stock</flux:text>
                                    <flux:text class="text-lg font-bold text-red-600 dark:text-red-400">
                                        {{ $summary['shop']->out_of_stock_products }}
                                    </flux:text>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Analytics -->
                <div class="border-t border-neutral-200 p-6 dark:border-neutral-700">
                    <div class="grid gap-6 lg:grid-cols-2">
                        <!-- Recent Sales -->
                        <div>
                            <flux:heading size="md" class="mb-4">Recent Sales</flux:heading>
                            @if($summary['recent_sales']->count() > 0)
                                <div class="space-y-3">
                                    @foreach($summary['recent_sales'] as $sale)
                                        <div class="flex items-center justify-between rounded-none border border-neutral-100 p-3 dark:border-neutral-700">
                                            <div class="min-w-0 flex-1 pr-3">
                                                <flux:text class="font-medium text-sm truncate">
                                                    Sale #{{ substr($sale->id, -8) }}
                                                </flux:text>
                                                <flux:text class="text-xs text-neutral-600 dark:text-neutral-400">
                                                    {{ $sale->salesperson->name }} • {{ $sale->items->count() }} items
                                                </flux:text>
                                            </div>
                                            <div class="text-right shrink-0">
                                                <flux:text class="font-medium text-sm">₦{{ number_format($sale->total_amount, 2) }}</flux:text>
                                                <flux:text class="text-xs text-neutral-600 dark:text-neutral-400">
                                                    {{ $sale->created_at->diffForHumans() }}
                                                </flux:text>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="py-4 text-center">
                                    <flux:icon name="shopping-cart" class="mx-auto size-8 text-neutral-400" />
                                    <flux:text class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">No recent sales</flux:text>
                                </div>
                            @endif
                        </div>

                        <!-- Top Products -->
                        <div>
                            <flux:heading size="md" class="mb-4">Top Products</flux:heading>
                            @if($summary['top_products']->count() > 0)
                                <div class="space-y-3">
                                    @foreach($summary['top_products'] as $product)
                                        <div class="flex items-center justify-between rounded-none border border-neutral-100 p-3 dark:border-neutral-700">
                                            <div class="min-w-0 flex-1 pr-3">
                                                <flux:text class="font-medium text-sm truncate">{{ $product->name }}</flux:text>
                                                <flux:text class="text-xs text-neutral-600 dark:text-neutral-400">
                                                    {{ number_format($product->total_quantity) }} sold • Stock: {{ number_format($product->stock_quantity) }}
                                                </flux:text>
                                            </div>
                                            <div class="text-right shrink-0">
                                                <flux:text class="font-medium text-sm">₦{{ number_format($product->total_revenue, 2) }}</flux:text>
                                                <flux:text class="text-xs text-neutral-600 dark:text-neutral-400">Total Sales</flux:text>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="py-4 text-center">
                                    <flux:icon name="cube" class="mx-auto size-8 text-neutral-400" />
                                    <flux:text class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">No sales data</flux:text>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
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
