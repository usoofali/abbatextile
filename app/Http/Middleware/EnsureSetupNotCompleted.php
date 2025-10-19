<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSetupNotCompleted
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Check if database is connected and setup is completed
            if (Schema::hasTable('users') && User::count() > 0) {
                // Setup is completed, redirect to login
                return redirect()->route('login');
            }
        } catch (\Exception $e) {
            // Database connection failed, allow access to setup
            // This is expected during initial setup
        }

        return $next($request);
    }
}
