<?php

use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Analytics'])] class extends Component {
    public $dateRange = '7'; // days
    public $totalShops;
    public $totalUsers;
    public $totalSales;
    public $totalRevenue;
    public $averageSaleValue;
    public $totalProducts;
    public $lowStockProducts;
    public $outOfStockProducts;
    public $activeSalespersons;
    public $recentSales;
    public $topShops;
    public $topProducts;
    public $salesByDay;
    public $shopPerformance;
    public $salesTrend;

    public function mount(): void
    {
        $this->loadAnalytics();
    }

    public function loadAnalytics(): void
    {
        $days = (int) $this->dateRange;
        $startDate = now()->subDays($days);

        $this->totalShops = Shop::count();
        $this->totalUsers = User::where('role', '!=', User::ROLE_ADMIN)->count();
        $this->totalSales = Sale::count();
        $this->totalRevenue = Sale::sum('total_amount');
        
        // New analytics
        $this->averageSaleValue = $this->totalSales > 0 ? $this->totalRevenue / $this->totalSales : 0;
        $this->totalProducts = Product::count();
        $this->lowStockProducts = Product::where('stock_quantity', '>', 0)
            ->where('stock_quantity', '<=', 10)
            ->count();
        $this->outOfStockProducts = Product::where('stock_quantity', '<=', 0)->count();
        $this->activeSalespersons = User::where('role', User::ROLE_SALESPERSON)
            ->where('is_active', true)
            ->count();

        // Recent sales with items relationship
        $this->recentSales = Sale::with(['shop', 'salesperson', 'items.product'])
            ->latest()
            ->limit(20)
            ->get();

        // Top shops with sales transactions relationship
        $this->topShops = Shop::withCount('salesTransactions')
            ->withSum('salesTransactions', 'total_amount')
            ->orderByDesc('sales_transactions_sum_total_amount')
            ->get();

        // Top selling products using sale items
        $this->topProducts = DB::table('sale_items')
            ->select(
                'products.name',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.subtotal) as total_revenue')
            )
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        $this->salesByDay = Sale::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(total_amount) as total_revenue')
            )
            ->where('created_at', '>=', $startDate)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        $this->shopPerformance = Shop::with(['manager'])
            ->withCount('salesTransactions')
            ->withCount('products')
            ->withSum('salesTransactions', 'total_amount')
            ->get();

        // Sales trend data for chart
        $this->salesTrend = Sale::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as daily_sales'),
                DB::raw('SUM(total_amount) as daily_revenue')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();
    }

    public function updatedDateRange(): void
    {
        $this->loadAnalytics();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <flux:heading size="xl" level="1">Analytics Dashboard</flux:heading>
            <flux:subheading size="lg">Comprehensive insights across all shops</flux:subheading>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-4">
            <flux:select wire:model.live="dateRange" class="w-full sm:w-32">
                <option value="7">Last 7 days</option>
                <option value="30">Last 30 days</option>
                <option value="90">Last 90 days</option>
            </flux:select>
            <flux:button variant="primary" :href="route('admin.dashboard')" wire:navigate class="max-md:w-full">
                <flux:icon name="arrow-left" />
                Back to Dashboard
            </flux:button>
        </div>
    </div>

    <!-- Key Metrics -->
    <div class="grid gap-4 sm:gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-5">
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
                    <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Sales</flux:text>
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
                    <flux:heading size="lg" class="font-bold text-base sm:text-lg">₦{{ number_format($totalRevenue, 2) }}</flux:heading>
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
    </div>

    <!-- Additional Metrics Row -->
    <div class="grid gap-4 sm:gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
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

        <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-orange-100 p-2 sm:p-3 dark:bg-orange-900/20">
                    <flux:icon name="user-group" class="size-5 sm:size-6 text-orange-600 dark:text-orange-400" />
                </div>
                <div class="min-w-0 flex-1">
                    <flux:text class="text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Active Sales Team</flux:text>
                    <flux:heading size="lg" class="font-bold text-base sm:text-lg">{{ $activeSalespersons }}</flux:heading>
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-6 grid-cols-1 lg:grid-cols-2">
        <!-- Shop Performance -->
        <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg" class="text-lg sm:text-xl">Shop Performance</flux:heading>
            </div>
            
            @if($shopPerformance->count() > 0)
                <div class="space-y-3 sm:space-y-4">
                    @foreach($shopPerformance as $shop)
                        <div class="rounded-lg border border-neutral-100 p-3 sm:p-4 dark:border-neutral-700">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0 flex-1">
                                    <flux:text class="font-medium text-sm sm:text-base truncate">{{ $shop->name }}</flux:text>
                                    <flux:text class="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400 truncate">{{ $shop->location }}</flux:text>
                                </div>
                                <div class="text-right shrink-0">
                                    <flux:text class="font-medium text-sm sm:text-base text-green-600 dark:text-green-400">
                                        ₦{{ number_format($shop->sales_transactions_sum_total_amount ?? 0, 2) }}
                                    </flux:text>
                                    <flux:text class="text-xs text-neutral-600 dark:text-neutral-400 block">
                                        {{ $shop->sales_transactions_count }} sales
                                    </flux:text>
                                </div>
                            </div>
                            <div class="mt-2 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between text-xs sm:text-sm">
                                <flux:text class="text-neutral-600 dark:text-neutral-400 truncate">
                                    Manager: {{ $shop->manager?->name ?? 'Unassigned' }}
                                </flux:text>
                                <flux:text class="text-neutral-600 dark:text-neutral-400">
                                    Products: {{ $shop->products_count }}
                                </flux:text>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="py-6 sm:py-8 text-center">
                    <flux:icon name="building-office" class="mx-auto size-8 sm:size-12 text-neutral-400" />
                    <flux:text class="mt-2 text-sm sm:text-base text-neutral-600 dark:text-neutral-400">No shops found</flux:text>
                </div>
            @endif
        </div>

        <!-- Top Products -->
        <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg" class="text-lg sm:text-xl">Top Selling Products</flux:heading>
            </div>
            
            @if($topProducts->count() > 0)
                <div class="space-y-3 sm:space-y-4">
                    @foreach($topProducts as $product)
                        <div class="flex items-center justify-between rounded-lg border border-neutral-100 p-3 dark:border-neutral-700">
                            <div class="min-w-0 flex-1 pr-3">
                                <flux:text class="font-medium text-sm sm:text-base truncate">{{ $product->name }}</flux:text>
                                <flux:text class="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400 truncate">
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

    <!-- Sales Trend -->
    <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
        <div class="mb-4">
            <flux:heading size="lg" class="text-lg sm:text-xl">Sales Trend (Last 30 Days)</flux:heading>
        </div>
        
        @if($salesTrend->count() > 0)
            <div class="space-y-3 sm:space-y-4">
                @foreach($salesTrend->take(10) as $day)
                    <div class="flex items-center justify-between rounded-lg border border-neutral-100 p-3 dark:border-neutral-700">
                        <div class="min-w-0 flex-1 pr-3">
                            <flux:text class="font-medium text-sm sm:text-base">
                                {{ \Carbon\Carbon::parse($day->date)->format('M j, Y') }}
                            </flux:text>
                            <flux:text class="text-xs sm:text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $day->daily_sales }} sales
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
                @if($salesTrend->count() > 10)
                    <div class="text-center">
                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">
                            +{{ $salesTrend->count() - 10 }} more days
                        </flux:text>
                    </div>
                @endif
            </div>
        @else
            <div class="py-6 sm:py-8 text-center">
                <flux:icon name="chart-bar" class="mx-auto size-8 sm:size-12 text-neutral-400" />
                <flux:text class="mt-2 text-sm sm:text-base text-neutral-600 dark:text-neutral-400">No sales data available</flux:text>
            </div>
        @endif
    </div>

    <!-- Recent Sales -->
    <div class="rounded-xl border border-neutral-200 bg-white p-4 sm:p-6 dark:border-neutral-700 dark:bg-neutral-800">
        <div class="mb-4 flex items-center justify-between">
            <flux:heading size="lg" class="text-lg sm:text-xl">Recent Sales Activity</flux:heading>
        </div>
        
        @if($recentSales->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full min-w-[800px]">
                    <thead class="border-b border-neutral-200 dark:border-neutral-700">
                        <tr>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Sale ID</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Shop</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Salesperson</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Items</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Amount</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Status</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-xs sm:text-sm font-medium text-neutral-600 dark:text-neutral-400">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach($recentSales as $sale)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/50">
                                <td class="px-3 sm:px-6 py-3">
                                    <flux:text class="font-medium text-sm sm:text-base">#{{ substr($sale->id, -8) }}</flux:text>
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    <flux:text class="text-xs sm:text-sm truncate">{{ $sale->shop->name }}</flux:text>
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    <flux:text class="text-xs sm:text-sm truncate">{{ $sale->salesperson->name }}</flux:text>
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    <flux:text class="text-xs sm:text-sm">{{ $sale->items->count() }} items</flux:text>
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    <flux:text class="font-medium text-sm sm:text-base">₦{{ number_format($sale->total_amount, 2) }}</flux:text>
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    <flux:badge :variant="match($sale->status) {
                                        'paid' => 'success',
                                        'pending' => 'warning',
                                        'cancelled' => 'error',
                                        default => 'neutral'
                                    }" class="capitalize">
                                        {{ $sale->status }}
                                    </flux:badge>
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    <flux:text class="text-xs text-neutral-600 dark:text-neutral-400">
                                        {{ $sale->created_at->format('M j, Y g:i A') }}
                                    </flux:text>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="py-6 sm:py-8 text-center">
                <flux:icon name="shopping-cart" class="mx-auto size-8 sm:size-12 text-neutral-400" />
                <flux:text class="mt-2 text-sm sm:text-base text-neutral-600 dark:text-neutral-400">No sales activity yet</flux:text>
            </div>
        @endif
    </div>
</div>