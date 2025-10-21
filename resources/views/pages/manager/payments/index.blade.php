<?php

use App\Models\Payment;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app', ['title' => 'Payment Management'])] class extends Component {
    use WithPagination;

    public $shop;
    public $search = '';
    public $modeFilter = '';
    public $salespersonFilter = '';
    public $dateFilter = 'week'; // today, week, month, year, custom, all
    public $startDate = '';
    public $endDate = '';
    public $showPaymentModal = false;
    public $selectedSale = null;
    public $paymentAmount = 0;
    public $paymentMode = 'cash';
    public $paymentReference = '';
    public $salespersons = [];
    public $filteredPaymentsTotal = 0;
    public $filteredPaymentsCount = 0;

    public function mount(): void
    {
        $user = Auth::user();
        $this->shop = $user->managedShop;

        if (!$this->shop) {
            session()->flash('error', 'No shop assigned to you.');
            return;
        }

        // Load salespersons for this shop
        $this->salespersons = $this->shop->salespersons()->get();

        // Set default date range to current week
        $this->startDate = now()->startOfWeek()->format('Y-m-d');
        $this->endDate = now()->endOfWeek()->format('Y-m-d');
    }

    public function getPayments()
    {
        $payments = Payment::whereHas('sale', function ($query) {
                $query->where('shop_id', $this->shop->id);
            })
            ->with(['sale.salesperson', 'receivedBy'])
            ->when($this->search, function ($query) {
                $query->where('reference', 'like', '%' . $this->search . '%')
                      ->orWhereHas('receivedBy', function ($q) {
                          $q->where('name', 'like', '%' . $this->search . '%');
                      })
                      ->orWhereHas('sale.salesperson', function ($q) {
                          $q->where('name', 'like', '%' . $this->search . '%');
                      });
            })
            ->when($this->modeFilter, function ($query) {
                $query->where('mode', $this->modeFilter);
            })
            ->when($this->salespersonFilter, function ($query) {
                $query->whereHas('sale', function ($q) {
                    $q->where('salesperson_id', $this->salespersonFilter);
                });
            })
            ->when($this->dateFilter !== 'all', function ($query) {
                $this->applyDateFilter($query);
            })
            ->orderBy('created_at', 'desc');

        // Calculate totals for filtered results
        $this->filteredPaymentsTotal = (clone $payments)->sum('amount');
        $this->filteredPaymentsCount = (clone $payments)->count();

        return $payments->paginate(15);
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

    public function getPendingSales()
    {
        return $this->shop->salesTransactions()
            ->where('status', 'pending')
            ->with(['salesperson', 'payments'])
            ->get()
            ->filter(function ($sale) {
                return $sale->balance > 0;
            });
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->modeFilter = '';
        $this->salespersonFilter = '';
        $this->dateFilter = 'week';
        $this->startDate = now()->startOfWeek()->format('Y-m-d');
        $this->endDate = now()->endOfWeek()->format('Y-m-d');
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedModeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSalespersonFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFilter(): void
    {
        if ($this->dateFilter === 'custom') {
            $this->startDate = now()->startOfMonth()->format('Y-m-d');
            $this->endDate = now()->endOfMonth()->format('Y-m-d');
        }
        $this->resetPage();
    }

    public function updatedStartDate(): void
    {
        if ($this->dateFilter === 'custom') {
            $this->resetPage();
        }
    }

    public function updatedEndDate(): void
    {
        if ($this->dateFilter === 'custom') {
            $this->resetPage();
        }
    }

    public function addPayment($saleId): void
    {
        $sale = Sale::with('payments')->find($saleId);
        
        if (!$sale) {
            session()->flash('error', 'Sale not found.');
            return;
        }

        $this->selectedSale = $sale;
        $this->paymentAmount = $sale->balance;
        $this->paymentMode = 'cash';
        $this->paymentReference = '';
        $this->showPaymentModal = true;
    }

    public function processPayment(): void
    {
        if (!$this->selectedSale) {
            session()->flash('error', 'No sale selected.');
            return;
        }

        if ($this->paymentAmount <= 0) {
            session()->flash('error', 'Payment amount must be greater than 0.');
            return;
        }

        if ($this->paymentAmount > $this->selectedSale->balance) {
            session()->flash('error', 'Payment amount cannot exceed the remaining balance.');
            return;
        }

        Payment::create([
            'sale_id' => $this->selectedSale->id,
            'amount' => $this->paymentAmount,
            'mode' => $this->paymentMode,
            'reference' => $this->paymentReference,
            'received_by' => Auth::id(),
        ]);

        $this->closePaymentModal();
        session()->flash('success', 'Payment processed successfully!');
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
        $this->selectedSale = null;
        $this->paymentAmount = 0;
        $this->paymentMode = 'cash';
        $this->paymentReference = '';
    }

    public function getModeBadgeClass($mode): string
    {
        return match ($mode) {
            'cash' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
            'transfer' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400',
            'pos' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400',
            'credit' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400',
        };
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl" level="1">Payment Management</flux:heading>
            <flux:text size="lg" class="text-neutral-600 dark:text-neutral-400">{{ $shop?->name ?? 'No shop assigned' }}</flux:text>
        </div>
        <flux:button variant="outline" :href="route('manager.dashboard')" wire:navigate>
            <flux:icon name="arrow-left" />
            Back to Dashboard
        </flux:button>
    </div>

    @if($shop)
        <!-- Pending Payments -->
        @if($this->getPendingSales()->count() > 0)
            <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-6 dark:border-yellow-700 dark:bg-yellow-900/20">
                <div class="flex items-center gap-2 mb-4">
                    <flux:icon name="exclamation-triangle" class="size-5 text-yellow-600 dark:text-yellow-400" />
                    <flux:heading size="lg" class="text-yellow-800 dark:text-yellow-200">Pending Payments</flux:heading>
                </div>
                
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($this->getPendingSales() as $sale)
                        <div class="bg-white dark:bg-neutral-800 p-4 rounded-lg border border-yellow-200 dark:border-yellow-700">
                            <div class="flex justify-between items-start mb-2">
                                <flux:text class="font-medium">Sale #{{ substr($sale->id, -8) }}</flux:text>
                                <span class="text-sm text-yellow-600 dark:text-yellow-400">Pending</span>
                            </div>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-neutral-600 dark:text-neutral-400">Salesperson:</span>
                                    <span class="font-medium">{{ $sale->salesperson->name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-neutral-600 dark:text-neutral-400">Total:</span>
                                    <span class="font-medium">₦{{ number_format($sale->total_amount, 2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-neutral-600 dark:text-neutral-400">Paid:</span>
                                    <span class="font-medium">₦{{ number_format($sale->total_paid, 2) }}</span>
                                </div>
                                <div class="flex justify-between border-t pt-1">
                                    <span class="text-neutral-600 dark:text-neutral-400">Balance:</span>
                                    <span class="font-bold text-red-600">₦{{ number_format($sale->balance, 2) }}</span>
                                </div>
                            </div>
                            <flux:button 
                                variant="primary"
                                icon="credit-card" 
                                size="sm" 
                                wire:click="addPayment('{{ $sale->id }}')"
                                class="w-full mt-3"
                            >
                                Add Payment
                            </flux:button>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Filters -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <!-- Search -->
                <div class="flex-1">
                    <flux:input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search by reference, user, or salesperson..."
                        icon="magnifying-glass"
                    />
                </div>

                <!-- Filters -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:gap-4">
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

                    <!-- Payment Mode Filter -->
                    <div class="flex items-center gap-2">
                        <flux:label value="Mode:" class="shrink-0 text-sm font-medium" />
                        <flux:select wire:model.live="modeFilter" class="min-w-[120px]">
                            <option value="">All Modes</option>
                            <option value="cash">Cash</option>
                            <option value="transfer">Transfer</option>
                            <option value="pos">POS</option>
                            <option value="credit">Credit</option>
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
                    <flux:button variant="outline" wire:click="resetFilters" class="shrink-0">
                        <flux:icon name="arrow-path" class="size-4" />
                        Reset
                    </flux:button>
                </div>
            </div>

            <!-- Active Filters Badge -->
            @if($search || $modeFilter || $salespersonFilter || $dateFilter !== 'week')
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
                    @if($modeFilter)
                        <flux:badge variant="outline" size="sm">
                            Mode: {{ ucfirst($modeFilter) }}
                            <button wire:click="$set('modeFilter', '')" class="ml-1 hover:text-red-500">
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

        <!-- Payments Table -->
        <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-800">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[800px]">
                    <thead class="border-b border-neutral-200 dark:border-neutral-700">
                        <tr>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Payment ID</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Sale ID</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Salesperson</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Amount</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Mode</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Reference</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Received By</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @forelse($this->getPayments() as $payment)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/50">
                                <td class="px-3 sm:px-6 py-3">
                                    <flux:text class="font-mono text-sm">{{ substr($payment->id, 8) }}</flux:text>
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    <flux:text class="font-mono text-sm">{{ substr($payment->sale->id, 8) }}</flux:text>
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    <flux:text class="text-sm">{{ $payment->sale->salesperson->name }}</flux:text>
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    <flux:text class="text-sm font-medium">₦{{ number_format($payment->amount, 2) }}</flux:text>
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $this->getModeBadgeClass($payment->mode) }}">
                                        {{ ucfirst($payment->mode) }}
                                    </span>
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    <flux:text class="text-sm">{{ $payment->reference ?: '-' }}</flux:text>
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    <flux:text class="text-sm">{{ $payment->receivedBy->name }}</flux:text>
                                </td>
                                <td class="px-3 sm:px-6 py-3">
                                    <flux:text class="text-sm">{{ $payment->created_at->format('M d, Y H:i') }}</flux:text>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center">
                                    <flux:icon name="credit-card" class="mx-auto size-12 text-neutral-400" />
                                    <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">No payments found</flux:text>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Search-wise Totals -->
            @if($filteredPaymentsCount > 0)
                <div class="border-t border-neutral-200 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-800/50">
                    <div class="px-6 py-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                            <div class="text-center">
                                <flux:text class="font-medium text-neutral-600 dark:text-neutral-400">Filtered Payments</flux:text>
                                <flux:text class="text-lg font-bold text-blue-600 dark:text-blue-400">
                                    {{ number_format($filteredPaymentsCount) }}
                                </flux:text>
                            </div>
                            <div class="text-center">
                                <flux:text class="font-medium text-neutral-600 dark:text-neutral-400">Total Amount</flux:text>
                                <flux:text class="text-lg font-bold text-green-600 dark:text-green-400">
                                    ₦{{ number_format($filteredPaymentsTotal, 2) }}
                                </flux:text>
                            </div>
                            <div class="text-center">
                                <flux:text class="font-medium text-neutral-600 dark:text-neutral-400">Average Payment</flux:text>
                                <flux:text class="text-lg font-bold text-purple-600 dark:text-purple-400">
                                    ₦{{ number_format($filteredPaymentsCount > 0 ? $filteredPaymentsTotal / $filteredPaymentsCount : 0, 2) }}
                                </flux:text>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-neutral-200 dark:border-neutral-700">
                {{ $this->getPayments()->links() }}
            </div>
        </div>

        <!-- Payment Modal -->
        @if($showPaymentModal && $selectedSale)
            <flux:modal wire:model="showPaymentModal" class="md:-w-96">
                <flux:heading size="xl">Add Payment</flux:heading>
                
                <div class="space-y-4">
                    <div class="bg-neutral-50 dark:bg-neutral-800 p-4 rounded-lg">
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-neutral-600 dark:text-neutral-400">Sale ID:</span>
                                <span class="font-mono">{{ substr($selectedSale->id, 8) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-neutral-600 dark:text-neutral-400">Salesperson:</span>
                                <span class="font-medium">{{ $selectedSale->salesperson->name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-neutral-600 dark:text-neutral-400">Total Amount:</span>
                                <span class="font-medium">₦{{ number_format($selectedSale->total_amount, 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-neutral-600 dark:text-neutral-400">Already Paid:</span>
                                <span class="font-medium">₦{{ number_format($selectedSale->total_paid, 2) }}</span>
                            </div>
                            <div class="flex justify-between border-t pt-2">
                                <span class="text-neutral-600 dark:text-neutral-400">Remaining Balance:</span>
                                <span class="font-bold text-red-600">₦{{ number_format($selectedSale->balance, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <flux:field>
                        <flux:label>Payment Amount</flux:label>
                        <flux:input 
                            wire:model="paymentAmount" 
                            type="number" 
                            step="0.01" 
                            min="0"
                            max="{{ $selectedSale->balance }}"
                            placeholder="Enter payment amount"
                        />
                    </flux:field>

                    <!-- Payment Mode -->
                    <flux:field>
                        <flux:label>Payment Mode</flux:label>
                        <flux:select wire:model="paymentMode" class="w-full">
                            <option value="cash">💵 Cash</option>
                            <option value="transfer">🏦 Bank Transfer</option>
                            <option value="pos">💳 POS</option>
                            <option value="credit">📝 Credit</option>
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label>Reference (Optional)</flux:label>
                        <flux:input 
                            wire:model="paymentReference" 
                            placeholder="Transaction reference"
                        />
                    </flux:field>
                </div>

                <div class="flex gap-3 mt-6">
                    <flux:button 
                        variant="primary" 
                        wire:click="processPayment"
                        class="flex-1"
                    >
                        Process Payment
                    </flux:button>
                    <flux:button 
                        variant="ghost" 
                        wire:click="closePaymentModal"
                    >
                        Cancel
                    </flux:button>
                </div>
            </flux:modal>
        @endif
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