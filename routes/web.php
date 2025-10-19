<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

// Route::get('/', function () {
//     return Volt('auth.login');
// })->name('login');

// Setup route (only accessible when no users exist)
Route::get('/setup', function () {
    return Volt('setup.configuration');
})->middleware('setup.not.completed')->name('setup');

// Redirect home to setup or login based on setup status
Route::get('/', function () {
    try {
        if (Schema::hasTable('users') && \App\Models\User::count() > 0) {
            return redirect()->route('login');
        }
    } catch (\Exception $e) {
        // Database connection failed, redirect to setup
    }
    
    return redirect()->route('setup');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');

    // Admin routes
    Route::middleware(['role:admin'])->group(function () {
        Volt::route('admin/dashboard', 'admin.dashboard')->name('admin.dashboard');
        Volt::route('admin/shops', 'admin.shops.index')->name('admin.shops.index');
        Volt::route('admin/shops/create', 'admin.shops.create')->name('admin.shops.create');
        Volt::route('admin/shops/{shop}/edit', 'admin.shops.edit')->name('admin.shops.edit');
        Volt::route('admin/users', 'admin.users.index')->name('admin.users.index');
        Volt::route('admin/users/create', 'admin.users.create')->name('admin.users.create');
        Volt::route('admin/users/{user}/edit', 'admin.users.edit')->name('admin.users.edit');
        Volt::route('settings/company', 'settings.company')->name('settings.company');
        Volt::route('admin/sales', 'admin.sales.index')->name('admin.sales.index');
    });

    // Manager routes
    Route::middleware(['role:manager'])->group(function () {
        Volt::route('manager/dashboard', 'manager.dashboard')->name('manager.dashboard');
        Volt::route('manager/products', 'manager.products.index')->name('manager.products.index');
        Volt::route('manager/products/create', 'manager.products.create')->name('manager.products.create');
        Volt::route('manager/products/{product}/edit', 'manager.products.edit')->name('manager.products.edit');
        Volt::route('manager/categories', 'manager.categories.index')->name('manager.categories.index');
        Volt::route('manager/categories/create', 'manager.categories.create')->name('manager.categories.create');
        Volt::route('manager/categories/{category}/edit', 'manager.categories.edit')->name('manager.categories.edit');
        Volt::route('manager/sales', 'manager.sales.index')->name('manager.sales.index');
        Volt::route('manager/stock', 'manager.stock.index')->name('manager.stock.index');
    });

    // Salesperson routes
    Route::middleware(['role:salesperson'])->group(function () {
        Volt::route('salesperson/dashboard', 'salesperson.dashboard')->name('salesperson.dashboard');
        Volt::route('salesperson/pos', 'salesperson.pos')->name('salesperson.pos');
        Volt::route('salesperson/sales', 'salesperson.sales.index')->name('salesperson.sales.index');
        Volt::route('salesperson/payments', 'salesperson.payments.index')->name('salesperson.payments.index');
    });

    // Admin stock route
    Route::middleware(['role:admin'])->group(function () {
        Volt::route('admin/stock', 'admin.stock.index')->name('admin.stock.index');
    });
});

require __DIR__.'/auth.php';
