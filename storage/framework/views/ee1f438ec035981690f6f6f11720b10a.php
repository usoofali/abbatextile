<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title><?php echo e($title ?? config('app.name')); ?></title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<!-- Add this to your app.blade.php layout file -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

<?php if(file_exists(public_path('build/manifest.json'))): ?>
    <link rel="stylesheet" href="<?php echo e(asset('build/assets/app-CXuiAMJ-.css')); ?>">
    <script type="module" src="<?php echo e(asset('build/assets/app-l0sNRNKZ.js')); ?>"></script>
<?php else: ?>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
<?php endif; ?>
<?php echo app('flux')->fluxAppearance(); ?>

<?php /**PATH C:\Users\MSA\Laravel\abbatextile\resources\views/partials/head.blade.php ENDPATH**/ ?>