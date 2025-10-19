<?php

use App\Models\User;
use App\Models\Shop;
use App\Models\Category;
use App\Models\Product;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new class extends Component {
    #[Validate('required|string|max:255')]
    public string $app_name = 'AbbaTextiles POS';
    
    #[Validate('required|string|max:255')]
    public string $app_url = '';
    
    #[Validate('required|string|max:255')]
    public string $db_connection = 'mysql';
    
    #[Validate('required|string|max:255')]
    public string $db_host = 'localhost';
    
    #[Validate('required|string|max:255')]
    public string $db_port = '3306';
    
    #[Validate('required|string|max:255')]
    public string $db_database = '';
    
    #[Validate('required|string|max:255')]
    public string $db_username = '';
    
    #[Validate('required|string|max:255')]
    public string $db_password = '';
    
    #[Validate('required|string|max:255')]
    public string $admin_name = 'Administrator';
    
    #[Validate('required|string|email|max:255')]
    public string $admin_email = '';
    
    #[Validate('required|string|min:8')]
    public string $admin_password = '';
    
    #[Validate('required|string|max:255')]
    public string $company_name = 'AbbaTextiles Nigeria Limited';
    
    #[Validate('nullable|string|max:500')]
    public ?string $company_address = null;
    
    #[Validate('nullable|string|max:50')]
    public ?string $company_phone = null;
    
    #[Validate('nullable|string|email|max:255')]
    public ?string $company_email = null;
    
    public bool $isSetupComplete = false;
    public array $setupSteps = [];
    public string $currentStep = 'configuration';
    public array $logs = [];
    public bool $isProcessing = false;
    public float $progress = 0;

    public function mount(): void
    {
        $this->checkSetupStatus();
        $this->initializeSetupSteps();
        
        // Try to get default values from environment
        $this->app_name = config('app.name', 'AbbaTextiles POS');
        $this->app_url = config('app.url', '');
        $this->db_host = config('database.connections.mysql.host', 'localhost');
        $this->db_port = config('database.connections.mysql.port', '3306');
        $this->db_database = config('database.connections.mysql.database', '');
        $this->db_username = config('database.connections.mysql.username', '');
        $this->db_password = config('database.connections.mysql.password', '');
    }

    public function checkSetupStatus(): void
    {
        try {
            // Check if database is connected and tables exist
            if (Schema::hasTable('users') && User::count() > 0) {
                $this->isSetupComplete = true;
            }
        } catch (\Exception $e) {
            $this->isSetupComplete = false;
        }
    }

    public function initializeSetupSteps(): void
    {
        $this->setupSteps = [
            'configuration' => [
                'title' => 'Configuration',
                'description' => 'Configure application settings and database connection',
                'completed' => false,
                'active' => true,
            ],
            'database' => [
                'title' => 'Database Setup',
                'description' => 'Run migrations and create database tables',
                'completed' => false,
                'active' => false,
            ],
            'admin' => [
                'title' => 'Create Admin User',
                'description' => 'Create the administrator account',
                'completed' => false,
                'active' => false,
            ],
            'company' => [
                'title' => 'Company Settings',
                'description' => 'Configure company information',
                'completed' => false,
                'active' => false,
            ],
            'sample_data' => [
                'title' => 'Sample Data',
                'description' => 'Create sample shops, categories, and products',
                'completed' => false,
                'active' => false,
            ],
            'optimization' => [
                'title' => 'Optimization',
                'description' => 'Create storage links and optimize application',
                'completed' => false,
                'active' => false,
            ],
            'complete' => [
                'title' => 'Setup Complete',
                'description' => 'Application is ready to use',
                'completed' => false,
                'active' => false,
            ],
        ];
    }

    public function updateEnvironment(): void
    {
        $this->addLog('Updating environment configuration...');
        
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);
        
        $replacements = [
            'APP_NAME="' . $this->app_name . '"',
            'APP_URL=' . $this->app_url,
            'DB_CONNECTION=' . $this->db_connection,
            'DB_HOST=' . $this->db_host,
            'DB_PORT=' . $this->db_port,
            'DB_DATABASE=' . $this->db_database,
            'DB_USERNAME=' . $this->db_username,
            'DB_PASSWORD="' . $this->db_password . '"',
        ];
        
        foreach ($replacements as $replacement) {
            $key = explode('=', $replacement)[0];
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
            
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n" . $replacement;
            }
        }
        
        file_put_contents($envPath, $envContent);
        
        // Clear config cache
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        
        $this->addLog('Environment configuration updated successfully');
    }

    public function testDatabaseConnection(): bool
    {
        try {
            $this->addLog('Testing database connection...');
            
            config([
                'database.connections.mysql.host' => $this->db_host,
                'database.connections.mysql.port' => $this->db_port,
                'database.connections.mysql.database' => $this->db_database,
                'database.connections.mysql.username' => $this->db_username,
                'database.connections.mysql.password' => $this->db_password,
            ]);
            
            DB::connection()->getPdo();
            $this->addLog('Database connection successful');
            return true;
        } catch (\Exception $e) {
            $this->addLog('Database connection failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    public function runMigrations(): bool
    {
        try {
            $this->addLog('Running database migrations...');
            
            // Run migrations
            Artisan::call('migrate', ['--force' => true]);
            
            $this->addLog('Database migrations completed successfully');
            return true;
        } catch (\Exception $e) {
            $this->addLog('Migration failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    public function createAdminUser(): bool
    {
        try {
            $this->addLog('Creating administrator user...');
            
            // Check if admin user already exists
            if (User::where('email', $this->admin_email)->exists()) {
                $this->addLog('Admin user already exists, updating...');
                $user = User::where('email', $this->admin_email)->first();
                $user->update([
                    'name' => $this->admin_name,
                    'password' => Hash::make($this->admin_password),
                    'role' => User::ROLE_ADMIN,
                ]);
            } else {
                User::create([
                    'name' => $this->admin_name,
                    'email' => $this->admin_email,
                    'password' => Hash::make($this->admin_password),
                    'role' => User::ROLE_ADMIN,
                    'email_verified_at' => now(),
                ]);
            }
            
            $this->addLog('Administrator user created successfully');
            return true;
        } catch (\Exception $e) {
            $this->addLog('Failed to create admin user: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    public function setupCompanySettings(): bool
    {
        try {
            $this->addLog('Setting up company settings...');
            
            CompanySetting::updateOrCreate(
                ['id' => 1],
                [
                    'company_name' => $this->company_name,
                    'company_address' => $this->company_address,
                    'company_phone' => $this->company_phone,
                    'company_email' => $this->company_email,
                ]
            );
            
            $this->addLog('Company settings configured successfully');
            return true;
        } catch (\Exception $e) {
            $this->addLog('Failed to setup company settings: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    public function createSampleData(): bool
    {
        try {
            $this->addLog('Creating sample data...');
            
            // Create sample shop
            $shop = Shop::firstOrCreate(
                ['name' => 'Main Branch'],
                [
                    'location' => 'Lagos, Nigeria',
                    'description' => 'Main textile shop location',
                ]
            );
            
            // Create sample categories
            $categories = [
                ['name' => 'Cotton Fabrics', 'default_unit_type' => 'yard'],
                ['name' => 'Silk Materials', 'default_unit_type' => 'yard'],
                ['name' => 'Linen Textiles', 'default_unit_type' => 'meter'],
                ['name' => 'Denim Materials', 'default_unit_type' => 'yard'],
            ];
            
            foreach ($categories as $categoryData) {
                Category::firstOrCreate(
                    ['name' => $categoryData['name']],
                    ['default_unit_type' => $categoryData['default_unit_type']]
                );
            }
            
            // Create sample products
            $products = [
                ['name' => 'Premium Cotton', 'category' => 'Cotton Fabrics', 'price_per_unit' => 2500, 'stock_quantity' => 100],
                ['name' => 'Silk Satin', 'category' => 'Silk Materials', 'price_per_unit' => 5000, 'stock_quantity' => 50],
                ['name' => 'Linen Blend', 'category' => 'Linen Textiles', 'price_per_unit' => 3500, 'stock_quantity' => 75],
                ['name' => 'Denim Fabric', 'category' => 'Denim Materials', 'price_per_unit' => 4000, 'stock_quantity' => 60],
            ];
            
            foreach ($products as $productData) {
                $category = Category::where('name', $productData['category'])->first();
                Product::firstOrCreate(
                    ['name' => $productData['name']],
                    [
                        'category_id' => $category->id,
                        'shop_id' => $shop->id,
                        'price_per_unit' => $productData['price_per_unit'],
                        'stock_quantity' => $productData['stock_quantity'],
                        'unit_type' => $category->default_unit_type,
                        'description' => 'High quality ' . strtolower($productData['name']) . ' material',
                    ]
                );
            }
            
            $this->addLog('Sample data created successfully');
            return true;
        } catch (\Exception $e) {
            $this->addLog('Failed to create sample data: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    public function optimizeApplication(): void
    {
        try {
            $this->addLog('Optimizing application...');
            
            // Create storage link for file uploads
            try {
                Artisan::call('storage:link');
                $this->addLog('Storage link created successfully');
            } catch (\Exception $e) {
                $this->addLog('Storage link creation failed (may already exist): ' . $e->getMessage(), 'warning');
            }
            
            // Clear and cache config
            Artisan::call('config:clear');
            Artisan::call('config:cache');
            
            // Clear and cache routes
            Artisan::call('route:clear');
            Artisan::call('route:cache');
            
            // Clear and cache views
            Artisan::call('view:clear');
            Artisan::call('view:cache');
            
            // Generate application key if not exists
            if (empty(config('app.key'))) {
                Artisan::call('key:generate', ['--force' => true]);
            }
            
            $this->addLog('Application optimization completed');
        } catch (\Exception $e) {
            $this->addLog('Optimization failed: ' . $e->getMessage(), 'error');
        }
    }

    public function addLog(string $message, string $level = 'info'): void
    {
        $this->logs[] = [
            'message' => $message,
            'level' => $level,
            'timestamp' => now()->format('H:i:s'),
        ];
        
        // Keep only last 50 logs
        if (count($this->logs) > 50) {
            $this->logs = array_slice($this->logs, -50);
        }
    }

    public function startSetup(): void
    {
        $this->validate();
        $this->isProcessing = true;
        $this->logs = [];
        $this->progress = 0;
        
        try {
            $this->addLog('Starting application setup...');
            
            // Step 1: Update environment
            $this->updateEnvironment();
            $this->progress = 14.29;
            
            // Step 2: Test database connection
            if (!$this->testDatabaseConnection()) {
                throw new \Exception('Database connection failed');
            }
            $this->progress = 28.57;
            
            // Step 3: Run migrations
            if (!$this->runMigrations()) {
                throw new \Exception('Migration failed');
            }
            $this->progress = 42.86;
            
            // Step 4: Create admin user
            if (!$this->createAdminUser()) {
                throw new \Exception('Admin user creation failed');
            }
            $this->progress = 57.14;
            
            // Step 5: Setup company settings
            if (!$this->setupCompanySettings()) {
                throw new \Exception('Company settings setup failed');
            }
            $this->progress = 71.43;
            
            // Step 6: Create sample data
            if (!$this->createSampleData()) {
                throw new \Exception('Sample data creation failed');
            }
            $this->progress = 85.71;
            
            // Step 7: Optimize application
            $this->optimizeApplication();
            $this->progress = 100;
            
            $this->addLog('Application setup completed successfully!', 'success');
            $this->isSetupComplete = true;
            
            // Redirect to login after 3 seconds
            $this->dispatch('setup-completed');
            
        } catch (\Exception $e) {
            $this->addLog('Setup failed: ' . $e->getMessage(), 'error');
        } finally {
            $this->isProcessing = false;
        }
    }

    public function skipSampleData(): void
    {
        $this->isProcessing = true;
        
        try {
            $this->addLog('Skipping sample data creation...');
            $this->optimizeApplication();
            $this->addLog('Application setup completed successfully!', 'success');
            $this->isSetupComplete = true;
            $this->dispatch('setup-completed');
        } catch (\Exception $e) {
            $this->addLog('Setup failed: ' . $e->getMessage(), 'error');
        } finally {
            $this->isProcessing = false;
        }
    }
}; ?>

<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-900 dark:to-gray-800">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="flex justify-center mb-4">
                    <x-app-logo class="w-20 h-20" />
                </div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                    {{ $isSetupComplete ? 'Setup Complete!' : 'Application Setup' }}
                </h1>
                <p class="text-lg text-gray-600 dark:text-gray-400 mt-2">
                    {{ $isSetupComplete ? 'Your application is ready to use' : 'Configure your AbbaTextiles POS system' }}
                </p>
            </div>

            @if ($isSetupComplete)
                <!-- Setup Complete -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-8 text-center">
                    <div class="mb-6">
                        <div class="w-16 h-16 text-green-500 mx-auto mb-4">
                            <svg fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-green-600 dark:text-green-400 mb-2">
                            Setup Completed Successfully!
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400">
                            Your AbbaTextiles POS system is now ready to use. You can now log in with your administrator account.
                        </p>
                    </div>
                    
                    <div class="space-y-4">
                        <a href="{{ route('login') }}" class="inline-flex items-center px-6 py-3 bg-blue-600 border border-transparent rounded-md font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                            Go to Login
                        </a>
                    </div>
                </div>
            @else
                <!-- Setup Form -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
                    <!-- Progress Bar -->
                    @if ($isProcessing)
                        <div class="bg-blue-50 dark:bg-blue-900/20 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-blue-600 dark:text-blue-400">
                                    Setting up application...
                                </span>
                                <span class="text-sm font-medium text-blue-600 dark:text-blue-400">
                                    {{ number_format($progress, 1) }}%
                                </span>
                            </div>
                            <div class="w-full bg-blue-200 dark:bg-blue-800 rounded-full h-2">
                                <div class="bg-blue-600 dark:bg-blue-400 h-2 rounded-full transition-all duration-300" 
                                     style="width: {{ $progress }}%"></div>
                            </div>
                        </div>
                    @endif

                    <!-- Setup Steps -->
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                            @foreach ($setupSteps as $key => $step)
                                <div class="flex items-center space-x-3 p-3 rounded-lg {{ $step['active'] ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-gray-50 dark:bg-gray-700' }}">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium
                                            {{ $step['completed'] ? 'bg-green-500 text-white' : 
                                               ($step['active'] ? 'bg-blue-500 text-white' : 'bg-gray-300 text-gray-600 dark:bg-gray-600 dark:text-gray-400') }}">
                                            @if ($step['completed'])
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                </svg>
                                            @else
                                                {{ array_search($key, array_keys($setupSteps)) + 1 }}
                                            @endif
                                        </div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <span class="text-sm font-medium {{ $step['active'] ? 'text-blue-600 dark:text-blue-400' : 'text-gray-900 dark:text-white' }}">
                                            {{ $step['title'] }}
                                        </span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $step['description'] }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if ($isProcessing)
                            <!-- Processing Logs -->
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-6">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-3">
                                    Setup Progress
                                </h3>
                                <div class="space-y-2 max-h-60 overflow-y-auto">
                                    @foreach ($logs as $log)
                                        <div class="flex items-start space-x-3">
                                            <span class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0">
                                                {{ $log['timestamp'] }}
                                            </span>
                                            <span class="text-xs flex-1 {{ $log['level'] === 'error' ? 'text-red-600 dark:text-red-400' : 
                                                ($log['level'] === 'success' ? 'text-green-600 dark:text-green-400' : 
                                                ($log['level'] === 'warning' ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-600 dark:text-gray-400')) }}">
                                                {{ $log['message'] }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <!-- Configuration Form -->
                            <form wire:submit="startSetup" class="space-y-8">
                                <!-- Application Settings -->
                                <div>
                                    <h2 class="text-xl font-bold mb-4">Application Settings</h2>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Application Name
                                            </label>
                                            <input type="text" wire:model="app_name" 
                                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                                   placeholder="AbbaTextiles POS" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Application URL
                                            </label>
                                            <input type="url" wire:model="app_url" 
                                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                                   placeholder="https://yourdomain.com" required>
                                        </div>
                                    </div>
                                </div>

                                <!-- Database Settings -->
                                <div>
                                    <h2 class="text-xl font-bold mb-4">Database Configuration</h2>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Database Host
                                            </label>
                                            <input type="text" wire:model="db_host" 
                                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                                   placeholder="localhost" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Database Port
                                            </label>
                                            <input type="text" wire:model="db_port" 
                                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                                   placeholder="3306" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Database Name
                                            </label>
                                            <input type="text" wire:model="db_database" 
                                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                                   placeholder="abbatextiles" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Database Username
                                            </label>
                                            <input type="text" wire:model="db_username" 
                                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                                   placeholder="username" required>
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Database Password
                                            </label>
                                            <input type="password" wire:model="db_password" 
                                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                                   placeholder="password" required>
                                        </div>
                                    </div>
                                </div>

                                <!-- Administrator Account -->
                                <div>
                                    <h2 class="text-lg font-bold mb-4">Administrator Account</h2>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Admin Name
                                            </label>
                                            <input type="text" wire:model="admin_name" 
                                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                                   placeholder="Administrator" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Admin Email
                                            </label>
                                            <input type="email" wire:model="admin_email" 
                                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                                   placeholder="admin@example.com" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Admin Password
                                            </label>
                                            <input type="password" wire:model="admin_password" 
                                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                                   placeholder="Minimum 8 characters" required>
                                        </div>
                                    </div>
                                </div>

                                <!-- Company Information -->
                                <div>
                                    <h2 class="text-xl font-bold mb-4">Company Information</h2>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Company Name
                                            </label>
                                            <input type="text" wire:model="company_name" 
                                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                                   placeholder="AbbaTextiles Nigeria Limited" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Company Email
                                            </label>
                                            <input type="email" wire:model="company_email" 
                                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                                   placeholder="info@abbatextiles.com">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Company Phone
                                            </label>
                                            <input type="text" wire:model="company_phone" 
                                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                                   placeholder="+234 123 456 7890">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Company Address
                                            </label>
                                            <textarea wire:model="company_address" rows="3"
                                                      class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                                      placeholder="Enter company address"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-gray-200 dark:border-gray-700">
                                    <button type="submit"
                                            wire:loading.attr="disabled"
                                            wire:target="startSetup"
                                            class="flex-1 bg-blue-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                        <span wire:loading.remove wire:target="startSetup">
                                            Start Setup
                                        </span>
                                        <span wire:loading wire:target="startSetup">
                                            Setting up...
                                        </span>
                                    </button>

                                    <button type="button"
                                            wire:click="skipSampleData"
                                            wire:loading.attr="disabled"
                                            wire:target="skipSampleData"
                                            class="flex-1 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 py-3 px-6 rounded-lg font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                        <span wire:loading.remove wire:target="skipSampleData">
                                            Setup Without Sample Data
                                        </span>
                                        <span wire:loading wire:target="skipSampleData">
                                            Setting up...
                                        </span>
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<script>
document.addEventListener('livewire:init', function () {
    Livewire.on('setup-completed', function () {
        setTimeout(function() {
            window.location.href = '{{ route("login") }}';
        }, 3000);
    });
});
</script>