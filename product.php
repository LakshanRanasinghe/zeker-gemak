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
            <nav class="w-full bg-white border-b border-slate-100 py-4 lg:px-[156px] px-6" aria-label="Breadcrumb">
                <div
                    class="mx-auto flex flex-wrap items-center gap-x-2 gap-y-1.5 text-sm font-sans font-medium text-[#6F7983]">
                    <a href="index.php" class="hover:text-[#E9A821] transition-colors">Home</a>
                    <svg class="w-3.5 h-3.5 text-[#D0D7DD] flex-shrink-0" fill="none" stroke="currentColor"
                        stroke-width="2.2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                    </svg>
                    <a href="shop.php" class="hover:text-[#E9A821] transition-colors">Shop</a>
                    <svg class="w-3.5 h-3.5 text-[#D0D7DD] flex-shrink-0" fill="none" stroke="currentColor"
                        stroke-width="2.2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                    </svg>
                    <a href="#" class="hover:text-[#E9A821] transition-colors">Ergonomisch</a>
                    <svg class="w-3.5 h-3.5 text-[#D0D7DD] flex-shrink-0" fill="none" stroke="currentColor"
                        stroke-width="2.2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                    </svg>
                    <span class="text-[#e9a81f] font-semibold">Vitility Handvatverdikkers</span>
                </div>
            </nav>

            <!-- Product Showcase Section -->
            <section class="w-full lg:px-[156px] px-6 py-12 bg-[#F8FAFC]">
                <div class="mx-auto grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">

                    <!-- LEFT COLUMN: PRODUCT GALLERY SLIDER -->
                    <div class="lg:col-span-6 flex flex-col gap-6 w-full">
                        <!-- Main Image Viewer -->
                        <div class="relative aspect-square w-full rounded-2xl bg-white border border-slate-100 flex items-center justify-center p-6 sm:p-12 overflow-hidden shadow-[0_4px_20px_rgba(44,54,66,0.02)] select-none group"
                            id="main-image-container">

                            <!-- Badges -->
                            <div class="absolute top-5 left-5 flex flex-col gap-2 z-10 pointer-events-none">
                                <span
                                    class="px-3 py-1.5 bg-[#E9A821] text-white text-xs font-bold uppercase tracking-wider rounded-lg shadow-sm">Bestseller</span>
                                <span
                                    class="px-3 py-1.5 bg-[#2C3642] text-white text-xs font-bold uppercase tracking-wider rounded-lg shadow-sm">-49%
                                    Korting</span>
                            </div>

                            <!-- Zoom Helper Hint -->
                            <div
                                class="absolute top-5 right-5 z-10 bg-white/80 backdrop-blur-sm p-2 rounded-xl border border-slate-100 opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none">
                                <svg class="w-5 h-5 text-[#2C3642]" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10.5 7.5v6m3-3h-6"></path>
                                </svg>
                            </div>

                            <!-- Image wrapper for zoom calculations -->
                            <div class="w-full h-full flex items-center justify-center pointer-events-none">
                                <img id="main-image"
                                    src="assets/images/products/ddd3c9cd450f7f848aaad72a4a94b374810e43c4.png"
                                    alt="Vitility Handvatverdikkers - Set Overzicht"
                                    class="max-w-full max-h-full object-contain transition-transform duration-200 ease-out origin-center cursor-zoom-in pointer-events-auto mix-blend-multiply"
                                    onclick="openLightbox()" />
                            </div>

                            <!-- Navigation Arrows Overlay -->
                            <button onclick="prevImage(event)"
                                class="absolute left-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/90 hover:bg-white border border-slate-100 hover:border-slate-200 text-[#2C3642] hover:text-[#E9A821] flex items-center justify-center shadow-md active:scale-95 opacity-0 group-hover:opacity-100 transition-all duration-300 focus:outline-none"
                                aria-label="Vorige afbeelding">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5"
                                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
                                </svg>
                            </button>
                            <button onclick="nextImage(event)"
                                class="absolute right-4 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/90 hover:bg-white border border-slate-100 hover:border-slate-200 text-[#2C3642] hover:text-[#E9A821] flex items-center justify-center shadow-md active:scale-95 opacity-0 group-hover:opacity-100 transition-all duration-300 focus:outline-none"
                                aria-label="Volgende afbeelding">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5"
                                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Gallery Thumbnails Strip -->
                        <div class="w-full flex items-center gap-4">
                            <!-- Thumb 1 (Active) -->
                            <button onclick="setGalleryImage(0)"
                                class="gallery-thumb flex-1 aspect-square rounded-xl bg-white border-2 border-[#E9A821] hover:border-[#E9A821] p-2 flex items-center justify-center shadow-sm transition-all overflow-hidden focus:outline-none">
                                <img src="assets/images/products/ddd3c9cd450f7f848aaad72a4a94b374810e43c4.png" alt="Thumb 1"
                                    class="max-w-full max-h-full object-contain mix-blend-multiply" />
                            </button>
                            <!-- Thumb 2 -->
                            <button onclick="setGalleryImage(1)"
                                class="gallery-thumb flex-1 aspect-square rounded-xl bg-white border-2 border-[#E9EEF4] hover:border-[#E9A821] p-2 flex items-center justify-center shadow-sm transition-all overflow-hidden focus:outline-none">
                                <img src="assets/images/products/5125790ec379bcbb23b2d47e8def2d5bd30105ce.png" alt="Thumb 2"
                                    class="max-w-full max-h-full object-contain mix-blend-multiply" />
                            </button>
                            <!-- Thumb 3 -->
                            <button onclick="setGalleryImage(2)"
                                class="gallery-thumb flex-1 aspect-square rounded-xl bg-white border-2 border-[#E9EEF4] hover:border-[#E9A821] p-2 flex items-center justify-center shadow-sm transition-all overflow-hidden focus:outline-none">
                                <img src="assets/images/products/69bddf9723b9c2ed7f4cfc93aecf3040c4f1a1db.png" alt="Thumb 3"
                                    class="max-w-full max-h-full object-contain mix-blend-multiply" />
                            </button>
                            <!-- Thumb 4 -->
                            <button onclick="setGalleryImage(3)"
                                class="gallery-thumb flex-1 aspect-square rounded-xl bg-white border-2 border-[#E9EEF4] hover:border-[#E9A821] p-2 flex items-center justify-center shadow-sm transition-all overflow-hidden focus:outline-none">
                                <img src="assets/images/products/9c7cb82f48c5e97051c85e9192ab69a6fe7d59d0.png" alt="Thumb 4"
                                    class="max-w-full max-h-full object-contain mix-blend-multiply" />
                            </button>

                            <button onclick="setGalleryImage(3)"
                                class="gallery-thumb flex-1 aspect-square rounded-xl bg-white border-2 border-[#E9EEF4] hover:border-[#E9A821] p-2 flex items-center justify-center shadow-sm transition-all overflow-hidden focus:outline-none">
                                <img src="assets/images/products/9c7cb82f48c5e97051c85e9192ab69a6fe7d59d0.png" alt="Thumb 4"
                                    class="max-w-full max-h-full object-contain mix-blend-multiply" />
                            </button>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: PRODUCT INFO & PURCHASE CONTROLS -->
                    <div class="lg:col-span-6 flex flex-col gap-6 w-full">
                        <!-- Brand, Rating & Category -->
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-[#E9A821] text-sm font-bold uppercase tracking-wider font-dm">Vitility</span>
                                <span
                                    class="text-xs font-mono font-medium text-[#6F7983] bg-slate-100 px-2.5 py-1 rounded-md">Art.
                                    Nr. GR900016</span>
                            </div>
                            <h1 class="text-[#2C3642] text-3xl sm:text-4xl font-bold font-sans leading-tight">Vitility
                                Handvatverdikkers – Set van 8 stuks</h1>

                            <!-- Star Ratings -->
                            <div class="flex items-center gap-3 mt-1">
                                <div class="flex items-center text-[#E9A821]">
                                    <!-- 5 Gold Stars -->
                                    <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20">
                                        <path
                                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                        </path>
                                    </svg>
                                    <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20">
                                        <path
                                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                        </path>
                                    </svg>
                                    <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20">
                                        <path
                                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                        </path>
                                    </svg>
                                    <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20">
                                        <path
                                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                        </path>
                                    </svg>
                                    <svg class="w-5 h-5 fill-current text-slate-300" viewBox="0 0 20 20">
                                        <path
                                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                        </path>
                                    </svg>
                                </div>
                                <a href="#tabs-section" onclick="switchTab('reviews')"
                                    class="text-sm text-[#4D5964] hover:text-[#E9A821] hover:underline font-medium font-sans">(24
                                    klantbeoordelingen)</a>
                            </div>
                        </div>

                        <!-- Price Section -->
                        <div
                            class="bg-white p-5 rounded-2xl border border-slate-100 shadow-[0_4px_20px_rgba(44,54,66,0.01)] flex flex-col gap-2">
                            <div class="flex items-center gap-3">
                                <span class="text-[#2C3642] text-3xl sm:text-4xl font-extrabold font-sans">€20,00</span>
                                <span class="text-[#6F7983] text-lg font-semibold line-through">€39,00</span>
                                <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-1 rounded-md">Je
                                    bespaart 49%</span>
                            </div>
                            <p class="text-xs text-[#6F7983] font-medium font-sans">Inclusief BTW, exclusief eventuele
                                verzendkosten</p>

                            <!-- Delivery Status -->
                            <div class="flex items-center gap-2 border-t border-slate-100 pt-3 mt-1">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span class="text-sm font-semibold text-emerald-600 font-sans">Ruim op voorraad – Voor 23:59
                                    besteld, morgen in huis</span>
                            </div>
                        </div>

                        <!-- Short Description -->
                        <p class="text-[#4D5964] text-base leading-relaxed font-sans font-normal">
                            Deze multifunctionele handvatverdikkers zijn speciaal ontworpen om de grip op dagelijkse
                            voorwerpen zoals bestek, pennen en tandenborstels aanzienlijk te verbeteren. Perfect voor mensen
                            met reuma, artritis, Parkinson of verminderde handkracht. De set bevat 8 verdikkers in
                            verschillende diameters voor optimale veelzijdigheid.
                        </p>

                        <!-- Key Bullet Points -->
                        <ul
                            class="flex flex-col gap-2.5 bg-slate-50 p-5 rounded-2xl border border-slate-100/60 text-sm font-sans font-medium text-[#4D5964]">
                            <li class="flex items-start gap-2.5">
                                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor"
                                    stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Verbetert direct de grip op dunne voorwerpen</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor"
                                    stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Set van 8 stuks in 3 verschillende binnendiameters</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor"
                                    stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Zacht en comfortabel antislip foam-materiaal</span>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor"
                                    stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span>Eenvoudig met een mesje op de gewenste lengte te snijden</span>
                            </li>
                        </ul>

                        <!-- Options Selectors -->
                        <div class="flex flex-col gap-4 border-t border-slate-100 pt-6">
                            <!-- Option 1: Pack/Set Options -->
                            <div class="flex flex-col gap-2.5">
                                <span class="text-sm font-bold text-[#2C3642] font-sans">Kies Pakketgrootte</span>
                                <div class="flex flex-wrap gap-3">
                                    <button
                                        class="px-4 py-2.5 rounded-xl border-2 border-[#E9A821] bg-white text-[#2C3642] text-sm font-bold font-sans shadow-sm transition-all focus:outline-none">
                                        Set van 8 stuks (€20,00)
                                    </button>
                                    <button
                                        class="px-4 py-2.5 rounded-xl border-2 border-[#E9EEF4] bg-white hover:border-slate-300 text-[#4D5964] hover:text-[#2C3642] text-sm font-semibold font-sans transition-all focus:outline-none opacity-80">
                                        Set van 12 stuks (€27,50)
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Quantity and Add to Cart Row -->
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-4 mt-2">
                            <!-- Quantity Selector -->
                            <div
                                class="flex items-center justify-between border border-[#D0D7DD] bg-white rounded-xl h-14 px-3 w-full sm:w-[150px] flex-shrink-0 shadow-sm">
                                <button onclick="decrementQty()"
                                    class="w-10 h-10 flex items-center justify-center text-[#6F7983] hover:text-[#2C3642] active:scale-90 transition-all focus:outline-none"
                                    aria-label="Aantal verlagen">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3"
                                        viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"></path>
                                    </svg>
                                </button>
                                <span id="qty-input"
                                    class="text-[#2C3642] text-lg font-bold font-sans w-8 text-center select-none">1</span>
                                <button onclick="incrementQty()"
                                    class="w-10 h-10 flex items-center justify-center text-[#6F7983] hover:text-[#2C3642] active:scale-90 transition-all focus:outline-none"
                                    aria-label="Aantal verhogen">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3"
                                        viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                </button>
                            </div>

                            <!-- Add To Cart Button -->
                            <button onclick="addToCartTrigger()"
                                class="relative flex-1 h-14 bg-[#E9A821] hover:bg-[#d09214] text-white text-base font-bold font-sans rounded-xl flex items-center justify-center gap-2 shadow-[0_4px_14px_rgba(233,168,33,0.3)] hover:shadow-[0_6px_20px_rgba(233,168,33,0.4)] active:scale-[0.98] transition-all duration-200 group focus:outline-none overflow-hidden">
                                <!-- Background animation glow -->
                                <div
                                    class="absolute inset-0 w-full h-full bg-gradient-to-r from-transparent via-white/10 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000 ease-out">
                                </div>

                                <span>In winkelwagen</span>
                                <svg class="w-5 h-5 group-hover:translate-x-1.5 transition-transform duration-300"
                                    fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Trust and Guarantees Badge List -->
                        <div
                            class="grid grid-cols-2 gap-4 border-t border-slate-100 pt-6 mt-2 text-xs font-sans font-semibold text-[#4D5964]">
                            <div class="flex items-center gap-2.5">
                                <svg class="w-5 h-5 text-[#E9A821]" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                                    </path>
                                </svg>
                                <span>Veilig & Vertrouwd betalen</span>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <svg class="w-5 h-5 text-[#E9A821]" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4">
                                    </path>
                                </svg>
                                <span>Gratis verzending vanaf €50</span>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <svg class="w-5 h-5 text-[#E9A821]" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H18.5"></path>
                                </svg>
                                <span>30 dagen bedenktijd</span>
                            </div>
                            <div class="flex items-center gap-2.5">
                                <svg class="w-5 h-5 text-[#E9A821]" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z">
                                    </path>
                                </svg>
                                <span>WebwinkelKeur Gecertificeerd</span>
                            </div>
                        </div>
                    </div>

                </div>
            </section>

            <!-- PRODUCT INFORMATION TABS SECTION -->
            <section class="w-full lg:px-[156px] px-6 py-12 bg-white border-t border-slate-100" id="tabs-section">
                <div class="mx-auto flex flex-col gap-8 max-w-5xl">
                    <!-- Tab Buttons Headers -->
                    <div class="flex border-b border-slate-100 gap-8 overflow-x-auto scrollbar-none pb-0.5">
                        <button onclick="switchTab('description')" id="tab-btn-description"
                            class="tab-btn pb-4 text-base font-bold font-sans text-[#E9A821] border-b-2 border-[#E9A821] whitespace-nowrap focus:outline-none transition-all duration-200">
                            Productbeschrijving
                        </button>
                        <button onclick="switchTab('specifications')" id="tab-btn-specifications"
                            class="tab-btn pb-4 text-base font-semibold font-sans text-[#6F7983] border-b-2 border-transparent hover:text-[#2C3642] whitespace-nowrap focus:outline-none transition-all duration-200">
                            Specificaties
                        </button>
                        <button onclick="switchTab('reviews')" id="tab-btn-reviews"
                            class="tab-btn pb-4 text-base font-semibold font-sans text-[#6F7983] border-b-2 border-transparent hover:text-[#2C3642] whitespace-nowrap focus:outline-none transition-all duration-200">
                            Klantbeoordelingen (24)
                        </button>
                    </div>

                    <!-- TAB PANELS CONTENT -->
                    <div>
                        <!-- Description Tab Panel -->
                        <div id="tab-panel-description"
                            class="tab-panel block flex flex-col gap-6 text-[#4D5964] leading-relaxed font-sans text-base">
                            <h3 class="text-xl font-bold text-[#2C3642]">Ergonomische handgreepvergroting voor betere grip
                            </h3>
                            <p>
                                De Vitility handvatverdikkers bieden een uiterst eenvoudige en effectieve oplossing voor het
                                verdikken van de handgrepen van alledaagse voorwerpen. Of het nu gaat om uw favoriete
                                eetbestek, pennen, potloden, tandenborstels of klein handgereedschap; door het aanbrengen
                                van de handvatverdikker vergroot u de greepdiameter, waardoor u minder kracht hoeft te
                                zetten bij het vasthouden van het voorwerp.
                            </p>
                            <p>
                                Dit product is met name geschikt voor mensen die moeite hebben met knijpbewegingen of die
                                last hebben van reumatische klachten, artritis, de ziekte van Parkinson of spierzwakte in de
                                handen. De verdikkers zijn gemaakt van hoogwaardig antislip schuimmateriaal dat zacht
                                aanvoelt maar toch voldoende stevigheid biedt om gecontroleerd te kunnen schrijven of eten.
                            </p>

                            <h4 class="text-base font-bold text-[#2C3642] mt-2">Hoe te gebruiken:</h4>
                            <ol class="list-decimal pl-5 flex flex-col gap-2">
                                <li>Selecteer een van de meegeleverde schuimtubes die het beste past bij de diameter van uw
                                    voorwerp.</li>
                                <li>Snijd de schuimtube indien nodig eenvoudig met een scherp mesje af op de gewenste
                                    lengte.</li>
                                <li>Schuif de verdikker voorzichtig over de handgreep van het voorwerp. Om het schuiven te
                                    vergemakkelijken, kunt u het voorwerp of de binnenkant van de tube licht bevochtigen met
                                    een druppeltje water en zeep.</li>
                            </ol>
                        </div>

                        <!-- Specifications Tab Panel -->
                        <div id="tab-panel-specifications" class="tab-panel hidden">
                            <div class="overflow-hidden border border-slate-100 rounded-2xl">
                                <table class="w-full text-left text-sm font-sans text-[#4D5964]">
                                    <tbody>
                                        <tr class="bg-slate-50/50 border-b border-slate-100">
                                            <th class="py-4 px-6 font-bold text-[#2C3642] w-[300px]">Merk</th>
                                            <td class="py-4 px-6">Vitility</td>
                                        </tr>
                                        <tr class="border-b border-slate-100">
                                            <th class="py-4 px-6 font-bold text-[#2C3642]">Aantal stuks in verpakking</th>
                                            <td class="py-4 px-6">8 handvatverdikkers (tubes)</td>
                                        </tr>
                                        <tr class="bg-slate-50/50 border-b border-slate-100">
                                            <th class="py-4 px-6 font-bold text-[#2C3642]">Lengte per tube</th>
                                            <td class="py-4 px-6">12 cm (eenvoudig zelf in te korten)</td>
                                        </tr>
                                        <tr class="border-b border-slate-100">
                                            <th class="py-4 px-6 font-bold text-[#2C3642]">Binnendiameters inbegrepen</th>
                                            <td class="py-4 px-6">
                                                <ul class="list-disc pl-4">
                                                    <li>3x Small: binnendiameter 6 mm (geel) - ideaal voor pennen, potloden
                                                    </li>
                                                    <li>3x Medium: binnendiameter 9.5 mm (rood) - ideaal voor
                                                        tandenborstels, dun bestek</li>
                                                    <li>2x Large: binnendiameter 12 mm (blauw) - ideaal voor dikker bestek,
                                                        borstels</li>
                                                </ul>
                                            </td>
                                        </tr>
                                        <tr class="bg-slate-50/50 border-b border-slate-100">
                                            <th class="py-4 px-6 font-bold text-[#2C3642]">Buitendiameter</th>
                                            <td class="py-4 px-6">Ongeveer 2.8 cm tot 3.1 cm</td>
                                        </tr>
                                        <tr class="border-b border-slate-100">
                                            <th class="py-4 px-6 font-bold text-[#2C3642]">Materiaal</th>
                                            <td class="py-4 px-6">Gesloten cellig EVA comfort-schuim (waterafstotend)</td>
                                        </tr>
                                        <tr class="bg-slate-50/50 border-b border-slate-100">
                                            <th class="py-4 px-6 font-bold text-[#2C3642]">Reiniging</th>
                                            <td class="py-4 px-6">Wasbaar met de hand in lauw water met milde zeep. Niet
                                                vaatwasserbestendig.</td>
                                        </tr>
                                        <tr>
                                            <th class="py-4 px-6 font-bold text-[#2C3642]">Kleur</th>
                                            <td class="py-4 px-6">Assorti kleuren (Geel, Rood, Blauw)</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Reviews Tab Panel -->
                        <div id="tab-panel-reviews" class="tab-panel hidden flex flex-col gap-8">
                            <!-- Ratings Breakout Header -->
                            <div
                                class="grid grid-cols-1 md:grid-cols-12 gap-8 items-center bg-slate-50 p-6 sm:p-8 rounded-2xl border border-slate-100">
                                <div
                                    class="md:col-span-4 flex flex-col items-center justify-center gap-2 border-b md:border-b-0 md:border-r border-slate-200/60 pb-6 md:pb-0">
                                    <span class="text-[#2C3642] text-5xl font-extrabold font-sans">4.8</span>
                                    <div class="flex items-center text-[#E9A821] my-1">
                                        <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20">
                                            <path
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                            </path>
                                        </svg>
                                        <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20">
                                            <path
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                            </path>
                                        </svg>
                                        <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20">
                                            <path
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                            </path>
                                        </svg>
                                        <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20">
                                            <path
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                            </path>
                                        </svg>
                                        <svg class="w-5 h-5 fill-current" viewBox="0 0 20 20">
                                            <path
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                            </path>
                                        </svg>
                                    </div>
                                    <span class="text-sm text-[#6F7983] font-medium font-sans">Op basis van 24
                                        reviews</span>
                                </div>
                                <div class="md:col-span-5 flex flex-col gap-2">
                                    <!-- 5 stars -->
                                    <div class="flex items-center gap-3 text-xs font-sans text-[#4D5964] font-medium">
                                        <span class="w-12">5 sterren</span>
                                        <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
                                            <div class="h-full bg-[#E9A821] w-[88%] rounded-full"></div>
                                        </div>
                                        <span class="w-8 text-right">88%</span>
                                    </div>
                                    <!-- 4 stars -->
                                    <div class="flex items-center gap-3 text-xs font-sans text-[#4D5964] font-medium">
                                        <span class="w-12">4 sterren</span>
                                        <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
                                            <div class="h-full bg-[#E9A821] w-[8%] rounded-full"></div>
                                        </div>
                                        <span class="w-8 text-right">8%</span>
                                    </div>
                                    <!-- 3 stars -->
                                    <div class="flex items-center gap-3 text-xs font-sans text-[#4D5964] font-medium">
                                        <span class="w-12">3 sterren</span>
                                        <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
                                            <div class="h-full bg-[#E9A821] w-[4%] rounded-full"></div>
                                        </div>
                                        <span class="w-8 text-right">4%</span>
                                    </div>
                                    <!-- 2 stars -->
                                    <div class="flex items-center gap-3 text-xs font-sans text-[#4D5964] font-medium">
                                        <span class="w-12">2 sterren</span>
                                        <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
                                            <div class="h-full bg-[#E9A821] w-0 rounded-full"></div>
                                        </div>
                                        <span class="w-8 text-right">0%</span>
                                    </div>
                                    <!-- 1 star -->
                                    <div class="flex items-center gap-3 text-xs font-sans text-[#4D5964] font-medium">
                                        <span class="w-12">1 ster</span>
                                        <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
                                            <div class="h-full bg-[#E9A821] w-0 rounded-full"></div>
                                        </div>
                                        <span class="w-8 text-right">0%</span>
                                    </div>
                                </div>
                                <div class="md:col-span-3 flex flex-col items-center justify-center">
                                    <button onclick="showReviewAlert()"
                                        class="px-5 py-3 border-2 border-[#E9A821] hover:bg-[#E9A821] text-[#E9A821] hover:text-white font-bold text-sm font-sans rounded-xl transition-all duration-200 shadow-sm active:scale-95 focus:outline-none">
                                        Schrijf een review
                                    </button>
                                </div>
                            </div>

                            <!-- Individual Reviews List -->
                            <div class="flex flex-col gap-6 divider-y divide-slate-100">

                                <!-- Review 1 -->
                                <div class="flex flex-col gap-3 pb-6 border-b border-slate-100">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1.5">
                                        <div class="flex items-center gap-3">
                                            <span class="font-bold text-[#2C3642] text-[15px]">Hendrika V.</span>
                                            <span
                                                class="flex items-center text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M6.267 3.455a.75.75 0 00-.75-.75h-.007a.75.75 0 00-.75.75v.006c0 .41-.334.744-.744.744h-.006a.75.75 0 00-.75.75v.007c0 .41-.334.744-.744.744h-.006a.75.75 0 00-.75.75v.2c0 .412-.339.743-.751.743H1.75a.75.75 0 00-.75.75v.007c0 .41-.334.744-.744.744h-.006a.75.75 0 00-.75.75v.006c0 .41-.334.744-.744.744h-.007a.75.75 0 00-.75.75v.007c0 .41-.334.744-.744.744h-.006a.75.75 0 00-.75.75v1.2c0 .41-.334.744-.744.744h-.006a.75.75 0 00-.75.75v.007c0 .41-.334.744-.744.744h-.006a.75.75 0 00-.75.75v.006c0 .41-.334.744-.744.744h-.007a.75.75 0 00-.75.75v.007c0 .41-.334.744-.744.744h-.006a.75.75 0 00-.75.75v.007c0 .412-.339.743-.751.743H1.75a.75.75 0 00-.75.75v1.2c0 .412-.339.743-.751.743H1.75a.75.75 0 00-.75.75v.007c0 .41-.334.744-.744.744h-.006a.75.75 0 00-.75.75v.006c0 .41-.334.744-.744.744h-.007a.75.75 0 00-.75.75v.007c0 .41-.334.744-.744.744H.75"
                                                        clip-rule="evenodd"></path>
                                                </svg>
                                                Geverifieerde koper
                                            </span>
                                        </div>
                                        <span class="text-xs text-[#6F7983] font-medium font-sans">15 april 2026</span>
                                    </div>
                                    <div class="flex text-[#E9A821] gap-0.5">
                                        <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                                            <path
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                            </path>
                                        </svg>
                                        <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                                            <path
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                            </path>
                                        </svg>
                                        <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                                            <path
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                            </path>
                                        </svg>
                                        <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                                            <path
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                            </path>
                                        </svg>
                                        <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                                            <path
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                            </path>
                                        </svg>
                                    </div>
                                    <p class="text-sm font-sans font-medium text-[#4D5964]">
                                        Echt een geweldige oplossing. Mijn moeder van 84 heeft veel last van reuma in haar
                                        vingers en kon haar bestek en tandenborstel bijna niet meer vasthouden. Met deze
                                        handvatverdikkers eet ze weer helemaal zelfstandig! Eenvoudig over het bestek heen
                                        te schuiven met een drupje afwasmiddel. Absolute aanrader!
                                    </p>
                                </div>

                                <!-- Review 2 -->
                                <div class="flex flex-col gap-3 pb-6 border-b border-slate-100">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1.5">
                                        <div class="flex items-center gap-3">
                                            <span class="font-bold text-[#2C3642] text-[15px]">Gerard de B.</span>
                                            <span
                                                class="flex items-center text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded">
                                                Geverifieerde koper
                                            </span>
                                        </div>
                                        <span class="text-xs text-[#6F7983] font-medium font-sans">28 maart 2026</span>
                                    </div>
                                    <div class="flex text-[#E9A821] gap-0.5">
                                        <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                                            <path
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                            </path>
                                        </svg>
                                        <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                                            <path
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                            </path>
                                        </svg>
                                        <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                                            <path
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                            </path>
                                        </svg>
                                        <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                                            <path
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                            </path>
                                        </svg>
                                        <svg class="w-4 h-4 fill-current" viewBox="0 0 20 20">
                                            <path
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                            </path>
                                        </svg>
                                    </div>
                                    <p class="text-sm font-sans font-medium text-[#4D5964]">
                                        Zeer tevreden over. De set bevat verschillende maten, wat heel handig is. Het dunste
                                        gele foam is perfect voor pennen en tekenpotloden, de blauwe gebruiken we voor
                                        groter keukengereedschap. Ze schuiven niet en voelen lekker zacht aan. Snelle
                                        levering door Zeker Gemak!
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- RELATED PRODUCTS SECTION -->
            <section class="w-full lg:px-[156px] px-6 py-16 bg-[#F8FAFC] border-t border-slate-100">
                <div class="mx-auto flex flex-col gap-10">
                    <div class="flex flex-col gap-2">
                        <span class="text-[#E9A821] text-xs font-bold uppercase tracking-widest font-dm">Gerelateerd</span>
                        <h2 class="text-[#2C3642] text-2xl sm:text-3xl font-bold font-sans">Aanbevolen combinaties</h2>
                    </div>

                    <!-- Products Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">

                        <!-- Related 1: Bord met opstaande rand -->
                        <article
                            class="bg-white rounded-2xl border border-slate-100 shadow-[0_4px_20px_rgba(44,54,66,0.02)] overflow-hidden flex flex-col group cursor-pointer hover:-translate-y-1 hover:shadow-md transition-all duration-300">
                            <div
                                class="relative h-[240px] flex items-center justify-center bg-[#F3F5F7] overflow-hidden p-6">
                                <img src="assets/images/products/5125790ec379bcbb23b2d47e8def2d5bd30105ce.png"
                                    alt="Vitility Bord met opstaande rand"
                                    class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-300 mix-blend-multiply" />
                                <div
                                    class="absolute bottom-4 left-4 right-4 translate-y-full opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300 ease-out">
                                    <button onclick="addToCartDirect('Vitility Bord met opstaande rand')"
                                        class="w-full h-11 bg-[#E9A821] hover:bg-[#d09214] text-white text-sm font-bold rounded-xl flex items-center justify-center gap-2 transition-colors">
                                        In winkelwagen
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="p-6 flex flex-col gap-4 flex-1">
                                <div class="flex flex-col gap-1.5">
                                    <p class="text-[#6F7983] text-xs font-semibold">Art. Nr. GR900014</p>
                                    <h3
                                        class="text-[#2C3642] text-lg font-bold leading-snug group-hover:text-[#E9A821] transition-colors line-clamp-1">
                                        Vitility Bord met opstaande rand</h3>
                                </div>
                                <div class="flex items-center justify-between border-t border-slate-100 pt-4 mt-auto">
                                    <span class="text-[#2C3642] text-xl font-extrabold">€20,00</span>
                                    <span class="text-xs text-emerald-600 font-bold bg-emerald-50 px-2 py-1 rounded">Op
                                        voorraad</span>
                                </div>
                            </div>
                        </article>

                        <!-- Related 2: Multi Opener Handy -->
                        <article
                            class="bg-white rounded-2xl border border-slate-100 shadow-[0_4px_20px_rgba(44,54,66,0.02)] overflow-hidden flex flex-col group cursor-pointer hover:-translate-y-1 hover:shadow-md transition-all duration-300">
                            <div
                                class="relative h-[240px] flex items-center justify-center bg-[#F3F5F7] overflow-hidden p-6">
                                <img src="assets/images/products/69bddf9723b9c2ed7f4cfc93aecf3040c4f1a1db.png"
                                    alt="Vitility Multi Opener Handy"
                                    class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-300 mix-blend-multiply" />
                                <div
                                    class="absolute bottom-4 left-4 right-4 translate-y-full opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300 ease-out">
                                    <button onclick="addToCartDirect('Vitility Multi Opener Handy')"
                                        class="w-full h-11 bg-[#E9A821] hover:bg-[#d09214] text-white text-sm font-bold rounded-xl flex items-center justify-center gap-2 transition-colors">
                                        In winkelwagen
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="p-6 flex flex-col gap-4 flex-1">
                                <div class="flex flex-col gap-1.5">
                                    <p class="text-[#6F7983] text-xs font-semibold">Art. Nr. GR900015</p>
                                    <h3
                                        class="text-[#2C3642] text-lg font-bold leading-snug group-hover:text-[#E9A821] transition-colors line-clamp-1">
                                        Vitility Multi Opener Handy</h3>
                                </div>
                                <div class="flex items-center justify-between border-t border-slate-100 pt-4 mt-auto">
                                    <span class="text-[#2C3642] text-xl font-extrabold">€20,00</span>
                                    <span class="text-xs text-emerald-600 font-bold bg-emerald-50 px-2 py-1 rounded">Op
                                        voorraad</span>
                                </div>
                            </div>
                        </article>

                        <!-- Related 3: Polsband wandelstok -->
                        <article
                            class="bg-white rounded-2xl border border-slate-100 shadow-[0_4px_20px_rgba(44,54,66,0.02)] overflow-hidden flex flex-col group cursor-pointer hover:-translate-y-1 hover:shadow-md transition-all duration-300">
                            <div
                                class="relative h-[240px] flex items-center justify-center bg-[#F3F5F7] overflow-hidden p-6">
                                <img src="assets/images/products/9c7cb82f48c5e97051c85e9192ab69a6fe7d59d0.png"
                                    alt="Vitility Polsband voor wandelstok"
                                    class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-300 mix-blend-multiply" />
                                <div
                                    class="absolute bottom-4 left-4 right-4 translate-y-full opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300 ease-out">
                                    <button onclick="addToCartDirect('Vitility Polsband voor wandelstok')"
                                        class="w-full h-11 bg-[#E9A821] hover:bg-[#d09214] text-white text-sm font-bold rounded-xl flex items-center justify-center gap-2 transition-colors">
                                        In winkelwagen
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="p-6 flex flex-col gap-4 flex-1">
                                <div class="flex flex-col gap-1.5">
                                    <p class="text-[#6F7983] text-xs font-semibold">Art. Nr. GR900017</p>
                                    <h3
                                        class="text-[#2C3642] text-lg font-bold leading-snug group-hover:text-[#E9A821] transition-colors line-clamp-1">
                                        Vitility Polsband voor wandelstok</h3>
                                </div>
                                <div class="flex items-center justify-between border-t border-slate-100 pt-4 mt-auto">
                                    <div class="flex items-baseline gap-2">
                                        <span class="text-[#2C3642] text-xl font-extrabold">€20,00</span>
                                        <span class="text-[#6F7983] text-xs line-through">€39,00</span>
                                    </div>
                                    <span class="text-xs text-emerald-600 font-bold bg-emerald-50 px-2 py-1 rounded">Op
                                        voorraad</span>
                                </div>
                            </div>
                        </article>

                    </div>
                </div>
            </section>

            <!-- PRODUCT GALLERY LIGHTBOX MODAL -->
            <div id="gallery-lightbox"
                class="fixed inset-0 z-50 bg-[#2C3642]/95 backdrop-blur-md flex flex-col justify-between p-4 sm:p-8 opacity-0 pointer-events-none transition-all duration-300 ease-out">

                <!-- Lightbox Top Controls Bar -->
                <div class="flex justify-between items-center w-full z-10">
                    <span id="lightbox-index-indicator"
                        class="text-white/80 font-mono text-sm tracking-widest select-none">1 / 4</span>

                    <button onclick="closeLightbox()"
                        class="w-12 h-12 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center shadow-lg transition-colors focus:outline-none"
                        aria-label="Sluit lightbox">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Lightbox Main Stage Area -->
                <div class="flex-1 flex items-center justify-center w-full relative">
                    <!-- Left Arrow -->
                    <button onclick="prevLightboxImage()"
                        class="absolute left-0 sm:left-4 top-1/2 -translate-y-1/2 w-14 h-14 rounded-xl bg-white/5 hover:bg-white/15 text-white/80 hover:text-white flex items-center justify-center transition-all active:scale-95 focus:outline-none"
                        aria-label="Vorige afbeelding">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </button>

                    <!-- Large Screen Image Display -->
                    <div class="max-w-full max-h-[70vh] flex flex-col items-center justify-center p-4">
                        <img id="lightbox-image" src="assets/images/products/ddd3c9cd450f7f848aaad72a4a94b374810e43c4.png"
                            alt="Product Image Fullscreen"
                            class="max-w-full max-h-full object-contain rounded-lg select-none" />
                        <p id="lightbox-caption"
                            class="text-white/90 text-center text-sm font-semibold font-sans mt-4 tracking-wide select-none">
                            Vitility Handvatverdikkers - Set Overzicht</p>
                    </div>

                    <!-- Right Arrow -->
                    <button onclick="nextLightboxImage()"
                        class="absolute right-0 sm:right-4 top-1/2 -translate-y-1/2 w-14 h-14 rounded-xl bg-white/5 hover:bg-white/15 text-white/80 hover:text-white flex items-center justify-center transition-all active:scale-95 focus:outline-none"
                        aria-label="Volgende afbeelding">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"
                            xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                </div>

                <!-- Lightbox Sync thumbnails row -->
                <div class="mx-auto w-full max-w-[480px] flex items-center gap-3 justify-center z-10 pb-2">
                    <button onclick="setGalleryImage(0)"
                        class="lightbox-thumb w-16 h-16 rounded-xl bg-white border-2 border-[#E9A821] p-1 flex items-center justify-center overflow-hidden transition-all focus:outline-none">
                        <img src="assets/images/products/ddd3c9cd450f7f848aaad72a4a94b374810e43c4.png"
                            alt="Lightbox Thumb 1" class="max-w-full max-h-full object-contain" />
                    </button>
                    <button onclick="setGalleryImage(1)"
                        class="lightbox-thumb w-16 h-16 rounded-xl bg-white border-2 border-transparent p-1 flex items-center justify-center overflow-hidden transition-all focus:outline-none">
                        <img src="assets/images/products/5125790ec379bcbb23b2d47e8def2d5bd30105ce.png"
                            alt="Lightbox Thumb 2" class="max-w-full max-h-full object-contain" />
                    </button>
                    <button onclick="setGalleryImage(2)"
                        class="lightbox-thumb w-16 h-16 rounded-xl bg-white border-2 border-transparent p-1 flex items-center justify-center overflow-hidden transition-all focus:outline-none">
                        <img src="assets/images/products/69bddf9723b9c2ed7f4cfc93aecf3040c4f1a1db.png"
                            alt="Lightbox Thumb 3" class="max-w-full max-h-full object-contain" />
                    </button>
                    <button onclick="setGalleryImage(3)"
                        class="lightbox-thumb w-16 h-16 rounded-xl bg-white border-2 border-transparent p-1 flex items-center justify-center overflow-hidden transition-all focus:outline-none">
                        <img src="assets/images/products/9c7cb82f48c5e97051c85e9192ab69a6fe7d59d0.png"
                            alt="Lightbox Thumb 4" class="max-w-full max-h-full object-contain" />
                    </button>
                </div>
            </div>

            <!-- DYNAMIC ADD-TO-CART TOAST SUCCESS NOTIFICATION -->
            <div id="cart-toast"
                class="fixed top-6 right-6 z-50 bg-[#2C3642] text-white py-4 px-6 rounded-2xl shadow-xl flex items-center gap-3 border border-slate-700/60 translate-y-[-100px] opacity-0 pointer-events-none transition-all duration-300 ease-out">
                <div class="w-8 h-8 rounded-full bg-emerald-500 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"
                        xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <div class="flex flex-col">
                    <span class="text-sm font-bold font-sans">Toegevoegd aan winkelwagen!</span>
                    <span id="toast-details" class="text-xs text-[#6F7983] font-medium font-sans">1x Vitility
                        Handvatverdikkers</span>
                </div>
            </div>

            <!-- JavaScript Controller -->
            <script>
                // Core Gallery State
                let activeIndex = 0;
                let activeQty = 1;
                const galleryImages = [
                    "assets/images/products/ddd3c9cd450f7f848aaad72a4a94b374810e43c4.png",
                    "assets/images/products/5125790ec379bcbb23b2d47e8def2d5bd30105ce.png",
                    "assets/images/products/69bddf9723b9c2ed7f4cfc93aecf3040c4f1a1db.png",
                    "assets/images/products/9c7cb82f48c5e97051c85e9192ab69a6fe7d59d0.png"
                ];
                const galleryCaptions = [
                    "Vitility Handvatverdikkers - Set Overzicht",
                    "Vitility Bord met Opstaande Rand - Ergonomisch Tafelgerei",
                    "Vitility Multi Opener Handy - Universele Fles & Pot Opener",
                    "Vitility Polsband voor Wandelstok - Extra Veiligheid"
                ];

                // Gallery Thumbnail Update Logic
                function updateGalleryDOM() {
                    const mainImg = document.getElementById("main-image");
                    const lightboxImg = document.getElementById("lightbox-image");
                    const lightboxCaption = document.getElementById("lightbox-caption");
                    const lightboxIndicator = document.getElementById("lightbox-index-indicator");

                    // Set Main Image src
                    if (mainImg) {
                        mainImg.src = galleryImages[activeIndex];
                        mainImg.alt = galleryCaptions[activeIndex];
                    }

                    // Set Lightbox Image src & caption
                    if (lightboxImg) lightboxImg.src = galleryImages[activeIndex];
                    if (lightboxCaption) lightboxCaption.textContent = galleryCaptions[activeIndex];
                    if (lightboxIndicator) lightboxIndicator.textContent = `${activeIndex + 1} / ${galleryImages.length}`;

                    // Update Normal Page thumbnails active state
                    const normalThumbs = document.querySelectorAll(".gallery-thumb");
                    normalThumbs.forEach((thumb, idx) => {
                        if (idx === activeIndex) {
                            thumb.classList.remove("border-[#E9EEF4]");
                            thumb.classList.add("border-[#E9A821]");
                        } else {
                            thumb.classList.remove("border-[#E9A821]");
                            thumb.classList.add("border-[#E9EEF4]");
                        }
                    });

                    // Update Lightbox thumbnails active state
                    const lightboxThumbs = document.querySelectorAll(".lightbox-thumb");
                    lightboxThumbs.forEach((thumb, idx) => {
                        if (idx === activeIndex) {
                            thumb.classList.remove("border-transparent");
                            thumb.classList.add("border-[#E9A821]");
                        } else {
                            thumb.classList.remove("border-[#E9A821]");
                            thumb.classList.add("border-transparent");
                        }
                    });
                }

                // Gallery Handlers
                function setGalleryImage(index) {
                    activeIndex = index;
                    updateGalleryDOM();
                }

                function nextImage(e) {
                    if (e) e.stopPropagation();
                    activeIndex = (activeIndex + 1) % galleryImages.length;
                    updateGalleryDOM();
                }

                function prevImage(e) {
                    if (e) e.stopPropagation();
                    activeIndex = (activeIndex - 1 + galleryImages.length) % galleryImages.length;
                    updateGalleryDOM();
                }

                // Interactive Zoom effect on hover
                document.addEventListener("DOMContentLoaded", () => {
                    const container = document.getElementById("main-image-container");
                    const img = document.getElementById("main-image");

                    if (container && img) {
                        container.addEventListener("mousemove", (e) => {
                            const rect = container.getBoundingClientRect();
                            const x = ((e.clientX - rect.left) / rect.width) * 100;
                            const y = ((e.clientY - rect.top) / rect.height) * 100;
                            img.style.transformOrigin = `${x}% ${y}%`;
                            img.style.transform = "scale(1.7)";
                        });

                        container.addEventListener("mouseleave", () => {
                            img.style.transformOrigin = "center center";
                            img.style.transform = "scale(1)";
                        });
                    }
                });

                // Lightbox Open / Close Triggers
                function openLightbox() {
                    const lightbox = document.getElementById("gallery-lightbox");
                    if (lightbox) {
                        lightbox.classList.remove("opacity-0", "pointer-events-none");
                        lightbox.classList.add("opacity-100", "pointer-events-auto");
                        document.body.style.overflow = "hidden"; // Prevent scrolling
                        updateGalleryDOM();
                    }
                }

                function closeLightbox() {
                    const lightbox = document.getElementById("gallery-lightbox");
                    if (lightbox) {
                        lightbox.classList.remove("opacity-100", "pointer-events-auto");
                        lightbox.classList.add("opacity-0", "pointer-events-none");
                        document.body.style.overflow = ""; // Re-enable scroll
                    }
                }

                // Lightbox Navigation
                function nextLightboxImage() {
                    activeIndex = (activeIndex + 1) % galleryImages.length;
                    updateGalleryDOM();
                }

                function prevLightboxImage() {
                    activeIndex = (activeIndex - 1 + galleryImages.length) % galleryImages.length;
                    updateGalleryDOM();
                }

                // Keyboard Listeners for Accessibility
                window.addEventListener("keydown", (e) => {
                    const lightbox = document.getElementById("gallery-lightbox");
                    const isOpen = lightbox && !lightbox.classList.contains("opacity-0");

                    if (isOpen) {
                        if (e.key === "Escape") {
                            closeLightbox();
                        } else if (e.key === "ArrowRight") {
                            nextLightboxImage();
                        } else if (e.key === "ArrowLeft") {
                            prevLightboxImage();
                        }
                    }
                });

                // Quantity Counter Logic
                function incrementQty() {
                    activeQty++;
                    if (activeQty > 99) activeQty = 99;
                    document.getElementById("qty-input").textContent = activeQty;
                }

                function decrementQty() {
                    activeQty--;
                    if (activeQty < 1) activeQty = 1;
                    document.getElementById("qty-input").textContent = activeQty;
                }

                // Tabs Switching Logic
                function switchTab(tabId) {
                    // Update tab buttons styles
                    const tabs = ["description", "specifications", "reviews"];
                    tabs.forEach(t => {
                        const btn = document.getElementById(`tab-btn-${t}`);
                        const panel = document.getElementById(`tab-panel-${t}`);

                        if (t === tabId) {
                            // Active styling
                            btn.classList.add("text-[#E9A821]", "border-[#E9A821]");
                            btn.classList.remove("text-[#6F7983]", "border-transparent");
                            // Active panel
                            panel.classList.remove("hidden");
                            panel.classList.add("block");
                        } else {
                            // Inactive styling
                            btn.classList.remove("text-[#E9A821]", "border-[#E9A821]");
                            btn.classList.add("text-[#6F7983]", "border-transparent");
                            // Inactive panel
                            panel.classList.remove("block");
                            panel.classList.add("hidden");
                        }
                    });
                }

                // Add to Cart Notifications
                function addToCartTrigger() {
                    showToast(`${activeQty}x Vitility Handvatverdikkers`);
                }

                function addToCartDirect(productName) {
                    showToast(`1x ${productName}`);
                }

                function showToast(detailsText) {
                    const toast = document.getElementById("cart-toast");
                    const details = document.getElementById("toast-details");
                    if (toast && details) {
                        details.textContent = detailsText;
                        toast.classList.remove("translate-y-[-100px]", "opacity-0", "pointer-events-none");
                        toast.classList.add("translate-y-0", "opacity-100", "pointer-events-auto");

                        setTimeout(() => {
                            toast.classList.remove("translate-y-0", "opacity-100", "pointer-events-auto");
                            toast.classList.add("translate-y-[-100px]", "opacity-0", "pointer-events-none");
                        }, 3000);
                    }
                }

                // Write review alert helper
                function showReviewAlert() {
                    alert("Bedankt voor uw interesse! Reviewinzendingen zijn momenteel gesloten voor onderhoud.");
                }
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