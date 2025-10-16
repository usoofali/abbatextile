<?php if (isset($component)) { $__componentOriginalefa1d248efab4e1ce8988fc029e1702d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalefa1d248efab4e1ce8988fc029e1702d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.layouts.auth.split','data' => ['title' => $title ?? null]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('layouts.auth.split'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($title ?? null)]); ?>
    <?php echo e($slot); ?>

 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalefa1d248efab4e1ce8988fc029e1702d)): ?>
<?php $attributes = $__attributesOriginalefa1d248efab4e1ce8988fc029e1702d; ?>
<?php unset($__attributesOriginalefa1d248efab4e1ce8988fc029e1702d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalefa1d248efab4e1ce8988fc029e1702d)): ?>
<?php $component = $__componentOriginalefa1d248efab4e1ce8988fc029e1702d; ?>
<?php unset($__componentOriginalefa1d248efab4e1ce8988fc029e1702d); ?>
<?php endif; ?>
<?php /**PATH C:\Users\MSA\Laravel\abbatextile\resources\views/components/layouts/auth.blade.php ENDPATH**/ ?>