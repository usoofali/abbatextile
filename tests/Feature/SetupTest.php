<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\CompanySetting;
use App\Models\Shop;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear any existing data
    User::truncate();
    CompanySetting::truncate();
    Shop::truncate();
    Category::truncate();
    Product::truncate();
});

test('setup page is accessible when no users exist', function () {
    $response = $this->get('/setup');
    
    $response->assertStatus(200);
    $response->assertSeeLivewire('setup.configuration');
});

test('setup page redirects to login when users exist', function () {
    User::factory()->create();
    
    $response = $this->get('/setup');
    
    $response->assertRedirect(route('login'));
});

test('home page redirects to setup when no users exist', function () {
    $response = $this->get('/');
    
    $response->assertRedirect(route('setup'));
});

test('home page redirects to login when users exist', function () {
    User::factory()->create();
    
    $response = $this->get('/');
    
    $response->assertRedirect(route('login'));
});

test('setup component validates required fields', function () {
    Volt::test('setup.configuration')
        ->set('app_name', '')
        ->set('app_url', '')
        ->set('db_host', '')
        ->set('db_database', '')
        ->set('db_username', '')
        ->set('db_password', '')
        ->set('admin_name', '')
        ->set('admin_email', '')
        ->set('admin_password', '')
        ->set('company_name', '')
        ->call('startSetup')
        ->assertHasErrors([
            'app_name',
            'app_url',
            'db_host',
            'db_database',
            'db_username',
            'db_password',
            'admin_name',
            'admin_email',
            'admin_password',
            'company_name',
        ]);
});

test('setup component validates email format', function () {
    Volt::test('setup.configuration')
        ->set('admin_email', 'invalid-email')
        ->call('startSetup')
        ->assertHasErrors(['admin_email']);
});

test('setup component validates password length', function () {
    Volt::test('setup.configuration')
        ->set('admin_password', '123')
        ->call('startSetup')
        ->assertHasErrors(['admin_password']);
});

test('setup creates admin user successfully', function () {
    $this->seedDatabase();
    
    Volt::test('setup.configuration')
        ->set('app_name', 'Test App')
        ->set('app_url', 'https://test.com')
        ->set('db_host', 'localhost')
        ->set('db_port', '3306')
        ->set('db_database', 'test_db')
        ->set('db_username', 'test_user')
        ->set('db_password', 'test_pass')
        ->set('admin_name', 'Test Admin')
        ->set('admin_email', 'admin@test.com')
        ->set('admin_password', 'password123')
        ->set('company_name', 'Test Company')
        ->call('startSetup');
    
    $this->assertDatabaseHas('users', [
        'name' => 'Test Admin',
        'email' => 'admin@test.com',
        'role' => User::ROLE_ADMIN,
    ]);
});

test('setup creates company settings successfully', function () {
    $this->seedDatabase();
    
    Volt::test('setup.configuration')
        ->set('app_name', 'Test App')
        ->set('app_url', 'https://test.com')
        ->set('db_host', 'localhost')
        ->set('db_port', '3306')
        ->set('db_database', 'test_db')
        ->set('db_username', 'test_user')
        ->set('db_password', 'test_pass')
        ->set('admin_name', 'Test Admin')
        ->set('admin_email', 'admin@test.com')
        ->set('admin_password', 'password123')
        ->set('company_name', 'Test Company')
        ->set('company_email', 'info@test.com')
        ->set('company_phone', '+1234567890')
        ->set('company_address', 'Test Address')
        ->call('startSetup');
    
    $this->assertDatabaseHas('company_settings', [
        'company_name' => 'Test Company',
        'company_email' => 'info@test.com',
        'company_phone' => '+1234567890',
        'company_address' => 'Test Address',
    ]);
});

test('setup creates sample data successfully', function () {
    $this->seedDatabase();
    
    Volt::test('setup.configuration')
        ->set('app_name', 'Test App')
        ->set('app_url', 'https://test.com')
        ->set('db_host', 'localhost')
        ->set('db_port', '3306')
        ->set('db_database', 'test_db')
        ->set('db_username', 'test_user')
        ->set('db_password', 'test_pass')
        ->set('admin_name', 'Test Admin')
        ->set('admin_email', 'admin@test.com')
        ->set('admin_password', 'password123')
        ->set('company_name', 'Test Company')
        ->call('startSetup');
    
    // Check sample shop was created
    $this->assertDatabaseHas('shops', [
        'name' => 'Main Branch',
        'location' => 'Lagos, Nigeria',
    ]);
    
    // Check sample categories were created
    $this->assertDatabaseHas('categories', [
        'name' => 'Cotton Fabrics',
        'default_unit_type' => 'yard',
    ]);
    
    $this->assertDatabaseHas('categories', [
        'name' => 'Silk Materials',
        'default_unit_type' => 'yard',
    ]);
    
    // Check sample products were created
    $this->assertDatabaseHas('products', [
        'name' => 'Premium Cotton',
        'price_per_unit' => 2500,
        'stock_quantity' => 100,
    ]);
});

test('setup skips sample data when requested', function () {
    $this->seedDatabase();
    
    Volt::test('setup.configuration')
        ->set('app_name', 'Test App')
        ->set('app_url', 'https://test.com')
        ->set('db_host', 'localhost')
        ->set('db_port', '3306')
        ->set('db_database', 'test_db')
        ->set('db_username', 'test_user')
        ->set('db_password', 'test_pass')
        ->set('admin_name', 'Test Admin')
        ->set('admin_email', 'admin@test.com')
        ->set('admin_password', 'password123')
        ->set('company_name', 'Test Company')
        ->call('skipSampleData');
    
    // Admin user should be created
    $this->assertDatabaseHas('users', [
        'name' => 'Test Admin',
        'email' => 'admin@test.com',
        'role' => User::ROLE_ADMIN,
    ]);
    
    // Company settings should be created
    $this->assertDatabaseHas('company_settings', [
        'company_name' => 'Test Company',
    ]);
    
    // But no sample data should be created
    $this->assertDatabaseCount('shops', 0);
    $this->assertDatabaseCount('categories', 0);
    $this->assertDatabaseCount('products', 0);
});

test('setup handles existing admin user', function () {
    $this->seedDatabase();
    
    // Create existing admin user
    $existingUser = User::create([
        'name' => 'Existing Admin',
        'email' => 'admin@test.com',
        'password' => bcrypt('old_password'),
        'role' => User::ROLE_ADMIN,
    ]);
    
    Volt::test('setup.configuration')
        ->set('app_name', 'Test App')
        ->set('app_url', 'https://test.com')
        ->set('db_host', 'localhost')
        ->set('db_port', '3306')
        ->set('db_database', 'test_db')
        ->set('db_username', 'test_user')
        ->set('db_password', 'test_pass')
        ->set('admin_name', 'Updated Admin')
        ->set('admin_email', 'admin@test.com')
        ->set('admin_password', 'new_password123')
        ->set('company_name', 'Test Company')
        ->call('startSetup');
    
    $existingUser->refresh();
    
    // Should update existing user
    expect($existingUser->name)->toBe('Updated Admin');
    expect($existingUser->email)->toBe('admin@test.com');
    expect($existingUser->role)->toBe(User::ROLE_ADMIN);
    
    // Should update password
    expect(Hash::check('new_password123', $existingUser->password))->toBeTrue();
});

test('setup handles existing company settings', function () {
    $this->seedDatabase();
    
    // Create existing company settings
    CompanySetting::create([
        'id' => 1,
        'company_name' => 'Existing Company',
        'company_email' => 'existing@test.com',
    ]);
    
    Volt::test('setup.configuration')
        ->set('app_name', 'Test App')
        ->set('app_url', 'https://test.com')
        ->set('db_host', 'localhost')
        ->set('db_port', '3306')
        ->set('db_database', 'test_db')
        ->set('db_username', 'test_user')
        ->set('db_password', 'test_pass')
        ->set('admin_name', 'Test Admin')
        ->set('admin_email', 'admin@test.com')
        ->set('admin_password', 'password123')
        ->set('company_name', 'Updated Company')
        ->set('company_email', 'updated@test.com')
        ->set('company_phone', '+1234567890')
        ->call('startSetup');
    
    // Should update existing company settings
    $this->assertDatabaseHas('company_settings', [
        'id' => 1,
        'company_name' => 'Updated Company',
        'company_email' => 'updated@test.com',
        'company_phone' => '+1234567890',
    ]);
});

test('setup logs progress correctly', function () {
    $this->seedDatabase();
    
    $component = Volt::test('setup.configuration')
        ->set('app_name', 'Test App')
        ->set('app_url', 'https://test.com')
        ->set('db_host', 'localhost')
        ->set('db_port', '3306')
        ->set('db_database', 'test_db')
        ->set('db_username', 'test_user')
        ->set('db_password', 'test_pass')
        ->set('admin_name', 'Test Admin')
        ->set('admin_email', 'admin@test.com')
        ->set('admin_password', 'password123')
        ->set('company_name', 'Test Company')
        ->call('startSetup');
    
    $logs = $component->get('logs');
    
    expect($logs)->toBeArray();
    expect(count($logs))->toBeGreaterThan(0);
    
    // Check for key log messages
    $logMessages = collect($logs)->pluck('message');
    expect($logMessages)->toContain('Starting application setup...');
    expect($logMessages)->toContain('Application setup completed successfully!');
    expect($logMessages)->toContain('Storage link created successfully');
});

test('setup shows progress correctly', function () {
    $this->seedDatabase();
    
    $component = Volt::test('setup.configuration')
        ->set('app_name', 'Test App')
        ->set('app_url', 'https://test.com')
        ->set('db_host', 'localhost')
        ->set('db_port', '3306')
        ->set('db_database', 'test_db')
        ->set('db_username', 'test_user')
        ->set('db_password', 'test_pass')
        ->set('admin_name', 'Test Admin')
        ->set('admin_email', 'admin@test.com')
        ->set('admin_password', 'password123')
        ->set('company_name', 'Test Company')
        ->call('startSetup');
    
    // Progress should be 100% after completion
    expect($component->get('progress'))->toBe(100.0);
    expect($component->get('isSetupComplete'))->toBeTrue();
});

test('setup creates storage link successfully', function () {
    $this->seedDatabase();
    
    // Ensure storage link doesn't exist initially
    if (file_exists(public_path('storage'))) {
        unlink(public_path('storage'));
    }
    
    $component = Volt::test('setup.configuration')
        ->set('app_name', 'Test App')
        ->set('app_url', 'https://test.com')
        ->set('db_host', 'localhost')
        ->set('db_port', '3306')
        ->set('db_database', 'test_db')
        ->set('db_username', 'test_user')
        ->set('db_password', 'test_pass')
        ->set('admin_name', 'Test Admin')
        ->set('admin_email', 'admin@test.com')
        ->set('admin_password', 'password123')
        ->set('company_name', 'Test Company')
        ->call('startSetup');
    
    // Check if storage link was created
    expect(file_exists(public_path('storage')))->toBeTrue();
    
    // Check if it's a symbolic link
    if (function_exists('is_link')) {
        expect(is_link(public_path('storage')))->toBeTrue();
    }
});

test('setup handles storage link creation failure gracefully', function () {
    $this->seedDatabase();
    
    // Create a file with the same name as the storage link to simulate failure
    file_put_contents(public_path('storage'), 'test');
    
    $component = Volt::test('setup.configuration')
        ->set('app_name', 'Test App')
        ->set('app_url', 'https://test.com')
        ->set('db_host', 'localhost')
        ->set('db_port', '3306')
        ->set('db_database', 'test_db')
        ->set('db_username', 'test_user')
        ->set('db_password', 'test_pass')
        ->set('admin_name', 'Test Admin')
        ->set('admin_email', 'admin@test.com')
        ->set('admin_password', 'password123')
        ->set('company_name', 'Test Company')
        ->call('startSetup');
    
    $logs = $component->get('logs');
    $logMessages = collect($logs)->pluck('message');
    
    // Should log warning about storage link creation failure
    expect($logMessages)->toContain('Storage link creation failed (may already exist):');
    
    // Setup should still complete successfully
    expect($component->get('isSetupComplete'))->toBeTrue();
});

// Helper method to seed the database with basic structure
function seedDatabase(): void
{
    Artisan::call('migrate', ['--force' => true]);
}
