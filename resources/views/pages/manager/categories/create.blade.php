<?php

use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Create Category'])] class extends Component {
    public $name = '';
    public $description = '';
    public $default_unit_type = 'yard';

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string|max:1000',
            'default_unit_type' => 'required',
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        Category::create($validated);

        session()->flash('success', 'Category created successfully.');
        $this->redirect(route('manager.categories.index'), navigate: true);
    }
}; ?>

    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <flux:heading size="xl" level="1">Create New Category</flux:heading>
                <flux:subheading size="lg">Add a new product category</flux:subheading>
            </div>
            <flux:button variant="outline" :href="route('manager.categories.index')" wire:navigate class="max-md:w-full">
                <flux:icon name="arrow-left" />
                Back to Categories
            </flux:button>
        </div>

        <!-- Form -->
        <div class="max-w-2xl">
            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <form wire:submit="save" class="space-y-6">
                    <!-- Category Name -->
                    <flux:input
                        wire:model="name"
                        label="Category Name"
                        placeholder="Enter category name"
                        required
                        autofocus
                    />

                    <!-- Description -->
                    <flux:textarea
                        wire:model="description"
                        label="Description"
                        placeholder="Enter category description (optional)"
                        rows="4"
                    />

                    <!-- Default Unit Type -->
                    <flux:input
                        wire:model="default_unit_type"
                        label="Default Unit Type"
                        placeholder="Enter Unit Type e.g meter"
                        required
                    />

                    <!-- Form Actions -->
                    <div class="flex flex-col sm:flex-row items-center justify-end gap-4 pt-6">
                        <flux:button variant="outline" type="button" :href="route('manager.categories.index')" wire:navigate class="w-full sm:w-auto">
                            Cancel
                        </flux:button>
                        <flux:button variant="primary" type="submit" class="w-full sm:w-auto">
                            Create Category
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
