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
    public $new_photo = null; // Separate variable for new photo upload
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
        $this->photo = $product->photo; // Keep existing photo path
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
            'new_photo' => 'nullable|image|max:2048', // Validate only new_photo
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
        try {
            $validated = $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            session()->flash('error', 'Please fix the validation errors below.');
            throw $e;
        }

        $photoPath = $this->product->photo; // Keep existing photo by default
        
        // Only process if a new photo was uploaded
        if ($this->new_photo instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            // Delete old photo if exists
            if ($this->product->photo && Storage::disk('public')->exists($this->product->photo)) {
                Storage::disk('public')->delete($this->product->photo);
            }
            $photoPath = $this->new_photo->store('products', 'public');
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

    public function removePhoto(): void
    {
        if ($this->product->photo && Storage::disk('public')->exists($this->product->photo)) {
            Storage::disk('public')->delete($this->product->photo);
        }
        $this->product->update(['photo' => null]);
        $this->photo = null;
        $this->new_photo = null;
        session()->flash('success', 'Photo removed successfully.');
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
                @error('name')
                    <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                @enderror

                <!-- Description -->
                <flux:textarea
                    wire:model="description"
                    label="Description"
                    placeholder="Enter product description (optional)"
                    rows="3"
                />
                @error('description')
                    <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                @enderror

                <!-- Photo Upload (camera or file)-->
                <div>
                    <flux:field label="Product Photo">
                        <input 
                            type="file" 
                            id="new-photo-input"
                            wire:model="new_photo" 
                            accept="image/*" 
                            capture="environment" 
                            class="block w-full text-sm file:mr-4 file:rounded file:border-0 file:bg-neutral-100 file:px-3 file:py-2 file:text-neutral-800 dark:file:bg-neutral-700 dark:file:text-neutral-100" 
                            onchange="resizeImage(this, 50)"
                        />
                        <div class="mt-2" wire:loading.delay wire:target="new_photo">
                            <flux:text>Uploading...</flux:text>
                        </div>
                        @error('new_photo')
                            <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                        @enderror
                        <div class="mt-3">
                            @if($new_photo instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile)
                                <img src="{{ $new_photo->temporaryUrl() }}" alt="Preview" class="h-24 w-24 rounded object-cover" />
                                <flux:text class="text-xs text-neutral-600 dark:text-neutral-400 mt-1">New photo preview</flux:text>
                            @elseif($photo)
                                <div class="flex items-center gap-4">
                                    <img src="{{ Storage::disk('public')->url($photo) }}" alt="Current" class="h-24 w-24 rounded object-cover" />
                                    <flux:button 
                                        type="button" 
                                        variant="outline" 
                                        size="sm" 
                                        wire:click="removePhoto"
                                        wire:confirm="Are you sure you want to remove this photo?"
                                        class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                    >
                                        <flux:icon name="trash" class="size-4" />
                                        Remove Photo
                                    </flux:button>
                                </div>
                                <flux:text class="text-xs text-neutral-600 dark:text-neutral-400 mt-1">Current photo</flux:text>
                            @else
                                <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">No photo uploaded</flux:text>
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
                @error('barcode')
                    <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                @enderror

                <!-- Category select -->
                <flux:select wire:model.live="category_id" label="Category">
                    <option value="">Select category</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </flux:select>
                @error('category_id')
                    <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                @enderror

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
                        @error('price_per_unit')
                            <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                        @enderror
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
                        @error('stock_quantity')
                            <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                        @enderror

                        <!-- Unit Type display from category -->
                        <flux:field label="Unit Type">
                            <div class="mt-2">
                                <flux:badge variant="outline">{{ ucfirst($current_unit) }}</flux:badge>
                                <flux:text class="text-xs text-neutral-600 dark:text-neutral-400 mt-1">
                                    @if($category_id)
                                        Unit from selected category
                                    @else
                                        Current unit type
                                    @endif
                                </flux:text>
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
                            <flux:text class="text-lg font-semibold text-green-600 dark:text-green-400">₦{{ number_format($product->total_revenue, 2) }}</flux:text>
                        </div>
                        <div class="text-center">
                            <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Sales Count</flux:text>
                            <flux:text class="text-lg font-semibold text-blue-600 dark:text-blue-400">{{ $product->saleItems()->count() }}</flux:text>
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

@push('scripts')
<script>
function resizeImage(input, maxSizeKB) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const maxSizeBytes = maxSizeKB * 1024;
        
        // Check if file is already small enough
        if (file.size <= maxSizeBytes) {
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = new Image();
            img.onload = function() {
                // Calculate new dimensions while maintaining aspect ratio
                let { width, height } = img;
                const aspectRatio = width / height;
                
                // Start with a reasonable size and reduce until under target
                let quality = 0.8;
                let newWidth = Math.min(width, 800);
                let newHeight = newWidth / aspectRatio;
                
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                
                // Function to compress image
                const compressImage = () => {
                    canvas.width = newWidth;
                    canvas.height = newHeight;
                    
                    ctx.drawImage(img, 0, 0, newWidth, newHeight);
                    
                    const dataURL = canvas.toDataURL('image/jpeg', quality);
                    const sizeKB = (dataURL.length * 0.75) / 1024; // Approximate size
                    
                    if (sizeKB > maxSizeKB && quality > 0.1) {
                        quality -= 0.1;
                        compressImage();
                    } else if (sizeKB > maxSizeKB && newWidth > 200) {
                        newWidth = Math.floor(newWidth * 0.8);
                        newHeight = newWidth / aspectRatio;
                        quality = 0.8;
                        compressImage();
                    } else {
                        // Convert dataURL back to file
                        const byteString = atob(dataURL.split(',')[1]);
                        const mimeString = dataURL.split(',')[0].split(':')[1].split(';')[0];
                        const ab = new ArrayBuffer(byteString.length);
                        const ia = new Uint8Array(ab);
                        for (let i = 0; i < byteString.length; i++) {
                            ia[i] = byteString.charCodeAt(i);
                        }
                        const blob = new Blob([ab], { type: mimeString });
                        const resizedFile = new File([blob], file.name, { type: mimeString });
                        
                        // Create a new FileList with the resized file
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(resizedFile);
                        input.files = dataTransfer.files;
                        
                        // Trigger Livewire update
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                        
                        console.log(`Image resized: ${(file.size / 1024).toFixed(1)}KB → ${(resizedFile.size / 1024).toFixed(1)}KB`);
                    }
                };
                
                compressImage();
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}
</script>
@endpush