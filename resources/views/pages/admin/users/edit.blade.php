<?php

use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Edit User'])] class extends Component {
    public User $user;
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $role = '';
    public $shop_id = '';

    public function mount(User $user): void
    {
        if ($user->isAdmin()) {
            abort(403, 'Cannot edit admin users.');
        }

        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role;
        $this->shop_id = $user->shop_id;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($this->user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|in:manager,salesperson',
            'shop_id' => ['required', Rule::exists('shops', 'id')],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $updateData = [
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'shop_id' => $this->shop_id,
        ];

        // Only update password if provided
        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $this->user->update($updateData);

        // Handle manager assignment (one shop per manager)
        if ($this->role === User::ROLE_MANAGER) {
            // Remove this user from managing any other shop first
            Shop::where('manager_id', $this->user->id)
                ->where('id', '!=', $this->shop_id)
                ->update(['manager_id' => null]);

            // Assign as manager for the selected shop
            Shop::find($this->shop_id)->update(['manager_id' => $this->user->id]);
        } else {
            // Remove manager assignment if user is no longer a manager
            $shop = Shop::where('manager_id', $this->user->id)->first();
            if ($shop) {
                $shop->update(['manager_id' => null]);
            }
        }

        session()->flash('success', 'User updated successfully.');
        $this->redirect(route('admin.users.index'), navigate: true);
    }

    public function getAvailableShopsProperty()
    {
        return Shop::all();
    }
}; ?>

    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <flux:heading size="xl" level="1">Edit User</flux:heading>
                <flux:subheading size="lg">{{ $user->name }}</flux:subheading>
            </div>
            <flux:button variant="outline" :href="route('admin.users.index')" wire:navigate class="max-md:w-full">
                <flux:icon name="arrow-left" />
                Back to Users
            </flux:button>
        </div>

        <!-- Form -->
        <div class="max-w-2xl">
            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
                <form wire:submit="save" class="space-y-6">
                    <!-- Name -->
                    <flux:input
                        wire:model="name"
                        label="Full Name"
                        placeholder="Enter full name"
                        required
                        autofocus
                    />

                    <!-- Email -->
                    <flux:input
                        wire:model="email"
                        label="Email Address"
                        type="email"
                        placeholder="Enter email address"
                        required
                    />

                    <!-- Password -->
                    <flux:input
                        wire:model="password"
                        label="New Password"
                        type="password"
                        placeholder="Leave empty to keep current password"
                        viewable
                    />

                    <!-- Confirm Password -->
                    <flux:input
                        wire:model="password_confirmation"
                        label="Confirm New Password"
                        type="password"
                        placeholder="Confirm new password"
                        viewable
                    />

                    <!-- Role -->
                    <div>
                        <flux:select wire:model="role" label="Role" required>
                            <option value="">Select a role</option>
                            <option value="manager">Manager</option>
                            <option value="salesperson">Salesperson</option>
                        </flux:select>
                    </div>

                    <!-- Shop Assignment -->
                    <div>
                        <flux:select wire:model="shop_id" label="Shop Assignment" required>
                            <option value="">Select a shop</option>
                            @foreach($this->availableShops as $shop)
                                <option value="{{ $shop->id }}">{{ $shop->name }} - {{ $shop->location }}</option>
                            @endforeach
                        </flux:select>
                        @if($this->availableShops->isEmpty())
                            <flux:text class="mt-2 text-sm text-amber-600 dark:text-amber-400">
                                No shops available. Please create a shop first.
                            </flux:text>
                        @endif
                    </div>

                    <!-- User Stats -->
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-800">
                        <flux:heading size="sm" class="mb-3">User Statistics</flux:heading>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="text-center">
                                <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Total Sales</flux:text>
                                <flux:text class="text-lg font-semibold">{{ $user->sales()->count() }}</flux:text>
                            </div>
                            <div class="text-center">
                                <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">Member Since</flux:text>
                                <flux:text class="text-lg font-semibold">{{ $user->created_at->format('M j, Y') }}</flux:text>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex flex-col sm:flex-row items-center justify-end gap-4 pt-6">
                        <flux:button variant="outline" type="button" :href="route('admin.users.index')" wire:navigate class="w-full sm:w-auto">
                            Cancel
                        </flux:button>
                        <flux:button variant="primary" type="submit" class="w-full sm:w-auto">
                            Update User
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
