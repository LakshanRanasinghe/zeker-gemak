@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand {{ $attributes }}>
        <x-slot name="logo" class="w-full h-auto">
            <x-app-logo-icon class="text-white dark:text-black" />
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand {{ $attributes }}>
        <x-slot name="logo" class="w-full h-auto">
            <x-app-logo-icon class="text-white dark:text-black" />
        </x-slot>
    </flux:brand>
@endif
