<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SetupController extends Controller
{
    public function index()
    {
        // Check if setup is already completed
        if (config('app.setup_completed', false)) {
            return redirect('/');
        }

        return view('setup.index');
    }

    public function checkRequirements()
    {
        $requirements = [
            'php_version' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'mbstring' => extension_loaded('mbstring'),
            'openssl' => extension_loaded('openssl'),
            'tokenizer' => extension_loaded('tokenizer'),
            'xml' => extension_loaded('xml'),
            'ctype' => extension_loaded('ctype'),
            'json' => extension_loaded('json'),
            'bcmath' => extension_loaded('bcmath'),
            'storage_writable' => is_writable(storage_path()),
            'bootstrap_writable' => is_writable(base_path('bootstrap/cache')),
            'env_file_exists' => file_exists(base_path('.env')),
        ];

        return response()->json($requirements);
    }

    public function checkDatabase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'db_host' => 'required',
            'db_port' => 'required',
            'db_name' => 'required',
            'db_username' => 'required',
            'db_password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please fill all database fields'
            ], 422);
        }

        try {
            // Test database connection
            config([
                'database.connections.mysql.host' => $request->db_host,
                'database.connections.mysql.port' => $request->db_port,
                'database.connections.mysql.database' => $request->db_name,
                'database.connections.mysql.username' => $request->db_username,
                'database.connections.mysql.password' => $request->db_password,
            ]);

            DB::connection('mysql')->getPdo();

            return response()->json([
                'success' => true,
                'message' => 'Database connection successful!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database connection failed: ' . $e->getMessage()
            ], 422);
        }
    }

    public function runSetup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'app_name' => 'required',
            'app_url' => 'required|url',
            'db_host' => 'required',
            'db_port' => 'required',
            'db_name' => 'required',
            'db_username' => 'required',
            'admin_name' => 'required',
            'admin_email' => 'required|email',
            'admin_password' => 'required|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please fix the validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Update .env file
            $this->updateEnvironmentFile($request);

            // Clear config cache
            Artisan::call('config:clear');

            // Run migrations
            Artisan::call('migrate', ['--force' => true]);

            // Run seeders
            Artisan::call('db:seed', ['--force' => true]);

            // Create storage link
            Artisan::call('storage:link');

            // Generate application key if not exists
            if (empty(config('app.key'))) {
                Artisan::call('key:generate', ['--force' => true]);
            }

            // Mark setup as completed
            $this->markSetupCompleted();

            return response()->json([
                'success' => true,
                'message' => 'Setup completed successfully! Redirecting...'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Setup failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function updateEnvironmentFile(Request $request)
    {
        $envPath = base_path('.env');
        $envExamplePath = base_path('.env.example');

        if (!file_exists($envPath) && file_exists($envExamplePath)) {
            copy($envExamplePath, $envPath);
        }

        $envContent = file_get_contents($envPath);

        $updates = [
            'APP_NAME' => '"' . addslashes($request->app_name) . '"',
            'APP_ENV' => 'production',
            'APP_KEY' => 'base64:' . base64_encode(Str::random(32)),
            'APP_DEBUG' => 'false',
            'APP_URL' => $request->app_url,
            
            'DB_HOST' => $request->db_host,
            'DB_PORT' => $request->db_port,
            'DB_DATABASE' => $request->db_name,
            'DB_USERNAME' => $request->db_username,
            'DB_PASSWORD' => $request->db_password,

            'SETUP_COMPLETED' => 'true',
        ];

        foreach ($updates as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}={$value}";
            
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$replacement}";
            }
        }

        file_put_contents($envPath, $envContent);
    }


    private function markSetupCompleted()
    {
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);
        
        $envContent = preg_replace(
            '/^SETUP_COMPLETED=.*/m',
            'SETUP_COMPLETED=true',
            $envContent
        );
        
        file_put_contents($envPath, $envContent);
    }
}