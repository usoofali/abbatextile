<?php

use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Manage Categories'])] class extends Component {
    public $categories;
    public $search = '';
    public $shop;

    public function mount(): void
    {
        $this->loadCategories();
        $user = Auth::user();
        $this->shop = $user->managedShop;
    }

    public function loadCategories(): void
    {
        $this->categories = Category::when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%');
            })
            ->orderBy('name')
            ->get();
    }

    public function updatedSearch(): void
    {
        $this->loadCategories();
    }

    public function deleteCategory(Category $category): void
    {
        // Check if category has products
        if ($category->products()->count() > 0) {
            session()->flash('error', 'Cannot delete category with existing products. Please reassign or delete the products first.');
            return;
        }

        $category->delete();
        $this->loadCategories();
        session()->flash('success', 'Category deleted successfully.');
    }
}; ?>

    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <flux:heading size="xl" level="1">Manage Categories</flux:heading>
                <flux:subheading size="lg">Organize your product categories</flux:subheading>
            </div>
            @if($shop)
            <flux:button variant="primary" :href="route('manager.categories.create')" wire:navigate class="max-md:w-full">
                <flux:icon name="plus" />
                Add Category
            </flux:button>
            @endif
        </div>
    @if($shop)
        <!-- Search -->
        <div class="flex items-center gap-4 max-md:flex-col">
            <div class="flex-1 w-full">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search categories by name or description..."
                    icon="magnifying-glass"
                />
            </div>
        </div>

        <!-- Categories Grid -->
        <div class="grid gap-6 grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
            @if($categories->count() > 0)
                @foreach($categories as $category)
                    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="rounded-lg bg-blue-100 p-3 dark:bg-blue-900/20">
                                    <flux:icon name="tag" class="size-6 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <flux:heading size="md" class="font-semibold">{{ $category->name }} | {{ $category->default_unit_type }}</flux:heading>
                                    <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">
                                        {{ $category->products()->count() }} products
                                    </flux:text>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:button 
                                    variant="ghost" 
                                    size="sm" 
                                    :href="route('manager.categories.edit', $category)" 
                                    wire:navigate
                                >
                                    <flux:icon name="pencil" />
                                </flux:button>
                                <flux:button 
                                    variant="ghost" 
                                    size="sm" 
                                    wire:click="deleteCategory('{{ $category->id }}')"
                                    wire:confirm="Are you sure you want to delete this category? This action cannot be undone."
                                    class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                >
                                    <flux:icon name="trash" />
                                </flux:button>
                            </div>
                        </div>
                        
                        @if($category->description)
                            <flux:text class="text-sm text-neutral-600 dark:text-neutral-400 mb-4">
                                {{ $category->description }}
                            </flux:text>
                        @endif

                        <div class="flex items-center justify-between pt-4 border-t border-neutral-200 dark:border-neutral-700">
                            <flux:text class="text-xs text-neutral-500 dark:text-neutral-400">
                                Created {{ $category->created_at->diffForHumans() }}
                            </flux:text>
                            @if($category->products()->count() > 0)
                                <flux:button 
                                    variant="ghost" 
                                    size="sm" 
                                    :href="route('manager.products.index', ['category' => $category->id])" 
                                    wire:navigate
                                >
                                    View Products
                                </flux:button>
                            @endif
                        </div>
                    </div>
                @endforeach
            @else
                <div class="col-span-full py-12 text-center">
                    <flux:icon name="tag" class="mx-auto size-12 text-neutral-400" />
                    <flux:heading size="lg" class="mt-4">No categories found</flux:heading>
                    <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">
                        @if($search)
                            No categories match your search criteria.
                        @else
                            Get started by adding your first category.
                        @endif
                    </flux:text>
                    @if(!$search)
                        <div class="mt-6">
                            <flux:button variant="primary" :href="route('manager.categories.create')" wire:navigate>
                                <flux:icon name="plus" />
                                Add First Category
                            </flux:button>
                        </div>
                    @endif
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
