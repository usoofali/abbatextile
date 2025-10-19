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
            <!-- Background -->
            <rect width="300" height="100" fill="#f5f5f5"/>

            <!-- Abstract fabric weave pattern -->
            <g fill="none" stroke="#999" stroke-width="2" opacity="0.3">
                <path d="M0 20 Q150 80 300 20" />
                <path d="M0 40 Q150 100 300 40" />
                <path d="M0 60 Q150 120 300 60" />
            </g>

            <!-- Main Logo Circle -->
            <circle cx="50" cy="50" r="30" fill="#1e3a8a"/>
            <text x="50" y="57" font-size="24" text-anchor="middle" fill="white" font-weight="bold">A</text>

            <!-- Company Name -->
            <text x="100" y="58" font-size="28" fill="#1e3a8a" font-weight="600" font-family="Arial, sans-serif">
                AbbaTextiles
            </text>
        </svg>
    <?php endif; ?>
</div>
<?php /**PATH C:\Users\MSA\Laravel\abbatextile\resources\views/components/app-logo.blade.php ENDPATH**/ ?>