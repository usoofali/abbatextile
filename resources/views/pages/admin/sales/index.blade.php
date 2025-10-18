<?php

use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('components.layouts.app', ['title' => 'Sales Report'])] class extends Component {
    public $sales;
    public $search = '';
    public $dateFilter = 'week'; // today, week, month, year, custom, all
    public $startDate;
    public $endDate;
    public $salespersonFilter = '';
    public $shopFilter = '';
    public $salespersons = [];
    public $shops = [];
    public $saleToCancel = null;
    public $exportFormat = '';

    public function mount(): void
    {
        // Load all shops for admin
        $this->shops = Shop::all();

        // Load all salespersons
        $this->salespersons = User::where('role', User::ROLE_SALESPERSON)
            ->where('is_active', true)
            ->get();

        // Set default date range to current week
        $this->startDate = now()->startOfWeek()->format('Y-m-d');
        $this->endDate = now()->endOfWeek()->format('Y-m-d');

        $this->loadSales();
    }

    public function loadSales(): void
    {
        $query = Sale::with(['salesperson', 'items.product', 'payments', 'shop'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('items.product', function ($productQuery) {
                        $productQuery->where('name', 'like', '%' . $this->search . '%');
                    })
                    ->orWhere('id', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->salespersonFilter, function ($query) {
                $query->where('salesperson_id', $this->salespersonFilter);
            })
            ->when($this->shopFilter, function ($query) {
                $query->where('shop_id', $this->shopFilter);
            })
            ->when($this->dateFilter !== 'all', function ($query) {
                $this->applyDateFilter($query);
            })
            ->latest();

        $this->sales = $query->get();
    }

    private function applyDateFilter($query): void
    {
        match ($this->dateFilter) {
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ]),
            'month' => $query->whereBetween('created_at', [
                now()->startOfMonth(),
                now()->endOfMonth()
            ]),
            'year' => $query->whereBetween('created_at', [
                now()->startOfYear(),
                now()->endOfYear()
            ]),
            'custom' => $query->whereBetween('created_at', [
                $this->startDate . ' 00:00:00',
                $this->endDate . ' 23:59:59'
            ]),
            default => null
        };
    }

    public function cancelSale($saleId): void
    {
        $sale = Sale::find($saleId);
        
        if (!$sale) {
            session()->flash('error', 'Sale not found.');
            return;
        }

        try {
            $sale->cancel();
            session()->flash('success', 'Sale cancelled successfully. Stock has been restored.');
            $this->loadSales();
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to cancel sale: ' . $e->getMessage());
        }

        $this->saleToCancel = null;
    }

    public function exportSales($format = 'csv'): StreamedResponse
    {
        $fileName = $this->generateFileName($format);
        
        return response()->streamDownload(function () use ($format) {
            $this->generateExportData($format);
        }, $fileName);
    }

    private function generateFileName($format): string
    {
        $dateRange = $this->getDateRangeForFileName();
        
        if ($this->shopFilter) {
            $shop = Shop::find($this->shopFilter);
            $shopName = $shop ? Str::slug($shop->name) : 'selected-shop';
            return "sales-report-{$shopName}-{$dateRange}.{$format}";
        }
        
        return "sales-report-all-shops-{$dateRange}.{$format}";
    }

    private function getDateRangeForFileName(): string
    {
        return match ($this->dateFilter) {
            'today' => 'today',
            'week' => 'this-week',
            'month' => 'this-month',
            'year' => 'this-year',
            'custom' => \Carbon\Carbon::parse($this->startDate)->format('Y-m-d') . '-to-' . \Carbon\Carbon::parse($this->endDate)->format('Y-m-d'),
            'all' => 'all-time',
            default => 'custom-range'
        };
    }

    private function generateExportData($format): void
    {
        $activeSales = $this->sales->where('status', '!=', 'cancelled');
        
        if ($format === 'csv') {
            $this->generateCSV($activeSales);
        } else {
            $this->generateExcel($activeSales);
        }
    }

    private function generateCSV($sales): void
    {
        $handle = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fwrite($handle, "\xEF\xBB\xBF");
        
        // Headers
        fputcsv($handle, [
            'Sale ID',
            'Shop',
            'Salesperson',
            'Items Count',
            'Products',
            'Total Amount',
            'Amount Paid',
            'Balance',
            'Status',
            'Date',
            'Time'
        ]);

        // Data rows
        foreach ($sales as $sale) {
            $products = $sale->items->map(function ($item) {
                return $item->product->name . ' (' . number_format($item->quantity, 2) . ' units)';
            })->implode('; ');

            fputcsv($handle, [
                '#' . substr($sale->id, -8),
                $sale->shop->name,
                $sale->salesperson->name,
                $sale->items->count(),
                $products,
                number_format($sale->total_amount, 2),
                number_format($sale->total_paid, 2),
                number_format($sale->balance, 2),
                ucfirst($sale->status),
                $sale->created_at->format('Y-m-d'),
                $sale->created_at->format('H:i:s')
            ]);
        }

        fclose($handle);
    }

    private function generateExcel($sales): void
    {
        $handle = fopen('php://output', 'w');
        
        // Add headers for Excel
        fwrite($handle, "Sales Report - All Shops\n");
        fwrite($handle, "Generated on: " . now()->format('Y-m-d H:i:s') . "\n");
        fwrite($handle, "Date Range: " . $this->getDateRangeDisplay() . "\n\n");
        
        // Headers
        fwrite($handle, "Sale ID\tShop\tSalesperson\tItems Count\tProducts\tTotal Amount\tAmount Paid\tBalance\tStatus\tDate\tTime\n");

        // Data rows
        foreach ($sales as $sale) {
            $products = $sale->items->map(function ($item) {
                return $item->product->name . ' (' . number_format($item->quantity, 2) . ' units)';
            })->implode('; ');

            fwrite($handle, 
                '#' . substr($sale->id, -8) . "\t" .
                $sale->shop->name . "\t" .
                $sale->salesperson->name . "\t" .
                $sale->items->count() . "\t" .
                $products . "\t" .
                number_format($sale->total_amount, 2) . "\t" .
                number_format($sale->total_paid, 2) . "\t" .
                number_format($sale->balance, 2) . "\t" .
                ucfirst($sale->status) . "\t" .
                $sale->created_at->format('Y-m-d') . "\t" .
                $sale->created_at->format('H:i:s') . "\n"
            );
        }

        // Summary
        fwrite($handle, "\n\nSummary:\n");
        fwrite($handle, "Total Active Sales: " . $sales->count() . "\n");
        fwrite($handle, "Total Revenue: ₦" . number_format($sales->sum('total_amount'), 2) . "\n");
        fwrite($handle, "Total Paid: ₦" . number_format($sales->sum('total_paid'), 2) . "\n");
        fwrite($handle, "Total Balance: ₦" . number_format($sales->sum('balance'), 2) . "\n");
        fwrite($handle, "Average Sale: ₦" . number_format($sales->avg('total_amount') ?? 0, 2) . "\n");

        fclose($handle);
    }

    private function getDateRangeDisplay(): string
    {
        return match ($this->dateFilter) {
            'today' => 'Today (' . now()->format('M j, Y') . ')',
            'week' => 'This Week (' . now()->startOfWeek()->format('M j') . ' - ' . now()->endOfWeek()->format('M j, Y') . ')',
            'month' => 'This Month (' . now()->format('F Y') . ')',
            'year' => 'This Year (' . now()->format('Y') . ')',
            'custom' => \Carbon\Carbon::parse($this->startDate)->format('M j, Y') . ' to ' . \Carbon\Carbon::parse($this->endDate)->format('M j, Y'),
            'all' => 'All Time',
            default => 'Custom Range'
        };
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->dateFilter = 'week';
        $this->salespersonFilter = '';
        $this->shopFilter = '';
        $this->startDate = now()->startOfWeek()->format('Y-m-d');
        $this->endDate = now()->endOfWeek()->format('Y-m-d');
        $this->loadSales();
    }

    public function updatedSearch(): void
    {
        $this->loadSales();
    }

    public function updatedDateFilter(): void
    {
        if ($this->dateFilter === 'custom') {
            $this->startDate = now()->startOfMonth()->format('Y-m-d');
            $this->endDate = now()->endOfMonth()->format('Y-m-d');
        }
        $this->loadSales();
    }

    public function updatedSalespersonFilter(): void
    {
        $this->loadSales();
    }

    public function updatedShopFilter(): void
    {
        $this->loadSales();
    }

    public function updatedStartDate(): void
    {
        if ($this->dateFilter === 'custom') {
            $this->loadSales();
        }
    }

    public function updatedEndDate(): void
    {
        if ($this->dateFilter === 'custom') {
            $this->loadSales();
        }
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">Sales Report - All Shops</flux:heading>
            <flux:subheading size="lg">Administrator View</flux:subheading>
        </div>
        
    </div>
    <div class="flex items-center justify-between gap-3">
    <!-- Left side content -->
    <div class="flex items-center gap-2">
        @if($sales->count() > 0)
            <flux:button 
                variant="outline" 
                icon="document-text"
                wire:click="exportSales('csv')"
                class="shrink-0"
            >
                Export CSV
            </flux:button>
        @endif
    </div>
    
    <!-- Right side button -->
    <flux:button variant="outline" :href="route('admin.dashboard')" wire:navigate>
        <flux:icon name="arrow-left" />
        Back to Dashboard
    </flux:button>
</div>
    <!-- Filters -->
    <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-800">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <!-- Search -->
            <div class="flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search by product name or sale ID..."
                    icon="magnifying-glass"
                />
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:gap-4">
                <!-- Shop Filter -->
                <div class="flex items-center gap-2">
                    <flux:label value="Shop:" class="shrink-0 text-sm font-medium" />
                    <flux:select wire:model.live="shopFilter" class="min-w-[150px]">
                        <option value="">All Shops</option>
                        @foreach($shops as $shop)
                            <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- Salesperson Filter -->
                <div class="flex items-center gap-2">
                    <flux:label value="Salesperson:" class="shrink-0 text-sm font-medium" />
                    <flux:select wire:model.live="salespersonFilter" class="min-w-[150px]">
                        <option value="">All Salespersons</option>
                        @foreach($salespersons as $salesperson)
                            <option value="{{ $salesperson->id }}">{{ $salesperson->name }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <!-- Date Filter -->
                <div class="flex items-center gap-2">
                    <flux:label value="Date:" class="shrink-0 text-sm font-medium" />
                    <flux:select wire:model.live="dateFilter" class="min-w-[120px]">
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="year">This Year</option>
                        <option value="custom">Custom Range</option>
                        <option value="all">All Time</option>
                    </flux:select>
                </div>

                <!-- Custom Date Range -->
                @if($dateFilter === 'custom')
                    <div class="flex items-center gap-2">
                        <flux:input
                            type="date"
                            wire:model.live="startDate"
                            class="min-w-[140px]"
                        />
                        <flux:text class="text-neutral-500">to</flux:text>
                        <flux:input
                            type="date"
                            wire:model.live="endDate"
                            class="min-w-[140px]"
                        />
                    </div>
                @endif

                <!-- Reset Filters -->
                <flux:button icon="arrow-path" variant="outline" wire:click="resetFilters" class="shrink-0">
                    Reset
                </flux:button>
            </div>
        </div>

        <!-- Active Filters Badge -->
        @if($search || $salespersonFilter || $shopFilter || $dateFilter !== 'week')
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Active filters:</flux:text>
                @if($search)
                    <flux:badge variant="outline" size="sm">
                        Search: "{{ $search }}"
                        <button wire:click="$set('search', '')" class="ml-1 hover:text-red-500">
                            <flux:icon name="x-mark" class="size-3" />
                        </button>
                    </flux:badge>
                @endif
                @if($shopFilter)
                    @php
                        $selectedShop = $shops->firstWhere('id', $shopFilter);
                    @endphp
                    <flux:badge variant="outline" size="sm">
                        Shop: {{ $selectedShop->name ?? 'Unknown' }}
                        <button wire:click="$set('shopFilter', '')" class="ml-1 hover:text-red-500">
                            <flux:icon name="x-mark" class="size-3" />
                        </button>
                    </flux:badge>
                @endif
                @if($salespersonFilter)
                    @php
                        $selectedSalesperson = $salespersons->firstWhere('id', $salespersonFilter);
                    @endphp
                    <flux:badge variant="outline" size="sm">
                        Salesperson: {{ $selectedSalesperson->name ?? 'Unknown' }}
                        <button wire:click="$set('salespersonFilter', '')" class="ml-1 hover:text-red-500">
                            <flux:icon name="x-mark" class="size-3" />
                        </button>
                    </flux:badge>
                @endif
                @if($dateFilter !== 'week')
                    <flux:badge variant="outline" size="sm">
                        @php
                            $filterLabels = [
                                'today' => 'Today',
                                'week' => 'This Week',
                                'month' => 'This Month',
                                'year' => 'This Year',
                                'custom' => 'Custom Range: ' . \Carbon\Carbon::parse($startDate)->format('M j, Y') . ' - ' . \Carbon\Carbon::parse($endDate)->format('M j, Y'),
                                'all' => 'All Time'
                            ];
                        @endphp
                        {{ $filterLabels[$dateFilter] }}
                        <button wire:click="$set('dateFilter', 'week')" class="ml-1 hover:text-red-500">
                            <flux:icon name="x-mark" class="size-3" />
                        </button>
                    </flux:badge>
                @endif
            </div>
        @endif
    </div>

    <!-- Sales Table -->
    <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-800">
        @if($sales->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px]">
                    <thead class="border-b border-neutral-200 dark:border-neutral-700">
                        <tr>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Sale ID</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Shop</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Salesperson</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Items</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Total Amount</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Status</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Date</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach($sales as $sale)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/50">
                                <td class="px-3 sm:px-6 py-3">
                                    <div>
                                        <flux:text class="font-mono text-sm font-medium">#{{ substr($sale->id, -8) }}</flux:text>
                                        <flux:text class="text-xs text-neutral-600 dark:text-neutral-400">
                                            {{ $sale->items->count() }} items
                                        </flux:text>
                                    </div>
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    <flux:text class="font-medium text-sm">{{ $sale->shop->name }}</flux:text>
                                    <flux:text class="text-xs text-neutral-600 dark:text-neutral-400">
                                        {{ $sale->shop->location }}
                                    </flux:text>
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
                                    <div class="space-y-1 max-w-xs">
                                        @foreach($sale->items->take(2) as $item)
                                            <div class="flex justify-between text-sm">
                                                <flux:text class="truncate">{{ $item->product->name }}</flux:text>
                                                <flux:text class="text-neutral-600 dark:text-neutral-400 ml-2 shrink-0">
                                                    {{ number_format($item->quantity, 2) }}
                                                </flux:text>
                                            </div>
                                        @endforeach
                                        @if($sale->items->count() > 2)
                                            <flux:text class="text-xs text-neutral-500 dark:text-neutral-400">
                                                +{{ $sale->items->count() - 2 }} more items
                                            </flux:text>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    <flux:text class="font-medium text-green-600 dark:text-green-400">
                                        ₦{{ number_format($sale->total_amount, 2) }}
                                    </flux:text>
                                    @if($sale->total_paid > 0)
                                        <flux:text class="text-xs text-blue-600 dark:text-blue-400 block">
                                            Paid: ₦{{ number_format($sale->total_paid, 2) }}
                                        </flux:text>
                                    @endif
                                    @if($sale->balance > 0)
                                        <flux:text class="text-xs text-amber-600 dark:text-amber-400 block">
                                            Balance: ₦{{ number_format($sale->balance, 2) }}
                                        </flux:text>
                                    @endif
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    @php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400',
                                            'paid' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
                                            'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
                                        ];
                                        $color = $statusColors[$sale->status] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400';
                                    @endphp
                                    <flux:badge :class="$color" class="capitalize">
                                        {{ $sale->status }}
                                    </flux:badge>
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    <div>
                                        <flux:text class="text-sm font-medium">{{ $sale->created_at->format('M j, Y') }}</flux:text>
                                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ $sale->created_at->format('g:i A') }}</flux:text>
                                    </div>
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    @if($sale->status !== 'cancelled')
                                        <flux:button 
                                            variant="outline" 
                                            icon="x-circle"
                                            size="sm" 
                                            wire:click="$set('saleToCancel', '{{ $sale->id }}')"
                                            class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                        >
                                            Cancel
                                        </flux:button>
                                    @else
                                        <flux:text class="text-sm text-neutral-500 dark:text-neutral-400">Cancelled</flux:text>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Summary -->
            <div class="border-t border-neutral-200 p-6 dark:border-neutral-700">
                @php
                    $activeSales = $sales->where('status', '!=', 'cancelled');
                    $totalRevenue = $activeSales->sum('total_amount');
                    $totalPaid = $activeSales->sum('total_paid');
                    $averageSale = $activeSales->avg('total_amount') ?? 0;
                @endphp
                <div class="grid gap-4 md:grid-cols-4">
                    <div class="text-center">
                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Active Sales</flux:text>
                        <flux:text class="text-2xl font-bold">{{ $activeSales->count() }}</flux:text>
                        @if($sales->count() !== $activeSales->count())
                            <flux:text class="text-xs text-neutral-500 dark:text-neutral-400">
                                ({{ $sales->where('status', 'cancelled')->count() }} cancelled)
                            </flux:text>
                        @endif
                    </div>
                    <div class="text-center">
                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Total Revenue</flux:text>
                        <flux:text class="text-2xl font-bold text-green-600 dark:text-green-400">
                            ₦{{ number_format($totalRevenue, 2) }}
                        </flux:text>
                    </div>
                    <div class="text-center">
                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Total Paid</flux:text>
                        <flux:text class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                            ₦{{ number_format($totalPaid, 2) }}
                        </flux:text>
                    </div>
                    <div class="text-center">
                        <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Average Sale</flux:text>
                        <flux:text class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                            ₦{{ number_format($averageSale, 2) }}
                        </flux:text>
                    </div>
                </div>
            </div>
        @else
            <div class="py-12 text-center">
                <flux:icon name="shopping-cart" class="mx-auto size-12 text-neutral-400" />
                <flux:heading size="lg" class="mt-4">No sales found</flux:heading>
                <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">
                    @if($search || $salespersonFilter || $shopFilter || $dateFilter !== 'week')
                        No sales match your current filters.
                    @else
                        No sales have been made yet.
                    @endif
                </flux:text>
            </div>
        @endif
    </div>

    <!-- Cancel Sale Confirmation Modal -->
    @if($saleToCancel)
        <flux:modal wire:model="saleToCancel" max-width="md">
            <div class="p-6">
                <div class="flex items-center gap-3">
                    <div class="rounded-full bg-red-100 p-2 dark:bg-red-900/20">
                        <flux:icon name="exclamation-triangle" class="size-6 text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <flux:heading size="lg">Cancel Sale</flux:heading>
                        <flux:text class="text-neutral-600 dark:text-neutral-400">
                            Are you sure you want to cancel this sale?
                        </flux:text>
                    </div>
                </div>

                <div class="mt-4 rounded-lg bg-neutral-50 p-4 dark:bg-neutral-800">
                    <flux:text class="font-medium">This action will:</flux:text>
                    <ul class="mt-2 space-y-1 text-sm text-neutral-600 dark:text-neutral-400">
                        <li>• Restore all product stock quantities</li>
                        <li>• Mark the sale as cancelled</li>
                        <li>• Remove the sale from revenue calculations</li>
                    </ul>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <flux:button variant="outline" wire:click="$set('saleToCancel', null)">
                        Keep Sale
                    </flux:button>
                    <flux:button 
                        variant="primary" 
                        icon="x-circle"
                        wire:click="cancelSale('{{ $saleToCancel }}')"
                        class="bg-red-600 hover:bg-red-700 focus:ring-red-500"
                    >
                        Yes, Cancel Sale
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>