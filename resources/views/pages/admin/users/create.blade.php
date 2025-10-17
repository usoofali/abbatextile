<?php

use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Create User'])] class extends Component {
    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $role = '';
    public $shop_id = '';
    public $is_active = true;

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:manager,salesperson',
            'shop_id' => ['required', Rule::exists('shops', 'id')],
            'is_active' => 'boolean',
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'role' => $this->role,
            'shop_id' => $this->shop_id,
            'is_active' => (bool) $this->is_active,
        ]);

        // If creating a manager, ensure they manage only this shop
        if ($this->role === User::ROLE_MANAGER) {
            Shop::where('manager_id', $user->id)->update(['manager_id' => null]);
            Shop::find($this->shop_id)->update(['manager_id' => $user->id]);
        }

        session()->flash('success', 'User created successfully.');
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
                <flux:heading size="xl" level="1">Create New User</flux:heading>
                <flux:subheading size="lg">Add a new manager or salesperson to the system</flux:subheading>
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
                        label="Password"
                        type="password"
                        placeholder="Enter password"
                        required
                        viewable
                    />

                    <!-- Confirm Password -->
                    <flux:input
                        wire:model="password_confirmation"
                        label="Confirm Password"
                        type="password"
                        placeholder="Confirm password"
                        required
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

                    <!-- Active Toggle -->
                    <div>
                        <flux:switch wire:model="is_active" label="Active" />
                        <flux:text class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">
                            Toggle to deactivate the account to prevent login.
                        </flux:text>
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

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-4 pt-6">
                        <flux:button variant="outline" type="button" :href="route('admin.users.index')" wire:navigate>
                            Cancel
                        </flux:button>
                        <flux:button variant="primary" type="submit">
                            Create User
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>
    </div>
