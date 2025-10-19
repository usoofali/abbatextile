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

?>

<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-900 dark:to-gray-800">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="flex justify-center mb-4">
                    <?php if (isset($component)) { $__componentOriginal7b17d80ff7900603fe9e5f0b453cc7c3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7b17d80ff7900603fe9e5f0b453cc7c3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.app-logo','data' => ['class' => 'w-20 h-20']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'w-20 h-20']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7b17d80ff7900603fe9e5f0b453cc7c3)): ?>
<?php $attributes = $__attributesOriginal7b17d80ff7900603fe9e5f0b453cc7c3; ?>
<?php unset($__attributesOriginal7b17d80ff7900603fe9e5f0b453cc7c3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7b17d80ff7900603fe9e5f0b453cc7c3)): ?>
<?php $component = $__componentOriginal7b17d80ff7900603fe9e5f0b453cc7c3; ?>
<?php unset($__componentOriginal7b17d80ff7900603fe9e5f0b453cc7c3); ?>
<?php endif; ?>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                    <?php echo e($isSetupComplete ? 'Setup Complete!' : 'Application Setup'); ?>

                </h1>
                <p class="text-lg text-gray-600 dark:text-gray-400 mt-2">
                    <?php echo e($isSetupComplete ? 'Your application is ready to use' : 'Configure your AbbaTextiles POS system'); ?>

                </p>
            </div>

            <?php if($isSetupComplete): ?>
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
                        <a href="<?php echo e(route('login')); ?>" class="inline-flex items-center px-6 py-3 bg-blue-600 border border-transparent rounded-md font-semibold text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                            Go to Login
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Setup Form -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg overflow-hidden">
                    <!-- Progress Bar -->
                    <?php if($isProcessing): ?>
                        <div class="bg-blue-50 dark:bg-blue-900/20 px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-blue-600 dark:text-blue-400">
                                    Setting up application...
                                </span>
                                <span class="text-sm font-medium text-blue-600 dark:text-blue-400">
                                    <?php echo e(number_format($progress, 1)); ?>%
                                </span>
                            </div>
                            <div class="w-full bg-blue-200 dark:bg-blue-800 rounded-full h-2">
                                <div class="bg-blue-600 dark:bg-blue-400 h-2 rounded-full transition-all duration-300" 
                                     style="width: <?php echo e($progress); ?>%"></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Setup Steps -->
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                            <?php $__currentLoopData = $setupSteps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="flex items-center space-x-3 p-3 rounded-lg <?php echo e($step['active'] ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-gray-50 dark:bg-gray-700'); ?>">
                                    <div class="flex-shrink-0">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium
                                            <?php echo e($step['completed'] ? 'bg-green-500 text-white' : 
                                               ($step['active'] ? 'bg-blue-500 text-white' : 'bg-gray-300 text-gray-600 dark:bg-gray-600 dark:text-gray-400')); ?>">
                                            <?php if($step['completed']): ?>
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                </svg>
                                            <?php else: ?>
                                                <?php echo e(array_search($key, array_keys($setupSteps)) + 1); ?>

                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <span class="text-sm font-medium <?php echo e($step['active'] ? 'text-blue-600 dark:text-blue-400' : 'text-gray-900 dark:text-white'); ?>">
                                            <?php echo e($step['title']); ?>

                                        </span>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            <?php echo e($step['description']); ?>

                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>

                        <?php if($isProcessing): ?>
                            <!-- Processing Logs -->
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-6">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-3">
                                    Setup Progress
                                </h3>
                                <div class="space-y-2 max-h-60 overflow-y-auto">
                                    <?php $__currentLoopData = $logs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <div class="flex items-start space-x-3">
                                            <span class="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0">
                                                <?php echo e($log['timestamp']); ?>

                                            </span>
                                            <span class="text-xs flex-1 <?php echo e($log['level'] === 'error' ? 'text-red-600 dark:text-red-400' : 
                                                ($log['level'] === 'success' ? 'text-green-600 dark:text-green-400' : 
                                                ($log['level'] === 'warning' ? 'text-yellow-600 dark:text-yellow-400' : 'text-gray-600 dark:text-gray-400'))); ?>">
                                                <?php echo e($log['message']); ?>

                                            </span>
                                        </div>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </div>
                            </div>
                        <?php else: ?>
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
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('livewire:init', function () {
    Livewire.on('setup-completed', function () {
        setTimeout(function() {
            window.location.href = '<?php echo e(route("login")); ?>';
        }, 3000);
    });
});
</script><?php /**PATH C:\Users\MSA\Laravel\abbatextile\resources\views\livewire\setup\configuration.blade.php ENDPATH**/ ?>