<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'digits' => 6,
    'name' => 'code',
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'digits' => 6,
    'name' => 'code',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div
    @focus-2fa-auth-code.window="$refs.input1?.focus()"
    @clear-2fa-auth-code.window="clearAll()"
    class="relative"
    x-data="{
        totalDigits: <?php echo \Illuminate\Support\Js::from($digits)->toHtml() ?>,
        digitIndices: <?php echo \Illuminate\Support\Js::from(range(1, $digits))->toHtml() ?>,
        init() {
            $nextTick(() => {
                this.$refs.input1?.focus();
            });
        },
        getInput(index) {
            return this.$refs['input' + index];
        },
        setValue(index, value) {
            this.getInput(index).value = value;
        },
        getCode() {
            return this.digitIndices
                .map(i => this.getInput(i).value)
                .join('');
        },
        updateHiddenField() {
            this.$refs.code.value = this.getCode();
            this.$refs.code.dispatchEvent(new Event('input', { bubbles: true }));
            this.$refs.code.dispatchEvent(new Event('change', { bubbles: true }));
        },
        handleNumberKey(index, key) {
            this.setValue(index, key);

            if (index < this.totalDigits) {
                this.getInput(index + 1).focus();
            }

            $nextTick(() => {
                this.updateHiddenField();
            });
        },
        handleBackspace(index) {
            const currentInput = this.getInput(index);

            if (currentInput.value !== '') {
                currentInput.value = '';
                this.updateHiddenField();
                return;
            }

            if (index <= 1) {
                return;
            }

            const previousInput = this.getInput(index - 1);
    
            previousInput.value = '';
            previousInput.focus();

            this.updateHiddenField();
        },
        handleKeyDown(index, event) {
            const key = event.key;

            if (/^[0-9]$/.test(key)) {
                event.preventDefault();
                this.handleNumberKey(index, key);
                return;
            }

            if (key === 'Backspace') {
                event.preventDefault();
                this.handleBackspace(index);
                return;
            }
        },
        handlePaste(event) {
            event.preventDefault();

            const pastedText = (event.clipboardData || window.clipboardData).getData('text');
            const numericOnly = pastedText.replace(/[^0-9]/g, '');
            const digitsToFill = Math.min(numericOnly.length, this.totalDigits);

            this.digitIndices
                .slice(0, digitsToFill)
                .forEach(index => {
                    this.setValue(index, numericOnly[index - 1]);
                });

            if (numericOnly.length >= this.totalDigits) {
                this.updateHiddenField();
            }
        },
        clearAll() {
            this.digitIndices.forEach(index => {
                this.setValue(index, '');
            });

            this.$refs.code.value = '';
            this.$refs.input1?.focus();
        }
    }"
>
    <div class="flex items-center">
        <?php for($x = 1; $x <= $digits; $x++): ?>
            <input
                x-ref="input<?php echo e($x); ?>"
                type="text"
                inputmode="numeric"
                pattern="[0-9]"
                maxlength="1"
                autocomplete="off"
                @paste="handlePaste"
                @keydown="handleKeyDown(<?php echo e($x); ?>, $event)"
                @focus="$el.select()"
                @input="$el.value = $el.value.replace(/[^0-9]/g, '').slice(0, 1)"
                class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                    'flex size-10 items-center justify-center border border-zinc-300 bg-accent-foreground text-center text-sm font-medium text-accent-content transition-colors focus:border-accent focus:border-2 focus:outline-none focus:relative focus:z-10 dark:border-zinc-700 dark:focus:border-accent',
                    'rounded-l-md' => $x === 1,
                    'rounded-r-md' => $x === $digits,
                    '-ml-px' => $x > 1,
                ]); ?>"
            />
        <?php endfor; ?>
    </div>

    <input
        <?php echo e($attributes->except(['digits'])); ?>

        type="hidden"
        x-ref="code"
        name="<?php echo e($name); ?>"
        minlength="<?php echo e($digits); ?>"
        maxlength="<?php echo e($digits); ?>"
    />
</div>
<?php /**PATH C:\Users\MSA\Laravel\abbatextile\resources\views\components\input-otp.blade.php ENDPATH**/ ?>