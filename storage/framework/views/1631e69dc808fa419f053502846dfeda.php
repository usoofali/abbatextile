<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" class="dark">
    <head>
        <?php echo $__env->make('partials.head', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </head>
    <body class="min-h-screen bg-neutral-100 antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="bg-muted flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-md flex-col gap-6">
                <a href="<?php echo e(route('home')); ?>" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <!-- <span class="flex items-center justify-center"> -->
                        <?php if (isset($component)) { $__componentOriginalab7941ded31f89d628e7a7441c552025 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalab7941ded31f89d628e7a7441c552025 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.app-auth-logo','data' => ['class' => '']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-auth-logo'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => '']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalab7941ded31f89d628e7a7441c552025)): ?>
<?php $attributes = $__attributesOriginalab7941ded31f89d628e7a7441c552025; ?>
<?php unset($__attributesOriginalab7941ded31f89d628e7a7441c552025); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalab7941ded31f89d628e7a7441c552025)): ?>
<?php $component = $__componentOriginalab7941ded31f89d628e7a7441c552025; ?>
<?php unset($__componentOriginalab7941ded31f89d628e7a7441c552025); ?>
<?php endif; ?>
                    <!-- </span> -->

                    <span class="sr-only"><?php echo e(config('app.name', 'Laravel')); ?></span>
                </a>

                <div class="flex flex-col gap-6">
                    <div class="rounded-xl border bg-white dark:bg-stone-950 dark:border-stone-800 text-stone-800 shadow-xs">
                        <div class="px-10 py-8"><?php echo e($slot); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php app('livewire')->forceAssetInjection(); ?>
<?php echo app('flux')->scripts(); ?>

    </body>
</html>
<?php /**PATH C:\Users\MSA\Laravel\abbatextile\resources\views/components/layouts/auth/card.blade.php ENDPATH**/ ?>