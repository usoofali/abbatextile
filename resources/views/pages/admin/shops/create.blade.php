<?php

use App\Models\Shop;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Create Shop'])] class extends Component {
    public $name = '';
    public $location = '';
    public $description = '';

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $shop = Shop::create([
            'id' => (string) Str::uuid(),
            'name' => $this->name,
            'location' => $this->location,
            'description' => $this->description,
            'manager_id' => null,
        ]);

        session()->flash('success', 'Shop created successfully.');
        $this->redirect(route('admin.shops.index'), navigate: true);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <flux:heading size="xl" level="1">Create New Shop</flux:heading>
            <flux:subheading size="lg">Add a new textile shop to the system</flux:subheading>
        </div>
        <flux:button variant="outline" :href="route('admin.shops.index')" wire:navigate class="max-md:w-full">
            <flux:icon name="arrow-left" />
            Back to Shops
        </flux:button>
    </div>

    <!-- Form -->
    <div class="max-w-2xl">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <form wire:submit="save" class="space-y-6">
                <!-- Shop Name -->
                <flux:input
                    wire:model="name"
                    label="Shop Name"
                    placeholder="Enter shop name"
                    required
                    autofocus
                />

                <!-- Location -->
                <flux:input
                    wire:model="location"
                    label="Location"
                    placeholder="Enter shop location/address"
                    required
                />

                <!-- Description -->
                <flux:textarea
                    wire:model="description"
                    label="Description"
                    placeholder="Enter shop description (optional)"
                    rows="3"
                />

                <!-- Form Actions -->
                <div class="flex items-center justify-end gap-4 pt-6">
                    <flux:button variant="outline" type="button" :href="route('admin.shops.index')" wire:navigate>
                        Cancel
                    </flux:button>
                    <flux:button variant="primary" type="submit">
                        Create Shop
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