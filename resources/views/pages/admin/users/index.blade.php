<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Manage Users'])] class extends Component {
    public $users;
    public $search = '';
    public $roleFilter = '';
    public $showDeleteModal = false;
    public $deleteUserId = null;

    public function mount(): void
    {
        $this->loadUsers();
    }

    public function loadUsers(): void
    {
        $this->users = User::with(['shop'])
            ->where('role', '!=', User::ROLE_ADMIN) // Exclude admins
            ->when($this->search, function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->where('name', 'like', '%' . $this->search . '%')
                             ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->roleFilter, function ($query) {
                $query->where('role', $this->roleFilter);
            })
            ->latest()
            ->get();
    }

    public function updatedSearch(): void
    {
        $this->loadUsers();
    }

    public function updatedRoleFilter(): void
    {
        $this->loadUsers();
    }

    public function deleteUser(User $user): void
    {
        if ($user->isAdmin()) {
            session()->flash('error', 'Cannot delete admin users.');
            return;
        }

        if ($user->sales()->count() > 0) {
            session()->flash('error', 'Cannot delete user with existing sales records.');
            return;
        }

        $user->delete();
        $this->loadUsers();
        session()->flash('success', 'User deleted successfully.');
    }

    public function promptDelete(string $id): void
    {
        $this->deleteUserId = $id;
        $this->showDeleteModal = true;
    }

    public function confirmDelete(): void
    {
        if ($this->deleteUserId) {
            $user = User::find($this->deleteUserId);
            if ($user) {
                $this->deleteUser($user);
            }
        }
        $this->showDeleteModal = false;
        $this->deleteUserId = null;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->deleteUserId = null;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 px-3 sm:px-6 overflow-x-auto">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <flux:heading size="xl" level="1">Manage Users</flux:heading>
            <flux:subheading size="lg">View and manage managers and salespersons</flux:subheading>
        </div>
        <flux:button variant="primary" :href="route('admin.users.create')" wire:navigate>
            <flux:icon name="user-plus" />
            Add New User
        </flux:button>
    </div>

    <!-- Search and Filters -->
    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
        <div class="flex-1 w-full">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Search users by name or email..."
                icon="magnifying-glass"
            />
        </div>
        <div class="w-full sm:w-48">
            <flux:select wire:model.live="roleFilter" placeholder="Filter by role">
                <option value="">All Roles</option>
                <option value="manager">Managers</option>
                <option value="salesperson">Salespersons</option>
            </flux:select>
        </div>
    </div>

    <!-- Users Table -->
    <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-800">
        @if($users->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px]">
                    <thead class="border-b border-neutral-200 dark:border-neutral-700">
                        <tr>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">User Details</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Role</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Shop Assignment</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Sales Stats</th>
                            <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @foreach($users as $user)
                            <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/50">
                                <td class="px-3 sm:px-6 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-neutral-200 text-sm font-medium dark:bg-neutral-700">
                                            {{ $user->initials() }}
                                        </div>
                                        <div>
                                            <flux:text class="font-medium">{{ $user->name }}</flux:text>
                                            <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ $user->email }}</flux:text>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-3 sm:px-6 py-3">
                                    @if($user->isManager())
                                        <flux:badge variant="blue">Manager</flux:badge>
                                    @elseif($user->isSalesperson())
                                        <flux:badge variant="green">Salesperson</flux:badge>
                                    @endif
                                </td>

                                <td class="px-3 sm:px-6 py-3">
                                    @if($user->shop)
                                        <div>
                                            <flux:text class="font-medium">{{ $user->shop->name }}</flux:text>
                                            <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ $user->shop->location }}</flux:text>
                                        </div>
                                    @else
                                        <flux:text class="text-sm text-neutral-500 dark:text-neutral-500">No shop assigned</flux:text>
                                    @endif
                                </td>

                                <td class="px-3 sm:px-6 py-3">
                                    @if($user->isSalesperson())
                                        <div>
                                            <flux:text class="font-medium">{{ $user->sales()->count() }} sales</flux:text>
                                            <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">
                                                ₦{{ number_format($user->sales()->sum('total_amount'), 2) }} revenue
                                            </flux:text>
                                        </div>
                                    @else
                                        <flux:text class="text-sm text-neutral-500 dark:text-neutral-500">N/A</flux:text>
                                    @endif
                                </td>

                                <td class="px-3 sm:px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <flux:button 
                                        variant="ghost" 
                                        icon="pencil"
                                        size="sm" :href="route('admin.users.edit', $user)" wire:navigate>
                                            
                                            Edit
                                        </flux:button>
                                        <flux:button 
                                            variant="ghost" 
                                            icon="trash"
                                            size="sm" 
                                            wire:click="promptDelete('{{ $user->id }}')"
                                            class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                        >
                                            Delete
                                        </flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="py-12 text-center">
                <flux:icon name="users" class="mx-auto size-12 text-neutral-400" />
                <flux:heading size="lg" class="mt-4">No users found</flux:heading>
                <flux:text class="mt-2 text-neutral-600 dark:text-neutral-400">
                    @if($search || $roleFilter)
                        No users match your search criteria.
                    @else
                        Get started by creating your first user.
                    @endif
                </flux:text>
                @if(!$search && !$roleFilter)
                    <div class="mt-6">
                        <flux:button variant="primary" :href="route('admin.users.create')" wire:navigate>
                            <flux:icon name="user-plus" />
                            Create First User
                        </flux:button>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <!-- Delete Confirmation Modal -->
    <flux:modal wire:model="showDeleteModal">
        <div class="p-6">
            <flux:heading size="lg">Delete user?</flux:heading>
            <flux:text class="mt-2 text-neutral-600 dark:text-neutral-300">
                Are you sure you want to delete this user? This action cannot be undone.
            </flux:text>
            <div class="mt-6 flex justify-end gap-3">
                <flux:button variant="outline" wire:click="cancelDelete">Cancel</flux:button>
                <flux:button variant="primary" class="bg-red-600 hover:bg-red-700 text-white dark:bg-red-500 dark:hover:bg-red-400" wire:click="confirmDelete">Delete</flux:button>
            </div>
        </div>
    </flux:modal>
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
