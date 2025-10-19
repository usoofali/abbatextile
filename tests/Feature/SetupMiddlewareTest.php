<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('middleware allows access to setup when no users exist', function () {
    $response = $this->get('/setup');
    
    $response->assertStatus(200);
    $response->assertSeeLivewire('setup.configuration');
});

test('middleware redirects to login when users exist', function () {
    User::factory()->create();
    
    $response = $this->get('/setup');
    
    $response->assertRedirect(route('login'));
});

test('middleware handles database connection errors gracefully', function () {
    // Mock database connection failure
    Schema::shouldReceive('hasTable')
        ->with('users')
        ->andThrow(new \Exception('Database connection failed'));
    
    $response = $this->get('/setup');
    
    $response->assertStatus(200);
    $response->assertSeeLivewire('setup.configuration');
});

test('middleware works with different user roles', function () {
    // Test with admin user
    User::factory()->create(['role' => User::ROLE_ADMIN]);
    
    $response = $this->get('/setup');
    $response->assertRedirect(route('login'));
    
    // Test with manager user
    User::truncate();
    User::factory()->create(['role' => User::ROLE_MANAGER]);
    
    $response = $this->get('/setup');
    $response->assertRedirect(route('login'));
    
    // Test with salesperson user
    User::truncate();
    User::factory()->create(['role' => User::ROLE_SALESPERSON]);
    
    $response = $this->get('/setup');
    $response->assertRedirect(route('login'));
});

test('middleware allows setup after user deletion', function () {
    // Create and then delete user
    $user = User::factory()->create();
    $user->delete();
    
    $response = $this->get('/setup');
    
    $response->assertStatus(200);
    $response->assertSeeLivewire('setup.configuration');
});
