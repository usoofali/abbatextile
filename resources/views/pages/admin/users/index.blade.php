            @if($users->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px]">
use App\Models\User;
use Illuminate\Support\Facades\Auth;
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">User Details</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Role</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Shop Assignment</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Sales Stats</th>
                                <th class="px-3 sm:px-6 py-3 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Actions</th>
    public $users;
    public $search = '';
    public $roleFilter = '';

    public function mount(): void
                                    <td class="px-3 sm:px-6 py-3">
        $this->loadUsers();
    }

    public function loadUsers(): void
    {
        $this->users = User::with(['shop'])
            ->where('role', '!=', User::ROLE_ADMIN) // Exclude admins from management
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%');
                                    <td class="px-3 sm:px-6 py-3">
            ->when($this->roleFilter, function ($query) {
                $query->where('role', $this->roleFilter);
            })
            ->latest()
            ->get();
    }

    public function updatedSearch(): void
                                    <td class="px-3 sm:px-6 py-3">
        $this->loadUsers();
    }

    public function updatedRoleFilter(): void
    {
        $this->loadUsers();
    }

    public function deleteUser(User $user): void
                                    <td class="px-3 sm:px-6 py-3">
        // Prevent deletion of admin users
        if ($user->isAdmin()) {
            session()->flash('error', 'Cannot delete admin users.');
            return;
        }

        // Check if user has sales
        if ($user->sales()->count() > 0) {
            session()->flash('error', 'Cannot delete user with existing sales records.');
            return;
        }
        // For now, we'll just show a message
        session()->flash('info', 'User status toggle functionality would be implemented here.');
    }
}; ?>

    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
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
        <div class="flex items-center gap-4">
            <div class="flex-1">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search users by name or email..."
                    icon="magnifying-glass"
                />
            </div>
            <div class="w-48">
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
                    <table class="w-full">
                        <thead class="border-b border-neutral-200 dark:border-neutral-700">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">User Details</th>
                                <th class="px-6 py-4 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Role</th>
                                <th class="px-6 py-4 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Shop Assignment</th>
                                <th class="px-6 py-4 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Sales Stats</th>
                                <th class="px-6 py-4 text-left text-sm font-medium text-neutral-600 dark:text-neutral-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach($users as $user)
                                <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-700/50">
                                    <td class="px-6 py-4">
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
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            @if($user->isManager())
                                                <flux:badge variant="blue">Manager</flux:badge>
                                            @elseif($user->isSalesperson())
                                                <flux:badge variant="green">Salesperson</flux:badge>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($user->shop)
                                            <div>
                                                <flux:text class="font-medium">{{ $user->shop->name }}</flux:text>
                                                <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">{{ $user->shop->location }}</flux:text>
                                            </div>
                                        @else
                                            <flux:text class="text-sm text-neutral-500 dark:text-neutral-500">No shop assigned</flux:text>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($user->isSalesperson())
                                            <div>
                                                <flux:text class="font-medium">{{ $user->sales()->count() }} sales</flux:text>
                                                <flux:text class="text-sm text-neutral-600 dark:text-neutral-400">
                                                    ${{ number_format($user->sales()->sum('total_price'), 2) }} revenue
                                                </flux:text>
                                            </div>
                                        @else
                                            <flux:text class="text-sm text-neutral-500 dark:text-neutral-500">N/A</flux:text>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <flux:button 
                                                variant="ghost" 
                                                size="sm" 
                                                wire:click="deleteUser({{ $user->id }})"
                                                wire:confirm="Are you sure you want to delete this user? This action cannot be undone."
                                                class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
                                            >
                                                <flux:icon name="trash" />
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
    </div>
