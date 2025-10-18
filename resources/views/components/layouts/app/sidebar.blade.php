<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />
            @php
                $user = auth()->user();
                $dashboardRoute = 'dashboard';
                
                if ($user) {
                    $dashboardRoute = match ($user->role) {
                        \App\Models\User::ROLE_ADMIN => 'admin.dashboard',
                        \App\Models\User::ROLE_MANAGER => 'manager.dashboard',
                        \App\Models\User::ROLE_SALESPERSON => 'salesperson.dashboard',
                        default => 'dashboard',
                    };
                }
            @endphp

            <a href="{{ route($dashboardRoute) }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">

                @auth
                    @if(auth()->user()->isAdmin())
                        <flux:navlist.group :heading="__('Administration')" class="grid">
                            <flux:navlist.item icon="chart-bar" :href="route('admin.dashboard')" :current="request()->routeIs('admin.*')" wire:navigate>{{ __('Admin Dashboard') }}</flux:navlist.item>
                            <flux:navlist.item icon="chart-pie" :href="route('admin.sales.index')" :current="request()->routeIs('admin.sales.*')" wire:navigate>{{ __('Sales Report') }}</flux:navlist.item>
                            <flux:navlist.item icon="archive-box" :href="route('admin.stock.index')" :current="request()->routeIs('admin.stock.index')" wire:navigate>{{ __('Stock Management') }}</flux:navlist.item>
                            <flux:navlist.item icon="building-storefront" :href="route('admin.shops.index')" :current="request()->routeIs('admin.shops.*')" wire:navigate>{{ __('Manage Shops') }}</flux:navlist.item>
                            <flux:navlist.item icon="users" :href="route('admin.users.index')" :current="request()->routeIs('admin.users.*')" wire:navigate>{{ __('Manage Users') }}</flux:navlist.item>
                        </flux:navlist.group>
                    @elseif(auth()->user()->isManager())
                        <flux:navlist.group :heading="__('Management')" class="grid">
                            <flux:navlist.item icon="chart-bar" :href="route('manager.dashboard')" :current="request()->routeIs('manager.dashboard')" wire:navigate>{{ __('Manager Dashboard') }}</flux:navlist.item>
                            <flux:navlist.item icon="chart-pie" :href="route('manager.sales.index')" :current="request()->routeIs('manager.sales.*')" wire:navigate>{{ __('Sales Report') }}</flux:navlist.item>
                            <flux:navlist.item icon="archive-box" :href="route('manager.stock.index')" :current="request()->routeIs('manager.stock.index')" wire:navigate>{{ __('Stock Management') }}</flux:navlist.item>
                            <flux:navlist.item icon="cube" :href="route('manager.products.index')" :current="request()->routeIs('manager.products.*')" wire:navigate>{{ __('Manage Products') }}</flux:navlist.item>
                            <flux:navlist.item icon="tag" :href="route('manager.categories.index')" :current="request()->routeIs('manager.categories.*')" wire:navigate>{{ __('Manage Categories') }}</flux:navlist.item>
                        </flux:navlist.group>
                    @elseif(auth()->user()->isSalesperson())
                        <flux:navlist.group :heading="__('Sales')" class="grid">
                            <flux:navlist.item icon="chart-bar" :href="route('salesperson.dashboard')" :current="request()->routeIs('salesperson.dashboard')" wire:navigate>{{ __('Sales Dashboard') }}</flux:navlist.item>
                            <flux:navlist.item icon="shopping-cart" :href="route('salesperson.pos')" :current="request()->routeIs('salesperson.pos')" wire:navigate>{{ __('Point of Sale') }}</flux:navlist.item>
                            <flux:navlist.item icon="credit-card" :href="route('salesperson.payments.index')" :current="request()->routeIs('salesperson.payments.*')" wire:navigate>{{ __('Payments') }}</flux:navlist.item>
                            <flux:navlist.item icon="chart-pie" :href="route('salesperson.sales.index')" :current="request()->routeIs('salesperson.sales.*')" wire:navigate>{{ __('My Sales') }}</flux:navlist.item>
                        </flux:navlist.group>
                    @endif
                @endauth
            </flux:navlist>

            <flux:spacer />

            

            <!-- Desktop User Menu -->
            <flux:dropdown class="hidden lg:block" position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon:trailing="chevrons-up-down"
                    data-test="sidebar-menu-button"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full" data-test="logout-button">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full" data-test="logout-button">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
