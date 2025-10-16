<?php

use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
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
    public $totalProfit;
    public $recentSales;
    public $topShops;
    public $topProducts;
    public $salesByDay;
    public $shopPerformance;

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
        $this->totalRevenue = Sale::sum('total_price');
        $this->totalProfit = Sale::sum('profit');

        $this->recentSales = Sale::with(['shop', 'salesperson', 'product'])
            ->latest()
            ->limit(20)
            ->get();

        $this->topShops = Shop::with('sales')
            ->withCount('sales')
            ->withSum('sales', 'total_price')
            ->withSum('sales', 'profit')
            ->orderByDesc('sales_sum_total_price')
            ->get();

        $this->topProducts = Sale::select('products.name', DB::raw('SUM(sales.quantity) as total_quantity'), DB::raw('SUM(sales.total_price) as total_revenue'), DB::raw('SUM(sales.profit) as total_profit'))
            ->join('products', 'sales.product_id', '=', 'products.id')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_revenue')
            ->limit(10)
            ->get();

        $this->salesByDay = Sale::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as sales_count'),
                DB::raw('SUM(total_price) as total_revenue'),
                DB::raw('SUM(profit) as total_profit')
            )
            ->where('created_at', '>=', $startDate)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        $this->shopPerformance = Shop::with(['manager', 'salespersons'])
            ->withCount('sales')
            ->withCount('products')
            ->withSum('sales', 'total_price')
            ->withSum('sales', 'profit')
            ->get();
    }

    public function updatedDateRange(): void
    {
        $this->loadAnalytics();
    }
}; ?>

    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">Analytics Dashboard</flux:heading>
                <flux:subheading size="lg">Comprehensive insights across all shops</flux:subheading>
            </div>
            <div class="flex items-center gap-4">
                <flux:select wire:model.live="dateRange" class="w-32">
                    <option value="7">Last 7 days</option>
                    <option value="30">Last 30 days</option>
                    <option value="90">Last 90 days</option>
                </flux:select>
                <flux:button variant="primary" :href="route('admin.dashboard')" wire:navigate>
                    <flux:icon name="arrow-left" />
                    Back to Dashboard
                </flux:button>
            </div>
        </div>

    <!-- Key Metrics -->
    <div class="grid gap-6 grid-cols-1 md:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center gap-4">
                    <div class="rounded-lg bg-blue-100 p-3 dark:bg-blue-900/20">
                        <flux:icon name="building-office" class="size-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <flux:text class="text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Shops</flux:text>
                        <flux:heading size="lg" class="font-bold">{{ $totalShops }}</flux:heading>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="flex items-center gap-4">
                    <div class="rounded-lg bg-green-100 p-3 dark:bg-green-900/20">
                        <flux:icon name="users" class="size-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <flux:text class="text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Users</flux:text>
                        <flux:heading size="lg" class="font-bold">{{ $totalUsers }}</flux:heading>
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
                    <div class="rounded-lg bg-yellow-100 p-3 dark:bg-yellow-900/20">
                        <flux:icon name="currency-dollar" class="size-6 text-yellow-600 dark:text-yellow-400" />
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
            <!-- Shop Performance -->
            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="mb-4">
                    <flux:heading size="lg">Shop Performance</flux:heading>
                </div>
                
                @if($shopPerformance->count() > 0)
                    <div class="space-y-4">
                        @foreach($shopPerformance as $shop)
                            <div class="rounded-lg border border-neutral-100 p-4 dark:border-neutral-700">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <flux:text class="font-medium">{{ $shop->name }}</flux:text>
                                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ $shop->location }}</flux:text>
                                    </div>
                                    <div class="text-right">
                                        <flux:text class="font-medium text-green-600 dark:text-green-400">
                                            ${{ number_format($shop->sales_sum_total_price ?? 0, 2) }}
                                        </flux:text>
                                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">
                                            {{ $shop->sales_count }} sales
                                        </flux:text>
                                    </div>
                                </div>
                                <div class="mt-2 flex items-center justify-between text-sm">
                                    <flux:text class="text-neutral-600 dark:text-neutral-400">
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
                    <div class="py-8 text-center">
                        <flux:icon name="building-office" class="mx-auto size-12 text-neutral-400" />
                        <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">No shops found</flux:text>
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

        <!-- Recent Sales -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="mb-4">
                <flux:heading size="lg">Recent Sales Activity</flux:heading>
            </div>
            
                @if($recentSales->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[800px]">
                            <thead class="border-b border-neutral-200 dark:border-neutral-700">
                                <tr>
                                    <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Product</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Shop</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Salesperson</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Quantity</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Amount</th>
                                    <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                                @foreach($recentSales as $sale)
                                    <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/50">
                                        <td class="px-3 sm:px-6 py-3">
                                            <flux:text class="font-medium">{{ $sale->product->name }}</flux:text>
                                        </td>
                                        <td class="px-3 sm:px-6 py-3">
                                            <flux:text class="text-sm">{{ $sale->shop->name }}</flux:text>
                                        </td>
                                        <td class="px-3 sm:px-6 py-3">
                                            <flux:text class="text-sm">{{ $sale->salesperson->name }}</flux:text>
                                        </td>
                                        <td class="px-3 sm:px-6 py-3">
                                            <flux:text class="text-sm">{{ $sale->quantity }} {{ $sale->unit_type }}</flux:text>
                                        </td>
                                        <td class="px-3 sm:px-6 py-3">
                                            <flux:text class="font-medium">${{ number_format($sale->total_price, 2) }}</flux:text>
                                        </td>
                                        <td class="px-3 sm:px-6 py-3">
                                            <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">
                                                {{ $sale->created_at->format('M j, Y g:i A') }}
                                            </flux:text>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
            @else
                <div class="py-8 text-center">
                    <flux:icon name="shopping-cart" class="mx-auto size-12 text-neutral-400" />
                    <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">No sales activity yet</flux:text>
                </div>
            @endif
        </div>
    </div>
