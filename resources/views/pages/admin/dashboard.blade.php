<?php

use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
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
    public $recentSales;
    public $topShops;

    public function mount(): void
    {
        $this->loadStats();
    }

    public function loadStats(): void
    {
        $this->totalShops = Shop::count();
        $this->totalUsers = User::where('role', '!=', User::ROLE_ADMIN)->count();
        $this->totalSales = Sale::count();
        $this->totalRevenue = Sale::sum('total_price');
        $this->totalProfit = Sale::sum('profit');

        $this->recentSales = Sale::with(['shop', 'salesperson', 'product'])
            ->latest()
            ->limit(10)
            ->get();

        $this->topShops = Shop::with('sales')
            ->withCount('sales')
            ->withSum('sales', 'total_price')
            ->withSum('sales', 'profit')
            ->orderByDesc('sales_sum_total_price')
            ->limit(5)
            ->get();
    }
}; ?>

    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="xl" level="1">Admin Dashboard</flux:heading>
                <flux:subheading size="lg">Overview of all textile shops</flux:subheading>
            </div>
            <div class="flex gap-3">
                <flux:button variant="primary" :href="route('admin.shops.create')" wire:navigate>
                    <flux:icon name="plus" />
                    Add Shop
                </flux:button>
                <flux:button variant="outline" :href="route('admin.users.create')" wire:navigate>
                    <flux:icon name="user-plus" />
                    Add User
                </flux:button>
            </div>
        </div>

    <!-- Stats Cards -->
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
            <!-- Recent Sales -->
            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading size="lg">Recent Sales</flux:heading>
                    <flux:link :href="route('admin.analytics')" wire:navigate>View All</flux:link>
                </div>
                
                @if($recentSales->count() > 0)
                    <div class="space-y-4">
                        @foreach($recentSales as $sale)
                            <div class="flex items-center justify-between rounded-lg border border-neutral-100 p-3 dark:border-neutral-700">
                                <div>
                                    <flux:text class="font-medium">{{ $sale->product->name }}</flux:text>
                                    <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ $sale->shop->name }} • {{ $sale->salesperson->name }}
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
                                <div>
                                    <flux:text class="font-medium">{{ $shop->name }}</flux:text>
                                    <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ $shop->location }}
                                    </flux:text>
                                </div>
                                <div class="text-right">
                                    <flux:text class="font-medium">${{ number_format($shop->sales_sum_total_price ?? 0, 2) }}</flux:text>
                                    <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ $shop->sales_count }} sales
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
        </div>
    </div>
