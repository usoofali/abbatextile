<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Application Setup</h1>
            <p class="text-gray-600">Welcome! Let's get your application configured for production.</p>
        </div>

        <!-- Progress Steps -->
        <div class="flex justify-center mb-8">
            <div class="flex items-center space-x-4">
                <div class="flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold" id="step-1">1</div>
                    <span class="text-sm mt-1 text-blue-600 font-medium">Requirements</span>
                </div>
                <div class="w-16 h-1 bg-gray-300"></div>
                <div class="flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center font-bold" id="step-2">2</div>
                    <span class="text-sm mt-1 text-gray-500">Database</span>
                </div>
                <div class="w-16 h-1 bg-gray-300"></div>
                <div class="flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center font-bold" id="step-3">3</div>
                    <span class="text-sm mt-1 text-gray-500">Application</span>
                </div>
                <div class="w-16 h-1 bg-gray-300"></div>
                <div class="flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center font-bold" id="step-4">4</div>
                    <span class="text-sm mt-1 text-gray-500">Complete</span>
                </div>
            </div>
        </div>

        <!-- Setup Form -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <!-- Step 1: Requirements Check -->
            <div id="step1-content">
                <h2 class="text-xl font-semibold mb-4">System Requirements Check</h2>
                <div id="requirements-list" class="space-y-3 mb-6">
                    <!-- Requirements will be populated by JavaScript -->
                </div>
                <button onclick="checkRequirements()" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                    <i class="fas fa-sync-alt mr-2"></i>Check Requirements
                </button>
            </div>

            <!-- Step 2: Database Configuration -->
            <div id="step2-content" class="hidden">
                <h2 class="text-xl font-semibold mb-4">Database Configuration</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Database Host</label>
                        <input type="text" id="db_host" value="localhost" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Database Port</label>
                        <input type="text" id="db_port" value="3306" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Database Name</label>
                        <input type="text" id="db_name" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Database Username</label>
                        <input type="text" id="db_username" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Database Password</label>
                        <input type="password" id="db_password" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div id="db-check-result" class="mb-4"></div>
                <div class="flex space-x-3">
                    <button onclick="showStep(1)" class="flex-1 bg-gray-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-gray-600 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Back
                    </button>
                    <button onclick="testDatabaseConnection()" class="flex-1 bg-blue-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                        <i class="fas fa-database mr-2"></i>Test Connection
                    </button>
                </div>
            </div>

            <!-- Step 3: Application Configuration -->
            <div id="step3-content" class="hidden">
                <h2 class="text-xl font-semibold mb-4">Application Configuration</h2>
                <div class="space-y-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Application Name</label>
                            <input type="text" id="app_name" value="AbbaTextiles" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Application URL</label>
                            <input type="url" id="app_url" placeholder="https://yourdomain.com" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="border-t pt-4">
                        <h3 class="text-lg font-medium mb-3">Admin Account</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Admin Name</label>
                                <input type="text" id="admin_name" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Admin Email</label>
                                <input type="email" id="admin_email" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Admin Password</label>
                                <input type="password" id="admin_password" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <button onclick="showStep(2)" class="flex-1 bg-gray-500 text-white py-3 px-4 rounded-lg font-semibold hover:bg-gray-600 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Back
                    </button>
                    <button onclick="runSetup()" class="flex-1 bg-green-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                        <i class="fas fa-play mr-2"></i>Run Setup
                    </button>
                </div>
            </div>

            <!-- Step 4: Completion -->
            <div id="step4-content" class="hidden text-center">
                <div class="text-green-500 text-6xl mb-4">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Setup Complete!</h2>
                <p class="text-gray-600 mb-6">Your application has been successfully configured and is ready to use.</p>
                <a href="/" class="inline-block bg-blue-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                    <i class="fas fa-rocket mr-2"></i>Launch Application
                </a>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div id="loading-overlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
            <div class="bg-white rounded-lg p-6 text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <p class="text-gray-700" id="loading-message">Processing...</p>
            </div>
        </div>
    </div>

    <script>
        let currentStep = 1;

        function showStep(step) {
            currentStep = step;
            
            // Update progress steps
            for (let i = 1; i <= 4; i++) {
                const stepElement = document.getElementById(`step-${i}`);
                if (i < step) {
                    stepElement.className = 'w-10 h-10 rounded-full bg-green-500 text-white flex items-center justify-center font-bold';
                } else if (i === step) {
                    stepElement.className = 'w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold';
                } else {
                    stepElement.className = 'w-10 h-10 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center font-bold';
                }
            }

            // Show/hide content
            document.getElementById('step1-content').classList.toggle('hidden', step !== 1);
            document.getElementById('step2-content').classList.toggle('hidden', step !== 2);
            document.getElementById('step3-content').classList.toggle('hidden', step !== 3);
            document.getElementById('step4-content').classList.toggle('hidden', step !== 4);
        }

        function showLoading(message = 'Processing...') {
            document.getElementById('loading-message').textContent = message;
            document.getElementById('loading-overlay').classList.remove('hidden');
        }

        function hideLoading() {
            document.getElementById('loading-overlay').classList.add('hidden');
        }

        async function checkRequirements() {
            showLoading('Checking system requirements...');
            
            try {
                const response = await fetch('/setup/requirements');
                const requirements = await response.json();
                
                const requirementsList = document.getElementById('requirements-list');
                requirementsList.innerHTML = '';
                
                let allPassed = true;
                
                for (const [requirement, passed] of Object.entries(requirements)) {
                    const requirementElement = document.createElement('div');
                    requirementElement.className = `flex items-center justify-between p-3 rounded-lg ${passed ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'}`;
                    
                    const requirementName = requirement.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    
                    requirementElement.innerHTML = `
                        <span class="font-medium ${passed ? 'text-green-800' : 'text-red-800'}">${requirementName}</span>
                        <span class="${passed ? 'text-green-600' : 'text-red-600'}">
                            <i class="fas fa-${passed ? 'check' : 'times'}"></i>
                        </span>
                    `;
                    
                    requirementsList.appendChild(requirementElement);
                    
                    if (!passed) allPassed = false;
                }
                
                if (allPassed) {
                    setTimeout(() => showStep(2), 1000);
                }
                
            } catch (error) {
                alert('Error checking requirements: ' + error.message);
            } finally {
                hideLoading();
            }
        }

        async function testDatabaseConnection() {
            const dbConfig = {
                db_host: document.getElementById('db_host').value,
                db_port: document.getElementById('db_port').value,
                db_name: document.getElementById('db_name').value,
                db_username: document.getElementById('db_username').value,
                db_password: document.getElementById('db_password').value,
            };

            showLoading('Testing database connection...');

            try {
                const response = await fetch('/setup/database-check', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(dbConfig)
                });

                const result = await response.json();
                
                const resultElement = document.getElementById('db-check-result');
                if (result.success) {
                    resultElement.innerHTML = `
                        <div class="bg-green-50 border border-green-200 text-green-800 p-3 rounded-lg">
                            <i class="fas fa-check-circle mr-2"></i>${result.message}
                        </div>
                    `;
                    setTimeout(() => showStep(3), 1000);
                } else {
                    resultElement.innerHTML = `
                        <div class="bg-red-50 border border-red-200 text-red-800 p-3 rounded-lg">
                            <i class="fas fa-times-circle mr-2"></i>${result.message}
                        </div>
                    `;
                }

            } catch (error) {
                alert('Error testing database connection: ' + error.message);
            } finally {
                hideLoading();
            }
        }

        async function runSetup() {
            const setupData = {
                app_name: document.getElementById('app_name').value,
                app_url: document.getElementById('app_url').value,
                db_host: document.getElementById('db_host').value,
                db_port: document.getElementById('db_port').value,
                db_name: document.getElementById('db_name').value,
                db_username: document.getElementById('db_username').value,
                db_password: document.getElementById('db_password').value,
                admin_name: document.getElementById('admin_name').value,
                admin_email: document.getElementById('admin_email').value,
                admin_password: document.getElementById('admin_password').value,
            };

            showLoading('Configuring your application... This may take a few moments.');

            try {
                const response = await fetch('/setup/run', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(setupData)
                });

                const result = await response.json();
                
                if (result.success) {
                    showStep(4);
                } else {
                    alert('Setup failed: ' + result.message);
                    if (result.errors) {
                        console.error('Validation errors:', result.errors);
                    }
                }

            } catch (error) {
                alert('Error during setup: ' + error.message);
            } finally {
                hideLoading();
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            checkRequirements();
        });
    </script>
</body>
</html>