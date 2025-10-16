<?php

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public function mount(): RedirectResponse
    {
        $user = Auth::user();
        dd($user);
        // Return the redirect so Livewire halts rendering and navigates immediately
        return match ($user->role) {
            User::ROLE_ADMIN => redirect()->route('admin.dashboard'),
            User::ROLE_MANAGER => redirect()->route('manager.dashboard'),
            User::ROLE_SALESPERSON => redirect()->route('salesperson.dashboard'),
            default => redirect()->route('login'),
        };
    }
}; ?>

<x-layouts.app :title="__('Dashboard123')">
    <div class="flex h-full w-full flex-1 flex-col items-center justify-center gap-4">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
        <flux:text>Redirecting to your dashboard...</flux:text>
    </div>
</x-layouts.app>
