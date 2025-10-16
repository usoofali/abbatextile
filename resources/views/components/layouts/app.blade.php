<x-layouts.app.sidebar :title="$title ?? null">
    <flux:main>
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
            {{ $slot }}
        </div>
    </flux:main>
</x-layouts.app.sidebar>
