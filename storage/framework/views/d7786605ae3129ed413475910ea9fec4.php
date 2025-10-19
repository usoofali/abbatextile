<?php
    use App\Models\CompanySetting;
    use Illuminate\Support\Facades\Storage;
    
    $companySettings = CompanySetting::first();
    $companyName = $companySettings->company_name ?? 'Laravel Starter Kit';
    $logoPath = $companySettings->logo_path ?? null;
?>

<div class="flex items-center justify-center rounded-xl bg-accent-content overflow-hidden">
    <?php
        $logoDisplayed = false;
    ?>

    <?php if($logoPath): ?>
        <?php
            try {
                $logoExists = Storage::exists($logoPath);
            } catch (Exception $e) {
                $logoExists = false;
            }
        ?>
        
        <?php if($logoExists): ?>
            <img 
                src="<?php echo e(Storage::url($logoPath)); ?>" 
                alt="<?php echo e($companyName ?? 'Company Logo'); ?>"
                class="size-full object-cover"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
            >
            <?php $logoDisplayed = true; ?>
        <?php endif; ?>
    <?php endif; ?>

    
    <?php if(!$logoDisplayed): ?>
        <svg 
        xmlns="http://www.w3.org/2000/svg" 
        viewBox="0 0 300 100" 
        class="size-full"
        preserveAspectRatio="xMidYMid meet"
    >
        <!-- Background with subtle gradient -->
        <rect width="300" height="100" fill="url(#gradient)"/>
        
        <!-- Gradient definition -->
        <defs>
            <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#f8f9fa"/>
                <stop offset="100%" stop-color="#e9ecef"/>
            </linearGradient>
        </defs>

        <!-- Modern geometric textile pattern -->
        <g fill="none" stroke="#4a6572" stroke-width="1.5" opacity="0.2">
            <rect x="10" y="15" width="20" height="20" rx="3"/>
            <rect x="40" y="15" width="20" height="20" rx="3"/>
            <rect x="70" y="15" width="20" height="20" rx="3"/>
            <rect x="25" y="40" width="20" height="20" rx="3"/>
            <rect x="55" y="40" width="20" height="20" rx="3"/>
            <rect x="85" y="40" width="20" height="20" rx="3"/>
        </g>

        <!-- Modern logo mark - abstract textile formation -->
        <g fill="#2d4e8a">
            <path d="M45,35 L55,25 L65,35 L55,45 Z"/>
            <circle cx="55" cy="35" r="8" fill="#e74c3c"/>
        </g>

        <!-- Company Name with modern typography -->
        <text x="95" y="42" font-size="20" fill="#2c3e50" font-weight="700" font-family="'Segoe UI', Tahoma, Geneva, Verdana, sans-serif" letter-spacing="1">
            ABBA
        </text>
        <text x="95" y="62" font-size="14" fill="#7f8c8d" font-weight="500" font-family="'Segoe UI', Tahoma, Geneva, Verdana, sans-serif" letter-spacing="2">
            TEXTILES
        </text>

        <!-- Subtle accent line -->
        <line x1="95" y1="65" x2="180" y2="65" stroke="#e74c3c" stroke-width="2"/>
    </svg>
    <?php endif; ?>
</div>
<?php /**PATH C:\Users\MSA\Laravel\abbatextile\resources\views\components\app-logo.blade.php ENDPATH**/ ?>