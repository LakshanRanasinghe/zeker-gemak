<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="h-full overflow-y-auto flex-1">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>