<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="h-screen bg-white dark:bg-zinc-800 flex flex-col lg:flex-row overflow-hidden">
    @persist('toast')
    <flux:toast position="top end" />
    @endpersist
    <flux:sidebar sticky collapsible="mobile"
        class="border-e border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900">
        <div class="absolute inset-0 pointer-events-none opacity-10" style="background-image: url('{{ asset('images/bg.png') }}');
               background-size: 800px;
               background-position: center;
               background-repeat: repeat;">
        </div>

        <div class="relative z-10 flex flex-col h-full min-h-0">
            <div class="shrink-0">
                <flux:sidebar.header>
                    <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                    <flux:sidebar.collapse class="lg:hidden" />
                </flux:sidebar.header>
            </div>

            <div class="flex-1 min-h-0 overflow-y-auto mt-6">
                <flux:sidebar.nav>
                    <flux:sidebar.group :heading="__('Platform')" class="grid">
                        <flux:sidebar.item icon="home" :href="route('dashboard')"
                            :current="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Dashboard') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('E-Commerce')" class="grid">
                        <flux:sidebar.item icon="shopping-bag" :href="route('orders.index')"
                            :current="request()->routeIs('orders.*')" wire:navigate>
                            {{ __('Orders') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="users" :href="route('customers.index')"
                            :current="request()->routeIs('customers.*')" wire:navigate>
                            {{ __('Customers') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="tag" :href="route('discount-groups.index')"
                            :current="request()->routeIs('discount-groups.*')" wire:navigate>
                            {{ __('Discount Groups') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="ticket" :href="route('coupons.index')"
                            :current="request()->routeIs('coupons.*')" wire:navigate>
                            {{ __('Coupons') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('Products')">
                        <flux:sidebar.item icon="layout-grid" :href="route('products.index')"
                            :current="request()->routeIs('products.index')" wire:navigate>
                            {{ __('Products') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="rectangle-group" :href="route('group-products.index')"
                            :current="request()->routeIs('group-products.*')" wire:navigate>
                            {{ __('Group Products') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="shield-check" :href="route('warranty-groups.index')"
                            :current="request()->routeIs('warranty-groups.*')" wire:navigate>
                            {{ __('Warranty Groups') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="tag" :href="route('taxonomies.index')"
                            :current="request()->routeIs('taxonomies.*')" wire:navigate>
                            {{ __('Taxonomies') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('Settings')" class="grid">
                        {{-- <flux:sidebar.item icon="globe-alt" :href="route('zones.index')"
                            :current="request()->routeIs('zones.*')" wire:navigate>
                            {{ __('Zones') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="receipt-percent" :href="route('tax-settings.index')"
                            :current="request()->routeIs('tax-settings.*')" wire:navigate>
                            {{ __('Tax') }}
                        </flux:sidebar.item> --}}
                        {{-- <flux:sidebar.item icon="truck" :href="route('shipping-settings.index')"
                            :current="request()->routeIs('shipping-settings.*')" wire:navigate>
                            {{ __('Shipping') }}
                        </flux:sidebar.item> --}}
                        <flux:sidebar.item icon="globe-europe-africa" :href="route('shipping-cost.index')"
                            :current="request()->routeIs('shipping-cost.*')" wire:navigate>
                            {{ __('Shipping Costs') }}
                        </flux:sidebar.item>
                        {{-- <flux:sidebar.item icon="users" :href="route('settings.show', 'team')"
                            :current="request()->routeIs('settings.show') && request()->route('tab') === 'team'" wire:navigate>
                            {{ __('Team') }}
                        </flux:sidebar.item> --}}
                        <flux:sidebar.item icon="star" :href="route('settings.show', 'popular-products')"
                            :current="request()->routeIs('settings.show') && request()->route('tab') === 'popular-products'" wire:navigate>
                            {{ __('Popular Products') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="truck" :href="route('settings.show', 'dhl')"
                            :current="request()->routeIs('settings.show') && request()->route('tab') === 'dhl'" wire:navigate>
                            {{ __('DHL') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="receipt-percent" :href="route('moneybird.settings')"
                            :current="request()->routeIs('moneybird.*')" wire:navigate>
                            {{ __('Moneybird') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('CMS')" class="grid">
                        <flux:sidebar.item icon="question-mark-circle" :href="route('faq-items.index')"
                            :current="request()->routeIs('faq-items.*')" wire:navigate>
                            {{ __('FAQ Items') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="book-open" :href="route('faq-pages.index')"
                            :current="request()->routeIs('faq-pages.*')" wire:navigate>
                            {{ __('FAQ Pages') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                </flux:sidebar.nav>
            </div>

            <div class="flex flex-col w-full gap-3 pt-3 border-t border-zinc-200 dark:border-zinc-700">
                @if (count(config('app.available_locales')) > 1)
                    <flux:dropdown class="w-full">
                        <flux:button icon:trailing="chevron-down" class="w-full">
                            {{ __(config('app.available_locales')[app()->getLocale()]) }}
                        </flux:button>

                        <flux:menu>
                            <flux:menu.radio.group>
                                @foreach (config('app.available_locales') as $locale => $label)
                                    <flux:menu.item :checked="app()->getLocale() == $locale"
                                        :href="route('lang.switch', $locale)" wire:navigate>
                                        {{ __($label) }}
                                    </flux:menu.item>
                                @endforeach
                            </flux:menu.radio.group>
                        </flux:menu>
                    </flux:dropdown>
                @endif

                <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
            </div>
        </div>
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <flux:dropdown position="top" align="end">
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer" data-test="logout-button">
                        {{ __('Log out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    @fluxScripts
</body>

</html>
