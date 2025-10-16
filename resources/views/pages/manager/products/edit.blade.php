<?php

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app', ['title' => 'Edit Product'])] class extends Component {
    use WithFileUploads;
    public Product $product;
    public $shop;
    public $name = '';
    public $description = '';
    public $photo = null;
    public $barcode = '';
    public $price_per_unit = '';
    public $stock_quantity = '';
    public $category_id = '';
    public $categories = [];
    public $current_unit = 'yard';

    public function mount(Product $product): void
    {
        $user = Auth::user();
        $this->shop = $user->managedShop;
        $this->categories = Category::orderBy('name')->get();
        
        if (!$this->shop || $product->shop_id !== $this->shop->id) {
            abort(403, 'Unauthorized access to this product.');
        }

        $this->product = $product;
        $this->name = $product->name;
        $this->description = $product->description;
        $this->photo = $product->photo;
        $this->barcode = $product->barcode;
        $this->price_per_unit = $product->price_per_unit;
        $this->stock_quantity = $product->stock_quantity;
        $this->category_id = $product->category_id;
        $this->current_unit = $product->unit_type;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'photo' => 'nullable|image|max:2048',
            'barcode' => 'nullable|string|max:255|unique:products,barcode,' . $this->product->id,
            'price_per_unit' => 'required|numeric|min:0',
            'stock_quantity' => 'required|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
        ];
    }

    public function updatedCategoryId(): void
    {
        $cat = $this->category_id ? Category::find($this->category_id) : null;
        $this->current_unit = $cat?->default_unit_type ?? $this->current_unit;
    }

    public function save(): void
    {
        $validated = $this->validate();

        $photoPath = $this->product->photo;
        if ($this->photo instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            $photoPath = $this->photo->store('products', 'public');
        }

        $this->product->update([
            'category_id' => $this->category_id ?: null,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'photo' => $photoPath,
            'barcode' => $this->barcode ?: null,
            'price_per_unit' => $this->price_per_unit,
            'stock_quantity' => $this->stock_quantity,
        ]);

        session()->flash('success', 'Product updated successfully.');
        $this->redirect(route('manager.products.index'), navigate: true);
    }
}; ?>

    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <flux:heading size="xl" level="1">Edit Product</flux:heading>
                <flux:subheading size="lg">{{ $product->name }}</flux:subheading>
            </div>
            <flux:button variant="outline" :href="route('manager.products.index')" wire:navigate class="max-md:w-full">
                <flux:icon name="arrow-left" />
                Back to Products
            </flux:button>
        </div>

        <!-- Form -->
        <div class="max-w-2xl">
            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <form wire:submit="save" class="space-y-6">
                    <!-- Product Name -->
                    <flux:input
                        wire:model="name"
                        label="Product Name"
                        placeholder="Enter product name"
                        required
                        autofocus
                    />

                    <!-- Description -->
                    <flux:textarea
                        wire:model="description"
                        label="Description"
                        placeholder="Enter product description (optional)"
                        rows="3"
                    />

                    <!-- Photo Upload (camera or file)-->
                    <div>
                        <flux:field label="Product Photo">
                            <input type="file" wire:model="photo" accept="image/*" capture="environment" class="block w-full text-sm file:mr-4 file:rounded file:border-0 file:bg-neutral-100 file:px-3 file:py-2 file:text-neutral-800 dark:file:bg-neutral-700 dark:file:text-neutral-100" />
                            <div class="mt-2" wire:loading.delay wire:target="photo">
                                <flux:text>Uploading...</flux:text>
                            </div>
                            <div class="mt-3">
                                @if($photo instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
                                    <img src="{{ $photo->temporaryUrl() }}" alt="Preview" class="h-24 w-24 rounded object-cover" />
                                @elseif($product->photo)
                                    <img src="{{ Storage::disk('public')->url($product->photo) }}" alt="Current" class="h-24 w-24 rounded object-cover" />
                                @endif
                            </div>
                        </flux:field>
                    </div>

                    <!-- Barcode -->
                    <flux:input
                        wire:model="barcode"
                        label="Barcode"
                        placeholder="Leave empty for auto-generation"
                    />

                    <!-- Category select -->
                    <flux:select wire:model="category_id" label="Category">
                        <option value="">Select category</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </flux:select>

                    <!-- Pricing Section -->
                    <div class="space-y-4">
                        <flux:heading size="md">Pricing Information</flux:heading>
                        <div class="grid gap-4 md:grid-cols-2">
                            <flux:input
                                wire:model="price_per_unit"
                                label="Price per {{ $current_unit }}"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                required
                            />
                        </div>
                    </div>

                    <!-- Stock Information -->
                    <div class="space-y-4">
                        <flux:heading size="md">Stock Information</flux:heading>
                        
                        <div class="grid gap-4 md:grid-cols-2">
                            <!-- Stock Quantity -->
                            <flux:input
                                wire:model="stock_quantity"
                                label="Stock Quantity"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                required
                            />

                            <!-- Unit Type display from category -->
                            <flux:field label="Unit Type">
                                <div class="mt-2">
                                    <flux:badge variant="outline">{{ ucfirst($current_unit) }}</flux:badge>
                                </div>
                            </flux:field>
                        </div>
                    </div>

                    <!-- Product Stats -->
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-800">
                        <flux:heading size="sm" class="mb-3">Product Statistics</flux:heading>
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="text-center">
                                <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Total Sold</flux:text>
                                <flux:text class="text-lg font-semibold">{{ number_format($product->total_sold, 2) }} {{ $product->unit_type }}</flux:text>
                            </div>
                            <div class="text-center">
                                <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Total Revenue</flux:text>
                                <flux:text class="text-lg font-semibold text-green-600 dark:text-green-400">${{ number_format($product->total_revenue, 2) }}</flux:text>
                            </div>
                            <div class="text-center">
                                <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Total Profit</flux:text>
                                <flux:text class="text-lg font-semibold text-emerald-600 dark:text-emerald-400">${{ number_format($product->total_profit, 2) }}</flux:text>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex flex-col sm:flex-row items-center justify-end gap-4 pt-6">
                        <flux:button variant="outline" type="button" :href="route('manager.products.index')" wire:navigate class="w-full sm:w-auto">
                            Cancel
                        </flux:button>
                        <flux:button variant="primary" type="submit" class="w-full sm:w-auto">
                            Update Product
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>
