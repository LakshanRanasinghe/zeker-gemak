<?php
// Check if this file is run directly or included in another page
$is_direct = (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME']));
if ($is_direct):
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Zeker Gemak - Modern Shopping Header</title>
        <meta name="description"
            content="Zeker Gemak header styled with Tailwind CSS, featuring responsive design, Google Fonts, and custom SVG icons.">
        <!-- Tailwind CSS Play CDN -->
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        fontFamily: {
                            sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                            dm: ['"DM Sans"', 'sans-serif'],
                        },
                        screens: {
                            'xxl': '1670px',
                        }
                    }
                }
            }
        </script>
        <!-- Google Fonts: Plus Jakarta Sans & DM Sans -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link
            href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800&display=swap"
            rel="stylesheet">
        <style>
            body {
                font-family: 'Plus Jakarta Sans', sans-serif;
                background-color: #F8FAFC;
            }

            .scrollbar-none::-webkit-scrollbar {
                display: none;
            }

            .scrollbar-none {
                -ms-overflow-style: none;
                scrollbar-width: none;
            }
        </style>
    </head>

    <body class="min-h-screen flex flex-col">
    <?php endif; ?>

    <!-- HEADER COMPONENT -->
    <header>
        <div class="w-full flex flex-col justify-start items-start shadow-sm select-none">
            <!-- Top Bar -->
            <div
                class="w-full lg:px-[156px] px-6 py-4 bg-white flex justify-between items-center border-b border-slate-100/60 lg:border-b-0">
                <!-- Logo & Mobile Controls Wrapper -->
                <div class="w-full lg:w-auto flex justify-between items-center lg:block">
                    <!-- Logo -->
                    <a href="/" class="flex-shrink-0 hover:opacity-90 transition-opacity">
                        <img class="w-[140px] h-[33px] md:w-[170px] md:h-[40px] lg:w-[204px] lg:h-[48px] object-contain"
                            src="assets/images/zeker-gemak-logo.png" alt="ZekerGemak Logo" />
                    </a>

                    <!-- Mobile/Tablet Controls -->
                    <div class="flex items-center gap-4 lg:hidden">
                        <!-- Language Switcher (mobile) -->
                        <div class="flex items-center gap-1 cursor-pointer hover:opacity-85 transition-opacity"
                            id="mobile-lang-btn">
                            <img class="w-[20px] h-[13px] object-cover rounded-[2px]" src="assets/icons/en.png"
                                alt="English Flag" />
                            <span class="text-[#4D5964] text-[14px] font-dm font-normal">En</span>
                        </div>

                        <!-- Cart (mobile) -->
                        <a href="#" class="relative p-1 hover:opacity-85 transition-opacity">
                            <svg class="w-6 h-6 text-[#2C3642]" fill="none" stroke="currentColor" stroke-width="1.8"
                                viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75a3 3 0 00-3-3m-9.75 0h9.75m-9.75 0a1.5 1.5 0 01-1.5-1.5V6.75m11.25 7.5a1.5 1.5 0 001.5-1.5V6.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z">
                                </path>
                            </svg>
                            <!-- Cart Badge -->
                            <span
                                class="absolute -top-1 -right-1 bg-[#E9A821] text-white text-[10px] font-sans font-bold w-4 h-4 rounded-full flex items-center justify-center">2</span>
                        </a>
                    </div>
                </div>

                <!-- Search Input (Desktop only) -->
                <div
                    class="hidden lg:flex w-full lg:max-w-[480px] flex-1 h-[40px] px-3 bg-white rounded-xl border border-[#D0D7DD] justify-start items-center gap-2 transition-all duration-200 focus-within:border-[#6F7983] focus-within:shadow-sm">
                    <svg class="w-[18px] h-[18px] text-[#6F7983] flex-shrink-0" fill="none" stroke="currentColor"
                        stroke-width="1.8" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <input type="text" placeholder="Search for products..."
                        class="w-full bg-transparent border-none outline-none text-[#6F7983] text-[14px] font-sans font-normal placeholder-[#6F7983] focus:ring-0" />
                </div>

                <!-- Action Items (Desktop only) -->
                <div class="hidden lg:flex items-center gap-6 flex-shrink-0">
                    <!-- Language Switcher -->
                    <div class="flex items-center gap-1 cursor-pointer hover:opacity-80 transition-opacity">
                        <img class="w-[24px] h-[16px] object-cover rounded-[2px]" src="assets/icons/en.png"
                            alt="English Flag" />
                        <span
                            class="text-[#4D5964] text-[16px] font-dm font-normal leading-[20.80px] break-words">En</span>
                        <svg class="w-4 h-4 text-[#6F7983]" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>

                    <!-- Need Help -->
                    <a href="tel:#"
                        class="flex items-center gap-1.5 cursor-pointer hover:opacity-80 transition-opacity">
                        <svg class="w-5 h-5 text-[#2C3642] flex-shrink-0" fill="none" stroke="currentColor"
                            stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z">
                            </path>
                        </svg>
                        <span
                            class="text-[#2C3642] text-[16px] font-sans font-normal leading-[20.80px] break-words">Need
                            help?</span>
                    </a>

                    <!-- Cart -->
                    <a href="#" class="flex items-center gap-1.5 cursor-pointer hover:opacity-80 transition-opacity">
                        <svg class="w-5 h-5 text-[#2C3642] flex-shrink-0" fill="none" stroke="currentColor"
                            stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75a3 3 0 00-3-3m-9.75 0h9.75m-9.75 0a1.5 1.5 0 01-1.5-1.5V6.75m11.25 7.5a1.5 1.5 0 001.5-1.5V6.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z">
                            </path>
                        </svg>
                        <span
                            class="text-[#2C3642] text-[16px] font-sans font-normal leading-[20.80px] break-words">Cart</span>
                    </a>
                </div>
            </div>

            <!-- Search Bar Wrapper (Mobile/Tablet only) -->
            <div class="w-full lg:hidden px-6 pb-4 bg-white border-b border-slate-100/60">
                <div
                    class="w-full h-[40px] px-3 bg-[#F8FAFC] rounded-xl border border-[#D0D7DD] flex justify-start items-center gap-2 transition-all duration-200 focus-within:bg-white focus-within:border-[#6F7983] focus-within:shadow-sm">
                    <svg class="w-[18px] h-[18px] text-[#6F7983] flex-shrink-0" fill="none" stroke="currentColor"
                        stroke-width="1.8" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <input type="text" placeholder="Search for products..."
                        class="w-full bg-transparent border-none outline-none text-[#6F7983] text-[14px] font-sans font-normal placeholder-[#6F7983] focus:ring-0" />
                </div>
            </div>

            <!-- Navigation Bar -->
            <div
                class="w-full h-auto lg:h-[56px] lg:px-[156px] px-6 py-2.5 lg:py-0 bg-[#262F40] flex justify-start items-center relative">
                <!-- Hamburger Menu Toggle (Mobile/Tablet only) -->
                <button id="mobile-menu-toggle"
                    class="flex items-center gap-2 text-white hover:text-[#E9A821] transition-colors font-sans font-semibold text-[15px] lg:hidden flex-shrink-0 focus:outline-none z-10 ml-auto"
                    aria-label="Toggle Menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                    <span>Menu</span>
                </button>

                <!-- Scrollable Categories (Desktop only) -->
                <div
                    class="hidden lg:flex flex-1 overflow-x-auto lg:overflow-visible scrollbar-none justify-start items-center gap-6 whitespace-nowrap">
                    <a href="#"
                        class="text-white hover:text-slate-300 transition-colors text-[15px] lg:text-[16px] font-sans font-light leading-[24px] break-words">Ergonomisch</a>
                    <a href="#"
                        class="text-white hover:text-slate-300 transition-colors text-[15px] lg:text-[16px] font-sans font-light leading-[24px] break-words">Hygiëne</a>
                    <a href="#"
                        class="text-white hover:text-slate-300 transition-colors text-[15px] lg:text-[16px] font-sans font-light leading-[24px] break-words">Orthopedisch</a>
                    <a href="#"
                        class="text-white hover:text-slate-300 transition-colors text-[15px] lg:text-[16px] font-sans font-light leading-[24px] break-words">Mobiliteit</a>
                    <a href="#"
                        class="text-white hover:text-slate-300 transition-colors text-[15px] lg:text-[16px] font-sans font-light leading-[24px] break-words">Hang
                        & Sluitwerk</a>
                    <a href="#"
                        class="text-white hover:text-slate-300 transition-colors text-[15px] lg:text-[16px] font-sans font-light leading-[24px] break-words">Arm-
                        en fiets trainers</a>
                    <a href="#"
                        class="text-white hover:text-slate-300 transition-colors text-[15px] lg:text-[16px] font-sans font-light leading-[24px] break-words">Verzorging</a>
                    <a href="#"
                        class="text-white hover:text-slate-300 transition-colors text-[15px] lg:text-[16px] font-sans font-light leading-[24px] break-words">Overig</a>
                </div>
            </div>
        </div>
    </header> <!-- Mobile Drawer Menu Overlay -->
    <div id="mobile-menu-drawer"
        class="fixed inset-0 z-50 bg-[#2C3642]/60 backdrop-blur-sm opacity-0 pointer-events-none transition-all duration-300">
        <!-- Drawer content -->
        <div class="fixed top-0 right-0 bottom-0 w-[300px] bg-white shadow-2xl flex flex-col justify-between p-6 translate-x-full transition-transform duration-300 ease-in-out"
            id="drawer-content">
            <div class="flex flex-col gap-8">
                <!-- Drawer Header -->
                <div class="flex justify-between items-center pb-4 border-b border-slate-100">
                    <span class="text-[#2C3642] text-[20px] font-sans font-bold">Menu</span>
                    <button id="mobile-menu-close" class="p-1 hover:opacity-85 text-[#2C3642]" aria-label="Close Menu">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Categories Links -->
                <nav class="flex flex-col gap-4">
                    <a href="#"
                        class="text-[#2C3642] hover:text-[#E9A821] text-[16px] font-sans font-semibold py-1 transition-colors">Ergonomisch</a>
                    <a href="#"
                        class="text-[#2C3642] hover:text-[#E9A821] text-[16px] font-sans font-semibold py-1 transition-colors">Hygiëne</a>
                    <a href="#"
                        class="text-[#2C3642] hover:text-[#E9A821] text-[16px] font-sans font-semibold py-1 transition-colors">Orthopedisch</a>
                    <a href="#"
                        class="text-[#2C3642] hover:text-[#E9A821] text-[16px] font-sans font-semibold py-1 transition-colors">Mobiliteit</a>
                    <a href="#"
                        class="text-[#2C3642] hover:text-[#E9A821] text-[16px] font-sans font-semibold py-1 transition-colors">Hang
                        & Sluitwerk</a>
                    <a href="#"
                        class="text-[#2C3642] hover:text-[#E9A821] text-[16px] font-sans font-semibold py-1 transition-colors">Arm-
                        en fiets trainers</a>
                    <a href="#"
                        class="text-[#2C3642] hover:text-[#E9A821] text-[16px] font-sans font-semibold py-1 transition-colors">Verzorging</a>
                    <a href="#"
                        class="text-[#2C3642] hover:text-[#E9A821] text-[16px] font-sans font-semibold py-1 transition-colors">Overig</a>
                </nav>
            </div>

            <!-- Drawer Footer (Help support) -->
            <div class="pt-6 border-t border-slate-100 flex flex-col gap-4">
                <a href="tel:#" class="flex items-center gap-2.5 text-[#2C3642] hover:text-[#E9A821] transition-colors">
                    <svg class="w-5 h-5 text-[#2C3642]" fill="none" stroke="currentColor" stroke-width="1.5"
                        viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z">
                        </path>
                    </svg>
                    <span class="text-[16px] font-sans font-medium">Need help?</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Script for mobile drawer controls -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Mobile Menu Controls
            const toggleBtn = document.getElementById('mobile-menu-toggle');
            const closeBtn = document.getElementById('mobile-menu-close');
            const drawer = document.getElementById('mobile-menu-drawer');
            const drawerContent = document.getElementById('drawer-content');

            if (toggleBtn && closeBtn && drawer && drawerContent) {
                const openDrawer = () => {
                    drawer.classList.remove('opacity-0', 'pointer-events-none');
                    drawer.classList.add('opacity-100', 'pointer-events-auto');
                    drawerContent.classList.remove('translate-x-full');
                    drawerContent.classList.add('translate-x-0');
                    document.body.style.overflow = 'hidden';
                };

                const closeDrawer = () => {
                    drawer.classList.remove('opacity-100', 'pointer-events-auto');
                    drawer.classList.add('opacity-0', 'pointer-events-none');
                    drawerContent.classList.remove('translate-x-0');
                    drawerContent.classList.add('translate-x-full');
                    document.body.style.overflow = '';
                };

                toggleBtn.addEventListener('click', openDrawer);
                closeBtn.addEventListener('click', closeDrawer);
                drawer.addEventListener('click', (e) => {
                    if (e.target === drawer) {
                        closeDrawer();
                    }
                });
            }
        });
    </script>

    <?php if ($is_direct): ?>
        <main class="relative w-full">
            <!-- Breadcrumbs Section -->
            <nav class="w-full bg-white border-b border-slate-100 py-4 lg:px-[156px] px-6" aria-label="Breadcrumb">
                <div class="mx-auto flex items-center gap-2 text-sm font-sans font-medium text-[#6F7983]">
                    <a href="index.php" class="hover:text-[#E9A821] transition-colors">Home</a>
                    <svg class="w-3.5 h-3.5 text-[#D0D7DD] flex-shrink-0" fill="none" stroke="currentColor"
                        stroke-width="2.2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                    </svg>
                    <span class="text-[#2C3642] font-semibold">Shop</span>
                </div>
            </nav>

            <!-- Beautiful Shop Hero Section -->
            <section class="w-full bg-[#FAF6EE] relative overflow-hidden border-b border-slate-100">
                <!-- Decorative background blur blobs for premium glassmorphism effect -->
                <div
                    class="absolute right-0 top-0 w-[400px] h-[400px] bg-[#E9A821]/5 rounded-full blur-3xl pointer-events-none">
                </div>
                <div
                    class="absolute left-[10%] bottom-0 w-[300px] h-[300px] bg-[#2C3642]/5 rounded-full blur-2xl pointer-events-none">
                </div>

                <div
                    class="mx-auto lg:px-[156px] px-6 py-12 md:py-16 flex flex-col md:flex-row items-center justify-between gap-8 md:gap-16">
                    <!-- Text Content Wrapper -->
                    <div
                        class="w-full md:w-[500px] flex flex-col justify-start items-start gap-5 md:gap-6 z-10 flex-shrink-0">
                        <span class="text-[#E9A821] text-[14px] font-dm font-bold tracking-widest uppercase">Zeker Gemak
                            Products</span>
                        <h1 class="text-[#2C3642] text-4xl md:text-5xl font-sans font-bold leading-tight break-words">
                            Living independently & <span class="text-[#E9A821]">safely</span>
                        </h1>
                        <p
                            class="text-[#4D5964] text-[16px] md:text-[18px] font-sans font-normal leading-[28px] break-words">
                            Browse our selection of comfortable living aids. We offer reliable adjustments and orthopaedic
                            supports to help make daily activities easier.
                        </p>
                    </div>

                    <!-- Banner Image Wrapper -->
                    <div class="w-full md:w-auto flex justify-center items-center z-10">
                        <div
                            class="relative w-full max-w-[480px] aspect-[16/10] md:w-[480px] md:h-[300px] rounded-2xl overflow-hidden shadow-xl border-4 border-white bg-white flex-shrink-0">
                            <img class="w-full h-full object-cover hover:scale-105 transition-transform duration-700 ease-out"
                                src="assets/images/home/saving-banner.jpg"
                                alt="Zeker Gemak comfortable living aids range" />
                            <!-- Subtle overlay gradient -->
                            <div
                                class="absolute inset-0 bg-gradient-to-tr from-[#2C3642]/20 via-transparent to-transparent pointer-events-none">
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Shop Products Area -->
            <section class="w-full lg:px-[156px] px-6 py-12 bg-[#F8FAFC]">
                <div class="mx-auto flex flex-col lg:flex-row gap-8 items-start">

                    <!-- SIDEBAR FILTERS (Desktop Only) -->
                    <aside class="hidden lg:flex flex-col gap-6 w-[280px] xl:w-[320px] flex-shrink-0 sticky top-6">

                        <!-- Search Widget -->
                        <div
                            class="bg-white p-6 rounded-2xl border border-slate-100 shadow-[0_4px_20px_rgba(44,54,66,0.02)] flex flex-col gap-3">
                            <h3 class="text-[#2C3642] text-base font-bold font-sans">Search Products</h3>
                            <div
                                class="relative w-full h-[44px] px-3 bg-[#F8FAFC] rounded-xl border border-[#D0D7DD] flex justify-start items-center gap-2 focus-within:bg-white focus-within:border-[#6F7983] focus-within:shadow-sm transition-all duration-200">
                                <svg class="w-[18px] h-[18px] text-[#6F7983] flex-shrink-0" fill="none"
                                    stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                <input type="text" placeholder="Search..."
                                    class="w-full bg-transparent border-none outline-none text-[#2C3642] text-[14px] font-sans font-normal placeholder-[#6F7983] focus:ring-0" />
                            </div>
                        </div>

                        <!-- Categories Widget -->
                        <div
                            class="bg-white p-6 rounded-2xl border border-slate-100 shadow-[0_4px_20px_rgba(44,54,66,0.02)] flex flex-col gap-4">
                            <h3 class="text-[#2C3642] text-base font-bold font-sans">Categories</h3>
                            <div class="flex flex-col gap-3">
                                <!-- Category List checkboxes -->
                                <label class="flex items-center justify-between group cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox"
                                            class="w-4 h-4 rounded border-[#D0D7DD] text-[#E9A821] focus:ring-[#E9A821] focus:ring-offset-0 cursor-pointer" />
                                        <span
                                            class="text-sm font-sans font-medium text-[#4D5964] group-hover:text-[#2C3642] transition-colors">Ergonomisch</span>
                                    </div>
                                    <span
                                        class="text-xs font-dm font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-[#6F7983]">12</span>
                                </label>
                                <label class="flex items-center justify-between group cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox"
                                            class="w-4 h-4 rounded border-[#D0D7DD] text-[#E9A821] focus:ring-[#E9A821] focus:ring-offset-0 cursor-pointer" />
                                        <span
                                            class="text-sm font-sans font-medium text-[#4D5964] group-hover:text-[#2C3642] transition-colors">Hygiëne</span>
                                    </div>
                                    <span
                                        class="text-xs font-dm font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-[#6F7983]">6</span>
                                </label>
                                <label class="flex items-center justify-between group cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox"
                                            class="w-4 h-4 rounded border-[#D0D7DD] text-[#E9A821] focus:ring-[#E9A821] focus:ring-offset-0 cursor-pointer" />
                                        <span
                                            class="text-sm font-sans font-medium text-[#4D5964] group-hover:text-[#2C3642] transition-colors">Orthopedisch</span>
                                    </div>
                                    <span
                                        class="text-xs font-dm font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-[#6F7983]">4</span>
                                </label>
                                <label class="flex items-center justify-between group cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox"
                                            class="w-4 h-4 rounded border-[#D0D7DD] text-[#E9A821] focus:ring-[#E9A821] focus:ring-offset-0 cursor-pointer"
                                            checked />
                                        <span
                                            class="text-sm font-sans font-semibold text-[#2C3642] transition-colors">Mobiliteit</span>
                                    </div>
                                    <span
                                        class="text-xs font-dm font-semibold px-2 py-0.5 rounded-full bg-[#E9A821]/10 text-[#E9A821]">8</span>
                                </label>
                                <label class="flex items-center justify-between group cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox"
                                            class="w-4 h-4 rounded border-[#D0D7DD] text-[#E9A821] focus:ring-[#E9A821] focus:ring-offset-0 cursor-pointer" />
                                        <span
                                            class="text-sm font-sans font-medium text-[#4D5964] group-hover:text-[#2C3642] transition-colors">Hang
                                            &amp; Sluitwerk</span>
                                    </div>
                                    <span
                                        class="text-xs font-dm font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-[#6F7983]">15</span>
                                </label>
                                <label class="flex items-center justify-between group cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox"
                                            class="w-4 h-4 rounded border-[#D0D7DD] text-[#E9A821] focus:ring-[#E9A821] focus:ring-offset-0 cursor-pointer" />
                                        <span
                                            class="text-sm font-sans font-medium text-[#4D5964] group-hover:text-[#2C3642] transition-colors">Arm-
                                            en fiets trainers</span>
                                    </div>
                                    <span
                                        class="text-xs font-dm font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-[#6F7983]">3</span>
                                </label>
                                <label class="flex items-center justify-between group cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox"
                                            class="w-4 h-4 rounded border-[#D0D7DD] text-[#E9A821] focus:ring-[#E9A821] focus:ring-offset-0 cursor-pointer" />
                                        <span
                                            class="text-sm font-sans font-medium text-[#4D5964] group-hover:text-[#2C3642] transition-colors">Verzorging</span>
                                    </div>
                                    <span
                                        class="text-xs font-dm font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-[#6F7983]">9</span>
                                </label>
                                <label class="flex items-center justify-between group cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox"
                                            class="w-4 h-4 rounded border-[#D0D7DD] text-[#E9A821] focus:ring-[#E9A821] focus:ring-offset-0 cursor-pointer" />
                                        <span
                                            class="text-sm font-sans font-medium text-[#4D5964] group-hover:text-[#2C3642] transition-colors">Overig</span>
                                    </div>
                                    <span
                                        class="text-xs font-dm font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-[#6F7983]">2</span>
                                </label>
                            </div>
                        </div>

                        <!-- Price Range Widget -->
                        <div
                            class="bg-white p-6 rounded-2xl border border-slate-100 shadow-[0_4px_20px_rgba(44,54,66,0.02)] flex flex-col gap-5">
                            <h3 class="text-[#2C3642] text-base font-bold font-sans">Price</h3>
                            <div class="flex items-center gap-3">
                                <div class="flex-1">
                                    <label class="text-xs font-dm font-bold text-[#6F7983] block mb-1">Min</label>
                                    <div class="relative">
                                        <span
                                            class="absolute left-3 top-1/2 -translate-y-1/2 text-[#6F7983] text-sm">$</span>
                                        <input type="number" value="10"
                                            class="w-full pl-7 pr-3 py-2 bg-[#F8FAFC] border border-[#D0D7DD] rounded-xl text-sm text-[#2C3642] focus:bg-white focus:border-[#E9A821] focus:outline-none focus:ring-0 transition-colors" />
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <label class="text-xs font-dm font-bold text-[#6F7983] block mb-1">Max</label>
                                    <div class="relative">
                                        <span
                                            class="absolute left-3 top-1/2 -translate-y-1/2 text-[#6F7983] text-sm">$</span>
                                        <input type="number" value="150"
                                            class="w-full pl-7 pr-3 py-2 bg-[#F8FAFC] border border-[#D0D7DD] rounded-xl text-sm text-[#2C3642] focus:bg-white focus:border-[#E9A821] focus:outline-none focus:ring-0 transition-colors" />
                                    </div>
                                </div>
                            </div>
                            <!-- Visual Track Range -->
                            <div class="relative pt-2 pb-1 px-1">
                                <div class="h-1 bg-slate-100 rounded-full w-full"></div>
                                <div
                                    class="absolute h-1 bg-[#E9A821] rounded-full left-1/6 right-1/4 top-1/2 -translate-y-1/2">
                                </div>
                                <div
                                    class="absolute w-[18px] h-[18px] bg-white border-[3px] border-[#E9A821] rounded-full shadow-md left-1/6 top-1/2 -translate-y-1/2 -ml-2.5 cursor-pointer hover:scale-110 transition-transform">
                                </div>
                                <div
                                    class="absolute w-[18px] h-[18px] bg-white border-[3px] border-[#E9A821] rounded-full shadow-md right-1/4 top-1/2 -translate-y-1/2 -ml-2.5 cursor-pointer hover:scale-110 transition-transform">
                                </div>
                            </div>
                        </div>

                        <!-- Status / Availability Widget -->
                        <div
                            class="bg-white p-6 rounded-2xl border border-slate-100 shadow-[0_4px_20px_rgba(44,54,66,0.02)] flex flex-col gap-4">
                            <h3 class="text-[#2C3642] text-base font-bold font-sans">Availability</h3>
                            <div class="flex flex-col gap-3">
                                <label class="flex items-center gap-3 cursor-pointer group">
                                    <input type="checkbox"
                                        class="w-4 h-4 rounded border-[#D0D7DD] text-[#E9A821] focus:ring-[#E9A821] focus:ring-offset-0 cursor-pointer"
                                        checked />
                                    <span
                                        class="text-sm font-sans font-medium text-[#4D5964] group-hover:text-[#2C3642] transition-colors">In
                                        Stock</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer group">
                                    <input type="checkbox"
                                        class="w-4 h-4 rounded border-[#D0D7DD] text-[#E9A821] focus:ring-[#E9A821] focus:ring-offset-0 cursor-pointer" />
                                    <span
                                        class="text-sm font-sans font-medium text-[#4D5964] group-hover:text-[#2C3642] transition-colors">On
                                        Sale</span>
                                </label>
                            </div>
                        </div>

                        <!-- Reset Filters -->
                        <button
                            class="w-full py-3 bg-transparent hover:bg-slate-50 border border-dashed border-[#D0D7DD] hover:border-[#E9A821] hover:text-[#E9A821] text-[#6F7983] font-sans font-bold text-sm rounded-xl transition-all duration-200">
                            Reset All Filters
                        </button>
                    </aside>

                    <!-- PRODUCTS AREA (Filters Bar & Grid) -->
                    <div class="flex-1 w-full">

                        <!-- Controls Bar -->
                        <div
                            class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-4 bg-white px-6 py-4 rounded-2xl border border-slate-100 shadow-[0_4px_20px_rgba(44,54,66,0.02)] mb-8">
                            <div class="text-[#4D5964] text-[15px] font-sans font-medium flex items-center">
                                Showing <span class="text-[#2C3642] font-bold mx-1">1-6</span> of <span
                                    class="text-[#2C3642] font-bold mx-1">6</span> products
                            </div>

                            <div class="flex items-center justify-between sm:justify-end gap-3">
                                <!-- Mobile filter toggle button (hidden on lg+) -->
                                <button id="mobile-filter-toggle"
                                    class="flex lg:hidden items-center gap-2 px-4 h-10 border border-[#D0D7DD] hover:border-[#6F7983] rounded-xl text-[14px] text-[#4D5964] font-sans font-semibold transition-colors bg-white">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                        viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z">
                                        </path>
                                    </svg>
                                    <span>Filters</span>
                                </button>

                                <!-- Sort Dropdown -->
                                <div class="flex items-center gap-2 flex-1 sm:flex-initial">
                                    <span
                                        class="text-sm font-sans font-medium text-[#6F7983] hidden sm:inline whitespace-nowrap">Sort
                                        by:</span>
                                    <div class="relative flex-1 sm:flex-initial">
                                        <select
                                            class="appearance-none w-full sm:w-[200px] h-10 bg-[#F8FAFC] hover:bg-slate-100/60 border border-[#D0D7DD] focus:border-[#6F7983] text-[#2C3642] font-sans font-bold text-[14px] rounded-xl px-4 pr-10 focus:outline-none focus:ring-0 cursor-pointer transition-colors">
                                            <option>Popularity</option>
                                            <option>Price: Low to High</option>
                                            <option>Price: High to Low</option>
                                            <option>Newest Items</option>
                                        </select>
                                        <!-- Custom dropdown arrow -->
                                        <div
                                            class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-[#6F7983]">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                                viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7">
                                                </path>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Products Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">

                            <!-- Product Card 1 -->
                            <article
                                class="bg-[#F3F5F7] rounded-xl border border-[#E9EEF4] shadow-[4px_6px_20px_rgba(109,109,120,0.04)] overflow-hidden flex flex-col group cursor-pointer hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                                <div
                                    class="relative h-[280px] flex items-center justify-center bg-[#F3F5F7] overflow-hidden">
                                    <img src="assets/images/products/232af3f7e55772773e15990eccf0b2880a965307.png"
                                        alt="JNF 16.312 Schuifdeurtrekring ovaal 154 x 29 mm RVS"
                                        class="w-[500px] h-[500px] max-w-full max-h-full object-contain group-hover:scale-105 transition-transform duration-300 mix-blend-multiply" />
                                    <span
                                        class="absolute top-5 left-5 px-3 py-1.5 bg-[#2C3642] text-white text-[16px] font-semibold capitalize leading-[17.6px] rounded">Sale</span>
                                    <div
                                        class="absolute bottom-0 left-0 right-0 px-4 pb-4 translate-y-full opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300 ease-out">
                                        <button
                                            class="w-full h-12 bg-[#F5A623] hover:bg-[#d99012] text-white text-[16px] font-bold leading-[20.8px] rounded-xl flex items-center justify-center gap-2 transition-colors duration-200">
                                            Add to Cart
                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                    d="M2 2L3.84438 2.31922L4.69829 12.4926C4.73109 12.8929 4.91363 13.2661 5.20947 13.5378C5.50532 13.8095 5.89273 13.9597 6.29439 13.9583H15.9685C16.3531 13.9588 16.7249 13.8203 17.0156 13.5684C17.3062 13.3166 17.4962 12.9682 17.5504 12.5875L18.3928 6.77234C18.4153 6.61768 18.407 6.46012 18.3685 6.30866C18.3301 6.1572 18.2621 6.01481 18.1685 5.88963C18.075 5.76445 17.9577 5.65894 17.8233 5.57913C17.689 5.49933 17.5402 5.44679 17.3855 5.42452C17.3288 5.41831 4.14055 5.41388 4.14055 5.41388"
                                                    stroke="white" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                                <path d="M12.0864 8.68945H14.5453" stroke="white" stroke-width="1.5"
                                                    stroke-linecap="round" stroke-linejoin="round" />
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M5.9053 17.0336C5.97033 17.0309 6.03525 17.0413 6.09615 17.0642C6.15706 17.0872 6.21268 17.1222 6.25968 17.1673C6.30668 17.2123 6.34408 17.2664 6.36963 17.3262C6.39518 17.3861 6.40836 17.4505 6.40836 17.5156C6.40836 17.5807 6.39518 17.6451 6.36963 17.7049C6.34408 17.7648 6.30668 17.8189 6.25968 17.8639C6.21268 17.9089 6.15706 17.944 6.09615 17.9669C6.03525 17.9899 5.97033 18.0003 5.9053 17.9975C5.78106 17.9922 5.66368 17.9391 5.57765 17.8493C5.49163 17.7595 5.4436 17.6399 5.4436 17.5156C5.4436 17.3912 5.49163 17.2717 5.57765 17.1819C5.66368 17.0921 5.78106 17.039 5.9053 17.0336Z"
                                                    fill="white" stroke="white" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M15.9083 17.0332C16.0365 17.0332 16.1594 17.0841 16.25 17.1747C16.3407 17.2654 16.3916 17.3883 16.3916 17.5165C16.3916 17.6446 16.3407 17.7676 16.25 17.8582C16.1594 17.9488 16.0365 17.9997 15.9083 17.9997C15.7801 17.9997 15.6572 17.9488 15.5666 17.8582C15.476 17.7676 15.425 17.6446 15.425 17.5165C15.425 17.3883 15.476 17.2654 15.5666 17.1747C15.6572 17.0841 15.7801 17.0332 15.9083 17.0332Z"
                                                    fill="white" stroke="white" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="bg-white border-t border-[#E9EEF4] px-6 pt-5 pb-4 flex flex-col gap-4 flex-1">
                                    <div class="flex flex-col gap-2">
                                        <p class="text-[#6F7983] text-[16px] font-medium leading-6">Article number: GR900012
                                        </p>
                                        <h3 class="text-[#2C3642] text-[20px] font-bold leading-7">JNF 16.312
                                            Schuifdeurtrekring ovaal 154 x 29 mm RVS</h3>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[#2C3642] text-[24px] font-extrabold leading-7">$20.00</span>
                                        <span
                                            class="text-[#6F7983] text-[14px] font-medium line-through capitalize">$39.00</span>
                                    </div>
                                </div>
                            </article>

                            <!-- Product Card 2 -->
                            <article
                                class="bg-[#F3F5F7] rounded-xl border border-[#E9EEF4] shadow-[4px_6px_20px_rgba(109,109,120,0.04)] overflow-hidden flex flex-col group cursor-pointer hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                                <div
                                    class="relative h-[280px] flex items-center justify-center bg-[#F3F5F7] overflow-hidden">
                                    <img src="assets/images/products/5125790ec379bcbb23b2d47e8def2d5bd30105ce.png"
                                        alt="Vitility Bord met opstaande rand"
                                        class="w-[500px] h-[500px] max-w-full max-h-full object-contain group-hover:scale-105 transition-transform duration-300 mix-blend-multiply" />
                                    <div
                                        class="absolute bottom-0 left-0 right-0 px-4 pb-4 translate-y-full opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300 ease-out">
                                        <button
                                            class="w-full h-12 bg-[#F5A623] hover:bg-[#d99012] text-white text-[16px] font-bold leading-[20.8px] rounded-xl flex items-center justify-center gap-2 transition-colors duration-200">
                                            Add to Cart
                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                    d="M2 2L3.84438 2.31922L4.69829 12.4926C4.73109 12.8929 4.91363 13.2661 5.20947 13.5378C5.50532 13.8095 5.89273 13.9597 6.29439 13.9583H15.9685C16.3531 13.9588 16.7249 13.8203 17.0156 13.5684C17.3062 13.3166 17.4962 12.9682 17.5504 12.5875L18.3928 6.77234C18.4153 6.61768 18.407 6.46012 18.3685 6.30866C18.3301 6.1572 18.2621 6.01481 18.1685 5.88963C18.075 5.76445 17.9577 5.65894 17.8233 5.57913C17.689 5.49933 17.5402 5.44679 17.3855 5.42452C17.3288 5.41831 4.14055 5.41388 4.14055 5.41388"
                                                    stroke="white" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                                <path d="M12.0864 8.68945H14.5453" stroke="white" stroke-width="1.5"
                                                    stroke-linecap="round" stroke-linejoin="round" />
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M5.9053 17.0336C5.97033 17.0309 6.03525 17.0413 6.09615 17.0642C6.15706 17.0872 6.21268 17.1222 6.25968 17.1673C6.30668 17.2123 6.34408 17.2664 6.36963 17.3262C6.39518 17.3861 6.40836 17.4505 6.40836 17.5156C6.40836 17.5807 6.39518 17.6451 6.36963 17.7049C6.34408 17.7648 6.30668 17.8189 6.25968 17.8639C6.21268 17.9089 6.15706 17.944 6.09615 17.9669C6.03525 17.9899 5.97033 18.0003 5.9053 17.9975C5.78106 17.9922 5.66368 17.9391 5.57765 17.8493C5.49163 17.7595 5.4436 17.6399 5.4436 17.5156C5.4436 17.3912 5.49163 17.2717 5.57765 17.1819C5.66368 17.0921 5.78106 17.039 5.9053 17.0336Z"
                                                    fill="white" stroke="white" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M15.9083 17.0332C16.0365 17.0332 16.1594 17.0841 16.25 17.1747C16.3407 17.2654 16.3916 17.3883 16.3916 17.5165C16.3916 17.6446 16.3407 17.7676 16.25 17.8582C16.1594 17.9488 16.0365 17.9997 15.9083 17.9997C15.7801 17.9997 15.6572 17.9488 15.5666 17.8582C15.476 17.7676 15.425 17.6446 15.425 17.5165C15.425 17.3883 15.476 17.2654 15.5666 17.1747C15.6572 17.0841 15.7801 17.0332 15.9083 17.0332Z"
                                                    fill="white" stroke="white" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="bg-white border-t border-[#E9EEF4] px-6 pt-5 pb-4 flex flex-col gap-4 flex-1">
                                    <div class="flex flex-col gap-2">
                                        <p class="text-[#6F7983] text-[16px] font-medium leading-6">Article number: GR900013
                                        </p>
                                        <h3 class="text-[#2C3642] text-[20px] font-bold leading-7">Vitility Bord met
                                            opstaande rand</h3>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[#2C3642] text-[24px] font-extrabold leading-7">$20.00</span>
                                    </div>
                                </div>
                            </article>

                            <!-- Product Card 3 -->
                            <article
                                class="bg-[#F3F5F7] rounded-xl border border-[#E9EEF4] shadow-[4px_6px_20px_rgba(109,109,120,0.04)] overflow-hidden flex flex-col group cursor-pointer hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                                <div
                                    class="relative h-[280px] flex items-center justify-center bg-[#F3F5F7] overflow-hidden">
                                    <img src="assets/images/products/69bddf9723b9c2ed7f4cfc93aecf3040c4f1a1db.png"
                                        alt="Vitility Multi Opener Handy"
                                        class="w-[500px] h-[500px] max-w-full max-h-full object-contain group-hover:scale-105 transition-transform duration-300 mix-blend-multiply" />
                                    <div
                                        class="absolute bottom-0 left-0 right-0 px-4 pb-4 translate-y-full opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300 ease-out">
                                        <button
                                            class="w-full h-12 bg-[#F5A623] hover:bg-[#d99012] text-white text-[16px] font-bold leading-[20.8px] rounded-xl flex items-center justify-center gap-2 transition-colors duration-200">
                                            Add to Cart
                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                    d="M2 2L3.84438 2.31922L4.69829 12.4926C4.73109 12.8929 4.91363 13.2661 5.20947 13.5378C5.50532 13.8095 5.89273 13.9597 6.29439 13.9583H15.9685C16.3531 13.9588 16.7249 13.8203 17.0156 13.5684C17.3062 13.3166 17.4962 12.9682 17.5504 12.5875L18.3928 6.77234C18.4153 6.61768 18.407 6.46012 18.3685 6.30866C18.3301 6.1572 18.2621 6.01481 18.1685 5.88963C18.075 5.76445 17.9577 5.65894 17.8233 5.57913C17.689 5.49933 17.5402 5.44679 17.3855 5.42452C17.3288 5.41831 4.14055 5.41388 4.14055 5.41388"
                                                    stroke="white" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                                <path d="M12.0864 8.68945H14.5453" stroke="white" stroke-width="1.5"
                                                    stroke-linecap="round" stroke-linejoin="round" />
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M5.9053 17.0336C5.97033 17.0309 6.03525 17.0413 6.09615 17.0642C6.15706 17.0872 6.21268 17.1222 6.25968 17.1673C6.30668 17.2123 6.34408 17.2664 6.36963 17.3262C6.39518 17.3861 6.40836 17.4505 6.40836 17.5156C6.40836 17.5807 6.39518 17.6451 6.36963 17.7049C6.34408 17.7648 6.30668 17.8189 6.25968 17.8639C6.21268 17.9089 6.15706 17.944 6.09615 17.9669C6.03525 17.9899 5.97033 18.0003 5.9053 17.9975C5.78106 17.9922 5.66368 17.9391 5.57765 17.8493C5.49163 17.7595 5.4436 17.6399 5.4436 17.5156C5.4436 17.3912 5.49163 17.2717 5.57765 17.1819C5.66368 17.0921 5.78106 17.039 5.9053 17.0336Z"
                                                    fill="white" stroke="white" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M15.9083 17.0332C16.0365 17.0332 16.1594 17.0841 16.25 17.1747C16.3407 17.2654 16.3916 17.3883 16.3916 17.5165C16.3916 17.6446 16.3407 17.7676 16.25 17.8582C16.1594 17.9488 16.0365 17.9997 15.9083 17.9997C15.7801 17.9997 15.6572 17.9488 15.5666 17.8582C15.476 17.7676 15.425 17.6446 15.425 17.5165C15.425 17.3883 15.476 17.2654 15.5666 17.1747C15.6572 17.0841 15.7801 17.0332 15.9083 17.0332Z"
                                                    fill="white" stroke="white" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="bg-white border-t border-[#E9EEF4] px-6 pt-5 pb-4 flex flex-col gap-4 flex-1">
                                    <div class="flex flex-col gap-2">
                                        <p class="text-[#6F7983] text-[16px] font-medium leading-6">Article number: GR900014
                                        </p>
                                        <h3 class="text-[#2C3642] text-[20px] font-bold leading-7">Vitility Multi Opener
                                            Handy</h3>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[#2C3642] text-[24px] font-extrabold leading-7">$20.00</span>
                                    </div>
                                </div>
                            </article>

                            <!-- Product Card 4 -->
                            <article
                                class="bg-[#F3F5F7] rounded-xl border border-[#E9EEF4] shadow-[4px_6px_20px_rgba(109,109,120,0.04)] overflow-hidden flex flex-col group cursor-pointer hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                                <div
                                    class="relative h-[280px] flex items-center justify-center bg-[#F3F5F7] overflow-hidden">
                                    <img src="assets/images/products/7450286542afaa19d6ac6dc7c4352e08c2261add.png"
                                        alt="Kruk en stokdoppen 19 mm zwart"
                                        class="w-[500px] h-[500px] max-w-full max-h-full object-contain group-hover:scale-105 transition-transform duration-300 mix-blend-multiply" />
                                    <div
                                        class="absolute bottom-0 left-0 right-0 px-4 pb-4 translate-y-full opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300 ease-out">
                                        <button
                                            class="w-full h-12 bg-[#F5A623] hover:bg-[#d99012] text-white text-[16px] font-bold leading-[20.8px] rounded-xl flex items-center justify-center gap-2 transition-colors duration-200">
                                            Add to Cart
                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                    d="M2 2L3.84438 2.31922L4.69829 12.4926C4.73109 12.8929 4.91363 13.2661 5.20947 13.5378C5.50532 13.8095 5.89273 13.9597 6.29439 13.9583H15.9685C16.3531 13.9588 16.7249 13.8203 17.0156 13.5684C17.3062 13.3166 17.4962 12.9682 17.5504 12.5875L18.3928 6.77234C18.4153 6.61768 18.407 6.46012 18.3685 6.30866C18.3301 6.1572 18.2621 6.01481 18.1685 5.88963C18.075 5.76445 17.9577 5.65894 17.8233 5.57913C17.689 5.49933 17.5402 5.44679 17.3855 5.42452C17.3288 5.41831 4.14055 5.41388 4.14055 5.41388"
                                                    stroke="white" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                                <path d="M12.0864 8.68945H14.5453" stroke="white" stroke-width="1.5"
                                                    stroke-linecap="round" stroke-linejoin="round" />
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M5.9053 17.0336C5.97033 17.0309 6.03525 17.0413 6.09615 17.0642C6.15706 17.0872 6.21268 17.1222 6.25968 17.1673C6.30668 17.2123 6.34408 17.2664 6.36963 17.3262C6.39518 17.3861 6.40836 17.5156C6.40836 17.5807 6.39518 17.6451 6.36963 17.7049C6.34408 17.7648 6.30668 17.8189 6.25968 17.8639C6.21268 17.9089 6.15706 17.944 6.09615 17.9669C6.03525 17.9899 5.97033 18.0003 5.9053 17.9975C5.78106 17.9922 5.66368 17.9391 5.57765 17.8493C5.49163 17.7595 5.4436 17.6399 5.4436 17.5156C5.4436 17.3912 5.49163 17.2717 5.57765 17.1819C5.66368 17.0921 5.78106 17.039 5.9053 17.0336Z"
                                                    fill="white" stroke="white" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M15.9083 17.0332C16.0365 17.0332 16.1594 17.0841 16.25 17.1747C16.3407 17.2654 16.3916 17.3883 16.3916 17.5165C16.3916 17.6446 16.3407 17.7676 16.25 17.8582C16.1594 17.9488 16.0365 17.9997 15.9083 17.9997C15.7801 17.9997 15.6572 17.9488 15.5666 17.8582C15.476 17.7676 15.425 17.6446 15.425 17.5165C15.425 17.3883 15.476 17.2654 15.5666 17.1747C15.6572 17.0841 15.7801 17.0332 15.9083 17.0332Z"
                                                    fill="white" stroke="white" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="bg-white border-t border-[#E9EEF4] px-6 pt-5 pb-4 flex flex-col gap-4 flex-1">
                                    <div class="flex flex-col gap-2">
                                        <p class="text-[#6F7983] text-[16px] font-medium leading-6">Article number: GR900015
                                        </p>
                                        <h3 class="text-[#2C3642] text-[20px] font-bold leading-7">Kruk en stokdoppen 19 mm
                                            zwart</h3>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[#2C3642] text-[24px] font-extrabold leading-7">$20.00</span>
                                    </div>
                                </div>
                            </article>

                            <!-- Product Card 5 -->
                            <article
                                class="bg-[#F3F5F7] rounded-xl border border-[#E9EEF4] shadow-[4px_6px_20px_rgba(109,109,120,0.04)] overflow-hidden flex flex-col group cursor-pointer hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                                <div
                                    class="relative h-[280px] flex items-center justify-center bg-[#F3F5F7] overflow-hidden">
                                    <img src="assets/images/products/9c7cb82f48c5e97051c85e9192ab69a6fe7d59d0.png"
                                        alt="Vitility Polsband voor wandelstok"
                                        class="w-[500px] h-[500px] max-w-full max-h-full object-contain group-hover:scale-105 transition-transform duration-300 mix-blend-multiply" />
                                    <span
                                        class="absolute top-5 left-5 px-3 py-1.5 bg-[#2C3642] text-white text-[16px] font-semibold capitalize leading-[17.6px] rounded">Sale</span>
                                    <div
                                        class="absolute bottom-0 left-0 right-0 px-4 pb-4 translate-y-full opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300 ease-out">
                                        <button
                                            class="w-full h-12 bg-[#F5A623] hover:bg-[#d99012] text-white text-[16px] font-bold leading-[20.8px] rounded-xl flex items-center justify-center gap-2 transition-colors duration-200">
                                            Add to Cart
                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                    d="M2 2L3.84438 2.31922L4.69829 12.4926C4.73109 12.8929 4.91363 13.2661 5.20947 13.5378C5.50532 13.8095 5.89273 13.9597 6.29439 13.9583H15.9685C16.3531 13.9588 16.7249 13.8203 17.0156 13.5684C17.3062 13.3166 17.4962 12.9682 17.5504 12.5875L18.3928 6.77234C18.4153 6.61768 18.407 6.46012 18.3685 6.30866C18.3301 6.1572 18.2621 6.01481 18.1685 5.88963C18.075 5.76445 17.9577 5.65894 17.8233 5.57913C17.689 5.49933 17.5402 5.44679 17.3855 5.42452C17.3288 5.41831 4.14055 5.41388 4.14055 5.41388"
                                                    stroke="white" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                                <path d="M12.0864 8.68945H14.5453" stroke="white" stroke-width="1.5"
                                                    stroke-linecap="round" stroke-linejoin="round" />
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M5.9053 17.0336C5.97033 17.0309 6.03525 17.0413 6.09615 17.0642C6.15706 17.0872 6.21268 17.1222 6.25968 17.1673C6.30668 17.2123 6.34408 17.2664 6.36963 17.3262C6.39518 17.3861 6.40836 17.5156C6.40836 17.5807 6.39518 17.6451 6.36963 17.7049C6.34408 17.7648 6.30668 17.8189 6.25968 17.8639C6.21268 17.9089 6.15706 17.944 6.09615 17.9669C6.03525 17.9899 5.97033 18.0003 5.9053 17.9975C5.78106 17.9922 5.66368 17.9391 5.57765 17.8493C5.49163 17.7595 5.4436 17.6399 5.4436 17.5156C5.4436 17.3912 5.49163 17.2717 5.57765 17.1819C5.66368 17.0921 5.78106 17.039 5.9053 17.0336Z"
                                                    fill="white" stroke="white" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M15.9083 17.0332C16.0365 17.0332 16.1594 17.0841 16.25 17.1747C16.3407 17.2654 16.3916 17.3883 16.3916 17.5165C16.3916 17.6446 16.3407 17.7676 16.25 17.8582C16.1594 17.9488 16.0365 17.9997 15.9083 17.9997C15.7801 17.9997 15.6572 17.9488 15.5666 17.8582C15.476 17.7676 15.425 17.6446 15.425 17.5165C15.425 17.3883 15.476 17.2654 15.5666 17.1747C15.6572 17.0841 15.7801 17.0332 15.9083 17.0332Z"
                                                    fill="white" stroke="white" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="bg-white border-t border-[#E9EEF4] px-6 pt-5 pb-4 flex flex-col gap-4 flex-1">
                                    <div class="flex flex-col gap-2">
                                        <p class="text-[#6F7983] text-[16px] font-medium leading-6">Article number: GR900016
                                        </p>
                                        <h3 class="text-[#2C3642] text-[20px] font-bold leading-7">Vitility Polsband voor
                                            wandelstok</h3>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[#2C3642] text-[24px] font-extrabold leading-7">$20.00</span>
                                        <span
                                            class="text-[#6F7983] text-[14px] font-medium line-through capitalize">$39.00</span>
                                    </div>
                                </div>
                            </article>

                            <!-- Product Card 6 -->
                            <article
                                class="bg-[#F3F5F7] rounded-xl border border-[#E9EEF4] shadow-[4px_6px_20px_rgba(109,109,120,0.04)] overflow-hidden flex flex-col group cursor-pointer hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                                <div
                                    class="relative h-[280px] flex items-center justify-center bg-[#F3F5F7] overflow-hidden">
                                    <img src="assets/images/products/ddd3c9cd450f7f848aaad72a4a94b374810e43c4.png"
                                        alt="Vitility Handvatverdikkers 8 stuks"
                                        class="w-[500px] h-[500px] max-w-full max-h-full object-contain group-hover:scale-105 transition-transform duration-300 mix-blend-multiply" />
                                    <span
                                        class="absolute top-5 left-5 px-3 py-1.5 bg-[#2C3642] text-white text-[16px] font-semibold capitalize leading-[17.6px] rounded">Sale</span>
                                    <div
                                        class="absolute bottom-0 left-0 right-0 px-4 pb-4 translate-y-full opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300 ease-out">
                                        <button
                                            class="w-full h-12 bg-[#F5A623] hover:bg-[#d99012] text-white text-[16px] font-bold leading-[20.8px] rounded-xl flex items-center justify-center gap-2 transition-colors duration-200">
                                            Add to Cart
                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                    d="M2 2L3.84438 2.31922L4.69829 12.4926C4.73109 12.8929 4.91363 13.2661 5.20947 13.5378C5.50532 13.8095 5.89273 13.9597 6.29439 13.9583H15.9685C16.3531 13.9588 16.7249 13.8203 17.0156 13.5684C17.3062 13.3166 17.4962 12.9682 17.5504 12.5875L18.3928 6.77234C18.4153 6.61768 18.407 6.46012 18.3685 6.30866C18.3301 6.1572 18.2621 6.01481 18.1685 5.88963C18.075 5.76445 17.9577 5.65894 17.8233 5.57913C17.689 5.49933 17.5402 5.44679 17.3855 5.42452C17.3288 5.41831 4.14055 5.41388 4.14055 5.41388"
                                                    stroke="white" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                                <path d="M12.0864 8.68945H14.5453" stroke="white" stroke-width="1.5"
                                                    stroke-linecap="round" stroke-linejoin="round" />
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M5.9053 17.0336C5.97033 17.0309 6.03525 17.0413 6.09615 17.0642C6.15706 17.0872 6.21268 17.1222 6.25968 17.1673C6.30668 17.2123 6.34408 17.2664 6.36963 17.3262C6.39518 17.3861 6.40836 17.4505 6.40836 17.5156C6.40836 17.5807 6.39518 17.6451 6.36963 17.7049C6.34408 17.7648 6.30668 17.8189 6.25968 17.8639C6.21268 17.9089 6.15706 17.944 6.09615 17.9669C6.03525 17.9899 5.97033 18.0003 5.9053 17.9975C5.78106 17.9922 5.66368 17.9391 5.57765 17.8493C5.49163 17.7595 5.4436 17.6399 5.4436 17.5156C5.4436 17.3912 5.49163 17.2717 5.57765 17.1819C5.66368 17.0921 5.78106 17.039 5.9053 17.0336Z"
                                                    fill="white" stroke="white" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                                <path fill-rule="evenodd" clip-rule="evenodd"
                                                    d="M15.9083 17.0332C16.0365 17.0332 16.1594 17.0841 16.25 17.1747C16.3407 17.2654 16.3916 17.3883 16.3916 17.5165C16.3916 17.6446 16.3407 17.7676 16.25 17.8582C16.1594 17.9488 16.0365 17.9997 15.9083 17.9997C15.7801 17.9997 15.6572 17.9488 15.5666 17.8582C15.476 17.7676 15.425 17.6446 15.425 17.5165C15.425 17.3883 15.476 17.2654 15.5666 17.1747C15.6572 17.0841 15.7801 17.0332 15.9083 17.0332Z"
                                                    fill="white" stroke="white" stroke-width="1.5" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div class="bg-white border-t border-[#E9EEF4] px-6 pt-5 pb-4 flex flex-col gap-4 flex-1">
                                    <div class="flex flex-col gap-2">
                                        <p class="text-[#6F7983] text-[16px] font-medium leading-6">Article number: GR900017
                                        </p>
                                        <h3 class="text-[#2C3642] text-[20px] font-bold leading-7">Vitility
                                            Handvatverdikkers 8 stuks</h3>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[#2C3642] text-[24px] font-extrabold leading-7">$20.00</span>
                                        <span
                                            class="text-[#6F7983] text-[14px] font-medium line-through capitalize">$39.00</span>
                                    </div>
                                </div>
                            </article>

                        </div>

                        <!-- Pagination -->
                        <div class="flex justify-center items-center gap-2 mt-12">
                            <button
                                class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-[#D0D7DD] hover:border-[#E9A821] hover:text-[#E9A821] text-[#6F7983] transition-all disabled:opacity-50"
                                disabled>
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
                                </svg>
                            </button>
                            <button
                                class="w-10 h-10 flex items-center justify-center rounded-xl bg-[#E9A821] text-white font-sans font-bold shadow-md hover:bg-[#d09214] transition-all">1</button>
                            <button
                                class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-[#D0D7DD] hover:border-[#E9A821] hover:text-[#E9A821] text-[#4D5964] font-sans font-medium transition-all">2</button>
                            <button
                                class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-[#D0D7DD] hover:border-[#E9A821] hover:text-[#E9A821] text-[#4D5964] font-sans font-medium transition-all">3</button>
                            <button
                                class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-[#D0D7DD] hover:border-[#E9A821] hover:text-[#E9A821] text-[#6F7983] transition-all">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>

                    </div>
                </div>
            </section>

            <!-- MOBILE FILTERS DRAWER -->
            <div id="mobile-filters-drawer"
                class="fixed inset-0 z-50 bg-[#2C3642]/60 backdrop-blur-sm opacity-0 pointer-events-none transition-all duration-300">
                <div class="fixed top-0 left-0 bottom-0 w-[300px] bg-white shadow-2xl flex flex-col justify-between translate-x-full transition-transform duration-300 ease-in-out"
                    id="mobile-filters-drawer-content">
                    <div class="flex flex-col h-full overflow-y-auto p-6 gap-6 scrollbar-none">
                        <!-- Header -->
                        <div class="flex justify-between items-center pb-4 border-b border-slate-100 flex-shrink-0">
                            <span class="text-[#2C3642] text-[20px] font-sans font-bold">Filter Products</span>
                            <button id="mobile-filters-close" class="p-1 hover:opacity-85 text-[#2C3642]"
                                aria-label="Close Filters">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Search in drawer -->
                        <div class="flex flex-col gap-3 flex-shrink-0">
                            <h3 class="text-[#2C3642] text-sm font-bold font-sans">Search</h3>
                            <div
                                class="relative w-full h-[40px] px-3 bg-[#F8FAFC] rounded-xl border border-[#D0D7DD] flex justify-start items-center gap-2 focus-within:bg-white focus-within:border-[#6F7983] transition-all duration-200">
                                <input type="text" placeholder="Search..."
                                    class="w-full bg-transparent border-none outline-none text-[#2C3642] text-[14px] font-sans placeholder-[#6F7983] focus:ring-0" />
                            </div>
                        </div>

                        <!-- Categories in drawer -->
                        <div class="flex flex-col gap-3">
                            <h3 class="text-[#2C3642] text-sm font-bold font-sans">Categories</h3>
                            <div class="flex flex-col gap-2">
                                <label class="flex items-center justify-between cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox"
                                            class="w-4 h-4 rounded border-[#D0D7DD] text-[#E9A821] focus:ring-offset-0 focus:ring-[#E9A821]" />
                                        <span class="text-sm font-sans font-medium text-[#4D5964]">Ergonomisch</span>
                                    </div>
                                </label>
                                <label class="flex items-center justify-between cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox"
                                            class="w-4 h-4 rounded border-[#D0D7DD] text-[#E9A821] focus:ring-offset-0 focus:ring-[#E9A821]" />
                                        <span class="text-sm font-sans font-medium text-[#4D5964]">Hygiëne</span>
                                    </div>
                                </label>
                                <label class="flex items-center justify-between cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox"
                                            class="w-4 h-4 rounded border-[#D0D7DD] text-[#E9A821] focus:ring-offset-0 focus:ring-[#E9A821]"
                                            checked />
                                        <span class="text-sm font-sans font-semibold text-[#2C3642]">Mobiliteit</span>
                                    </div>
                                </label>
                                <label class="flex items-center justify-between cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox"
                                            class="w-4 h-4 rounded border-[#D0D7DD] text-[#E9A821] focus:ring-offset-0 focus:ring-[#E9A821]" />
                                        <span class="text-sm font-sans font-medium text-[#4D5964]">Orthopedisch</span>
                                    </div>
                                </label>
                                <label class="flex items-center justify-between cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox"
                                            class="w-4 h-4 rounded border-[#D0D7DD] text-[#E9A821] focus:ring-offset-0 focus:ring-[#E9A821]" />
                                        <span class="text-sm font-sans font-medium text-[#4D5964]">Hang &amp;
                                            Sluitwerk</span>
                                    </div>
                                </label>
                                <label class="flex items-center justify-between cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox"
                                            class="w-4 h-4 rounded border-[#D0D7DD] text-[#E9A821] focus:ring-offset-0 focus:ring-[#E9A821]" />
                                        <span class="text-sm font-sans font-medium text-[#4D5964]">Arm- en fiets
                                            trainers</span>
                                    </div>
                                </label>
                                <label class="flex items-center justify-between cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox"
                                            class="w-4 h-4 rounded border-[#D0D7DD] text-[#E9A821] focus:ring-offset-0 focus:ring-[#E9A821]" />
                                        <span class="text-sm font-sans font-medium text-[#4D5964]">Verzorging</span>
                                    </div>
                                </label>
                                <label class="flex items-center justify-between cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox"
                                            class="w-4 h-4 rounded border-[#D0D7DD] text-[#E9A821] focus:ring-offset-0 focus:ring-[#E9A821]" />
                                        <span class="text-sm font-sans font-medium text-[#4D5964]">Overig</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Price Range in drawer -->
                        <div class="flex flex-col gap-3">
                            <h3 class="text-[#2C3642] text-sm font-bold font-sans">Price Range</h3>
                            <div class="flex items-center gap-2">
                                <input type="number" value="10"
                                    class="w-full px-2 py-1.5 bg-[#F8FAFC] border border-[#D0D7DD] rounded-lg text-sm text-[#2C3642]" />
                                <span class="text-slate-400">-</span>
                                <input type="number" value="150"
                                    class="w-full px-2 py-1.5 bg-[#F8FAFC] border border-[#D0D7DD] rounded-lg text-sm text-[#2C3642]" />
                            </div>
                        </div>

                        <!-- Status / Availability in drawer -->
                        <div class="flex flex-col gap-3">
                            <h3 class="text-[#2C3642] text-sm font-bold font-sans">Availability</h3>
                            <div class="flex flex-col gap-2">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox"
                                        class="w-4 h-4 rounded border-[#D0D7DD] text-[#E9A821] focus:ring-offset-0"
                                        checked />
                                    <span class="text-sm font-sans font-medium text-[#4D5964]">In Stock</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox"
                                        class="w-4 h-4 rounded border-[#D0D7DD] text-[#E9A821] focus:ring-offset-0" />
                                    <span class="text-sm font-sans font-medium text-[#4D5964]">On Sale</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Apply Filters button in drawer footer -->
                    <div class="p-6 border-t border-slate-100 flex-shrink-0 flex gap-3">
                        <button id="mobile-filters-reset"
                            class="flex-1 py-3 bg-slate-50 border border-[#D0D7DD] hover:border-slate-400 text-[#6F7983] font-sans font-bold text-sm rounded-xl transition-all">Reset</button>
                        <button id="mobile-filters-apply"
                            class="flex-1 py-3 bg-[#E9A821] hover:bg-[#d09214] text-white font-sans font-bold text-sm rounded-xl transition-all">Apply</button>
                    </div>
                </div>
            </div>

            <!-- Script for mobile filters drawer controls -->
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const filterToggleBtn = document.getElementById('mobile-filter-toggle');
                    const filterCloseBtn = document.getElementById('mobile-filters-close');
                    const filterDrawer = document.getElementById('mobile-filters-drawer');
                    const filterDrawerContent = document.getElementById('mobile-filters-drawer-content');
                    const filterApplyBtn = document.getElementById('mobile-filters-apply');
                    const filterResetBtn = document.getElementById('mobile-filters-reset');

                    if (filterToggleBtn && filterCloseBtn && filterDrawer && filterDrawerContent) {
                        const openFilters = () => {
                            filterDrawer.classList.remove('opacity-0', 'pointer-events-none');
                            filterDrawer.classList.add('opacity-100', 'pointer-events-auto');
                            filterDrawerContent.classList.remove('translate-x-full');
                            filterDrawerContent.classList.add('translate-x-0');
                            document.body.style.overflow = 'hidden';
                        };

                        const closeFilters = () => {
                            filterDrawer.classList.remove('opacity-100', 'pointer-events-auto');
                            filterDrawer.classList.add('opacity-0', 'pointer-events-none');
                            filterDrawerContent.classList.remove('translate-x-0');
                            filterDrawerContent.classList.add('translate-x-full');
                            document.body.style.overflow = '';
                        };

                        filterToggleBtn.addEventListener('click', openFilters);
                        filterCloseBtn.addEventListener('click', closeFilters);
                        if (filterApplyBtn) filterApplyBtn.addEventListener('click', closeFilters);
                        if (filterResetBtn) filterResetBtn.addEventListener('click', closeFilters);

                        filterDrawer.addEventListener('click', (e) => {
                            if (e.target === filterDrawer) {
                                closeFilters();
                            }
                        });
                    }
                });
            </script>
        </main>

        <!-- Footer -->
        <footer class="bg-[#2C3642] text-white pt-20 pb-8 px-6 lg:px-[156px] font-sans">
            <div class="mx-auto flex flex-col gap-12">
                <!-- Top Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-12 lg:gap-8 items-start">
                    <!-- Brand & Newsletter (Col span 4) -->
                    <div class="col-span-1 sm:col-span-2 lg:col-span-4 flex flex-col gap-6">
                        <a href="/" class="inline-block hover:opacity-90 transition-opacity">
                            <img class="h-[60px] w-auto object-contain" src="assets/images/zeker-gemak-logo-white.png"
                                alt="ZekerGemak Logo" />
                        </a>
                        <div class="flex flex-col gap-8">
                            <div class="flex flex-col gap-4">
                                <p class="text-white text-sm font-medium leading-[20px] max-w-[335px]">
                                    Subscribe our newsletter to get more deals, new products and promotions
                                </p>
                                <form class="relative w-full max-w-[335px] h-12">
                                    <input type="email" placeholder="Enter your email" required
                                        class="w-full h-full pl-4 pr-14 bg-[#343F4D] border border-[#4D5964]/50 focus:border-white/40 focus:outline-none rounded-xl text-white text-sm placeholder-[#6F7983] transition-colors" />
                                    <button type="submit" aria-label="Subscribe"
                                        class="absolute right-1 top-1 w-10 h-10 bg-[#E9A821] hover:bg-[#d09214] active:scale-95 text-white rounded-lg flex items-center justify-center transition-all">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5"
                                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"></path>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                            <!-- Social Icons -->
                            <div class="flex items-center gap-5">
                                <a href="#" aria-label="Instagram"
                                    class="text-white hover:text-[#E9A821] transition-colors">
                                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none"
                                        xmlns="http://www.w3.org/2000/svg" class="w-8 h-8">
                                        <rect x="2.66667" y="2.6665" width="26.6667" height="26.6667" rx="5.33333"
                                            stroke="currentColor" stroke-width="2" />
                                        <circle cx="24" cy="7.99984" r="1.33333" fill="currentColor" />
                                        <circle cx="16" cy="16.0002" r="6.66667" stroke="currentColor" stroke-width="2" />
                                    </svg>
                                </a>
                                <a href="#" aria-label="Facebook" class="text-white hover:text-[#E9A821] transition-colors">
                                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none"
                                        xmlns="http://www.w3.org/2000/svg" class="w-8 h-8">
                                        <path
                                            d="M24 4H20C16.3181 4 13.3333 6.98477 13.3333 10.6667V13.3333H8V18.6667H13.3333V28H18.6667V18.6667H24V13.3333H18.6667V10.6667C18.6667 9.93029 19.2636 9.33333 20 9.33333H24V4Z"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                    </svg>
                                </a>
                                <a href="#" aria-label="YouTube" class="text-white hover:text-[#E9A821] transition-colors">
                                    <svg width="32" height="32" viewBox="0 0 32 32" fill="none"
                                        xmlns="http://www.w3.org/2000/svg" class="w-8 h-8">
                                        <rect x="2.66667" y="4" width="26.6667" height="24" rx="5.33333"
                                            stroke="currentColor" stroke-width="2" />
                                        <path
                                            d="M13.9296 11.6313L20.2815 14.8073C21.2643 15.2986 21.2643 16.701 20.2815 17.1924L13.9296 20.3684C13.0431 20.8116 12 20.167 12 19.1758V12.8239C12 11.8327 13.0431 11.188 13.9296 11.6313Z"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Quick links (Col span 2) -->
                    <div class="col-span-1 lg:col-span-2 flex flex-col gap-4">
                        <h3 class="text-white font-bold text-xl leading-7">Quick links</h3>
                        <div class="flex flex-col gap-3">
                            <a href="#"
                                class="text-white/70 hover:text-white transition-colors text-sm font-medium">Ergonomisch</a>
                            <a href="#"
                                class="text-white/70 hover:text-white transition-colors text-sm font-medium">Hygiëne</a>
                            <a href="#"
                                class="text-white/70 hover:text-white transition-colors text-sm font-medium">Orthopedisch</a>
                            <a href="#"
                                class="text-white/70 hover:text-white transition-colors text-sm font-medium">Mobiliteit</a>
                            <a href="#" class="text-white/70 hover:text-white transition-colors text-sm font-medium">Hang &
                                Sluitwerk</a>
                            <a href="#" class="text-white/70 hover:text-white transition-colors text-sm font-medium">Arm- en
                                fiets trainers</a>
                            <a href="#"
                                class="text-white/70 hover:text-white transition-colors text-sm font-medium">Verzorging</a>
                            <a href="#"
                                class="text-white/70 hover:text-white transition-colors text-sm font-medium">Overig</a>
                        </div>
                    </div>

                    <!-- Customer service (Col span 2) -->
                    <div class="col-span-1 lg:col-span-2 flex flex-col gap-4">
                        <h3 class="text-white font-bold text-xl leading-7">Customer service</h3>
                        <div class="flex flex-col gap-3">
                            <a href="#"
                                class="text-white/70 hover:text-white transition-colors text-sm font-medium">Shipping and
                                Pickup</a>
                            <a href="#"
                                class="text-white/70 hover:text-white transition-colors text-sm font-medium">Delivery
                                times</a>
                            <a href="#"
                                class="text-white/70 hover:text-white transition-colors text-sm font-medium">Available on
                                Backorder</a>
                            <a href="#"
                                class="text-white/70 hover:text-white transition-colors text-sm font-medium">Exchanges and
                                Returns</a>
                            <a href="#"
                                class="text-white/70 hover:text-white transition-colors text-sm font-medium">Warranty and
                                Complaints</a>
                        </div>
                    </div>

                    <!-- Company (Col span 2) -->
                    <div class="col-span-1 lg:col-span-2 flex flex-col gap-4">
                        <h3 class="text-white font-bold text-xl leading-7">Company</h3>
                        <div class="flex flex-col gap-3">
                            <a href="#" class="text-white/70 hover:text-white transition-colors text-sm font-medium">About
                                Us</a>
                            <a href="#" class="text-white/70 hover:text-white transition-colors text-sm font-medium">General
                                Terms & Condition</a>
                            <a href="#"
                                class="text-white/70 hover:text-white transition-colors text-sm font-medium">Promotional
                                Terms & Conditions</a>
                            <a href="#"
                                class="text-white/70 hover:text-white transition-colors text-sm font-medium">Privacy</a>
                        </div>
                    </div>

                    <!-- Contact & Organization (Col span 2) -->
                    <div class="col-span-1 lg:col-span-2 flex flex-col gap-8">
                        <!-- Contact Us -->
                        <div class="flex flex-col gap-4">
                            <h3 class="text-white font-bold text-xl leading-7">Contact us</h3>
                            <div class="flex flex-col gap-3">
                                <p class="text-white/70 text-sm font-medium leading-[20px]">
                                    Email: <a href="mailto:info@zekergemak.nl"
                                        class="text-[#E9A821] hover:underline font-semibold transition-all">info@zekergemak.nl</a>
                                </p>
                                <p class="text-white/70 text-sm font-medium leading-[20px]">
                                    Contact: <a href="#"
                                        class="text-[#E9A821] hover:underline font-semibold transition-all">click here</a>
                                </p>
                            </div>
                        </div>
                        <!-- Organization -->
                        <div class="flex flex-col gap-4">
                            <h3 class="text-white font-bold text-xl leading-7">Organization</h3>
                            <div class="flex flex-col gap-3 text-white/70 text-sm font-medium leading-[20px]">
                                <p>Name: Zeker Gemak BV</p>
                                <p>Bank: NL85 INGB 0007 0170 22</p>
                                <p>CoC number: 80287735</p>
                                <p>VAT number: 8616.17.873.B01</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Footer -->
                <div class="border-t border-[#4D5964]/50 pt-6 flex flex-col sm:flex-row items-center justify-between gap-6">
                    <p class="text-[#6F7983] text-sm font-medium text-center sm:text-left">
                        Copyright © Zeker Gemak. All rights reserved
                    </p>
                    <a href="https://www.webwinkelkeur.nl" target="_blank" rel="noopener"
                        class="hover:opacity-95 transition-opacity">
                        <img class="h-[40px] w-auto rounded-lg" src="assets/images/logos/webwinkel-keur.svg"
                            alt="Webwinkel Keur Trustmark" />
                    </a>
                </div>
            </div>
        </footer>
    </body>

    </html>
<?php endif; ?>