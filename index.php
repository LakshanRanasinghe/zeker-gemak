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
    </header>

    <!-- Mobile Drawer Menu Overlay -->
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

    <!-- Script for mobile drawer controls & logo slider -->
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

            // Bottom Logos Loop Slider (for Mobile/Tablet)
            const track = document.getElementById('logo-slider-track');
            if (track) {
                const parent = track.parentElement;
                let currentIndex = 0;
                let slideInterval;

                function slide() {
                    if (window.innerWidth >= 1024) {
                        track.style.transform = 'none';
                        return;
                    }

                    const width = parent.offsetWidth;
                    const gap = 16; // gap-4 is 16px
                    currentIndex++;

                    // Apply smooth slide transition
                    track.style.transition = 'transform 500ms ease-in-out';
                    const offset = currentIndex * (width + gap);
                    track.style.transform = `translateX(-${offset}px)`;

                    // When reaching the duplicated first slide (Slide index 3)
                    if (currentIndex === 3) {
                        setTimeout(() => {
                            // Snap back to start instantly without visual transition
                            track.style.transition = 'none';
                            track.style.transform = 'translateX(0px)';
                            currentIndex = 0;
                        }, 500);
                    }
                }

                function startSlider() {
                    if (window.innerWidth < 1024) {
                        slideInterval = setInterval(slide, 3000);
                    }
                }

                function stopSlider() {
                    clearInterval(slideInterval);
                }

                startSlider();

                window.addEventListener('resize', () => {
                    stopSlider();
                    track.style.transition = 'none';
                    track.style.transform = 'none';
                    currentIndex = 0;
                    startSlider();
                });
            }
        });
    </script>

    <?php if ($is_direct): ?>
        <!-- BEAUTIFUL HERO SECTION (For Previewing) -->
        <main class="relative w-full overflow-x-hidden">
            <!-- Hero section -->
            <div class="bg-[#FAF6EE]">
                <div
                    class="relative w-full lg:min-h-[846px] lg:px-[156px] px-6 pt-20 pb-16 lg:py-0 flex flex-col lg:flex-row items-center justify-between">
                    <!-- Text Content Wrapper -->
                    <div class="w-full lg:w-[540px] flex flex-col justify-start items-start gap-10 z-10 flex-shrink-0">
                        <div class="w-full flex flex-col justify-start items-start gap-6">
                            <h1
                                class="w-full text-[#2C3642] text-5xl md:text-[84px] font-sans font-bold leading-tight md:leading-[100.80px] break-words">
                                Comfortable<br />living <span class="text-[#E9A821]">aids</span>
                            </h1>
                            <p
                                class="w-full text-[#4D5964] text-[18px] font-sans font-normal leading-[28.80px] break-words">
                                At ZekerGemak, find reliable aids for daily use helping those with ADL limitations,
                                rheumatism,
                                or osteoarthritis live independently and safely. See how small adjustments make a big
                                difference.
                            </p>
                        </div>
                        <!-- Action Buttons -->
                        <div class="flex flex-wrap justify-start items-start gap-4">
                            <a href="#"
                                class="h-14 px-[26px] py-4 bg-[#E9A821] hover:bg-[#d09214] text-white text-[18px] font-sans font-bold leading-[26px] rounded-xl shadow-lg shadow-slate-300/10 flex justify-center items-center gap-2 transition-all duration-200">
                                Shop our collections
                            </a>
                            <a href="#"
                                class="h-14 px-[26px] py-4 bg-transparent hover:bg-white/40 text-[#E9A821] text-[18px] font-sans font-bold leading-[26px] rounded-xl border-[1.50px] border-[#E9A821] flex justify-center items-center gap-2 transition-all duration-200">
                                Contact Us
                            </a>
                        </div>
                    </div>

                    <!-- Tilted Images Wrapper (Universal for Mobile, Tablet, and Desktop) -->
                    <div
                        class="w-full lg:w-auto h-[408px] sm:h-[561px] md:h-[765px] lg:h-auto flex justify-center items-center overflow-hidden lg:overflow-visible mt-16 lg:mt-0 select-none pointer-events-none z-0">
                        <div
                            class="relative w-[905px] h-[1020px] flex-shrink-0 origin-center scale-[0.4] sm:scale-[0.55] md:scale-[0.75] lg:scale-100 xxl:scale-120 transition-transform duration-200 lg:absolute lg:-right-[141.43px] lg:top-1/2 lg:-translate-y-1/2 xxl:-right-[169.72px]">
                            <!-- Background Connecting Line Frame -->
                            <div
                                class="absolute w-[547.74px] h-[463.20px] left-[150px] top-[407.28px] -rotate-[18deg] origin-top-left border-4 border-[#E9A821]">
                            </div>

                            <!-- Bedroom image -->
                            <img class="absolute w-[446.53px] h-[457.98px] left-[338.90px] top-[548.31px] -rotate-[18deg] origin-top-left shadow-2xl rounded-xl object-cover border-4 border-white"
                                src="assets/images/home/hero-4.png" alt="Bedroom Aids" />

                            <!-- Door lock image -->
                            <img class="absolute w-[261.76px] h-[254.57px] left-[29.78px] top-[688.21px] -rotate-[18deg] origin-top-left shadow-2xl rounded-xl object-cover border-4 border-white"
                                src="assets/images/home/hero-3.png" alt="Smart Lock" />

                            <!-- Bathroom accessories image -->
                            <img class="absolute w-[338.05px] h-[320.99px] left-0 top-[254.90px] -rotate-[18deg] origin-top-left shadow-2xl rounded-xl object-cover border-4 border-white"
                                src="assets/images/home/hero-1.png" alt="Bathroom Aids" />

                            <!-- Kitchen/dining image -->
                            <img class="absolute w-[338.05px] h-[320.99px] left-[405.39px] top-[113.43px] -rotate-[18deg] origin-top-left shadow-2xl rounded-xl object-cover border-4 border-white"
                                src="assets/images/home/hero-2.png" alt="Kitchen Aids" />
                        </div>
                    </div>
                </div>

                <!-- Bottom Logos Row -->
                <div
                    class="w-full bg-white border-t border-slate-200/60 px-6 py-8 overflow-hidden flex justify-center items-center mt-0 lg:mt-24">
                    <div class="w-full max-w-6xl overflow-hidden relative">
                        <div id="logo-slider-track"
                            class="flex items-center gap-4 w-max lg:w-full lg:justify-between lg:gap-[96px]">
                            <!-- Original 6 Logos -->
                            <img src="assets/images/logos/logoipsum-1.svg"
                                class="w-[calc((100vw-64px)/2)] lg:w-auto flex-shrink-0 lg:flex-shrink h-8 md:h-10 object-contain hover:opacity-85 transition-opacity"
                                alt="Logoipsum 1" />
                            <img src="assets/images/logos/logoipsum-2.svg"
                                class="w-[calc((100vw-64px)/2)] lg:w-auto flex-shrink-0 lg:flex-shrink h-6 md:h-8 object-contain hover:opacity-85 transition-opacity"
                                alt="Logoipsum 2" />
                            <img src="assets/images/logos/logoipsum-3.svg"
                                class="w-[calc((100vw-64px)/2)] lg:w-auto flex-shrink-0 lg:flex-shrink h-8 md:h-10 object-contain hover:opacity-85 transition-opacity"
                                alt="Logoipsum 3" />
                            <img src="assets/images/logos/logoipsum-4.svg"
                                class="w-[calc((100vw-64px)/2)] lg:w-auto flex-shrink-0 lg:flex-shrink h-8 md:h-10 object-contain hover:opacity-85 transition-opacity"
                                alt="Logoipsum 4" />
                            <img src="assets/images/logos/logoipsum-5.svg"
                                class="w-[calc((100vw-64px)/2)] lg:w-auto flex-shrink-0 lg:flex-shrink h-8 md:h-10 object-contain hover:opacity-85 transition-opacity"
                                alt="Logoipsum 5" />
                            <img src="assets/images/logos/logoipsum-6.png"
                                class="w-[calc((100vw-64px)/2)] lg:w-auto flex-shrink-0 lg:flex-shrink h-8 md:h-10 object-contain hover:opacity-85 transition-opacity"
                                alt="Logoipsum 6" />

                            <!-- Duplicated first 2 logos for infinite loop (Hidden on desktop) -->
                            <img src="assets/images/logos/logoipsum-1.svg"
                                class="w-[calc((100vw-64px)/2)] lg:hidden flex-shrink-0 h-8 md:h-10 object-contain hover:opacity-85 transition-opacity"
                                alt="Logoipsum 1 Duplicate" />
                            <img src="assets/images/logos/logoipsum-2.svg"
                                class="w-[calc((100vw-64px)/2)] lg:hidden flex-shrink-0 h-6 md:h-8 object-contain hover:opacity-85 transition-opacity"
                                alt="Logoipsum 2 Duplicate" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Categories Section -->
            <section class="w-full lg:px-[156px] px-6 py-16">
                <div class="flex flex-wrap justify-center gap-4 lg:gap-6">
                    <!-- Bedroom Card -->
                    <div
                        class="flex flex-col items-center justify-between pt-6 pb-4 px-4 h-[250px] bg-[#F3F5F7] rounded-[16px] overflow-hidden relative group hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300 cursor-pointer w-[calc(50%-8px)] sm:w-[calc((100%-32px)/3)] lg:w-auto lg:flex-1">
                        <span
                            class="text-[#2C3642] text-[20px] font-sans font-bold leading-[30px] text-center z-10 group-hover:text-[#E9A821] transition-colors duration-300">
                            Bedroom
                        </span>
                        <div class="w-full flex justify-center items-end h-[150px] overflow-hidden">
                            <img class="max-h-[130px] w-auto object-contain group-hover:scale-105 transition-transform duration-300 mix-blend-multiply"
                                src="assets/images/categories/cat-1.jpg" alt="Bedroom" />
                        </div>
                    </div>

                    <!-- Kitchen & Dining Card -->
                    <div
                        class="flex flex-col items-center justify-between pt-6 pb-4 px-4 h-[250px] bg-[#F3F5F7] rounded-[16px] overflow-hidden relative group hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300 cursor-pointer w-[calc(50%-8px)] sm:w-[calc((100%-32px)/3)] lg:w-auto lg:flex-1">
                        <span
                            class="text-[#2C3642] text-[20px] font-sans font-bold leading-[30px] text-center z-10 group-hover:text-[#E9A821] transition-colors duration-300">
                            Kitchen & Dining
                        </span>
                        <div class="w-full flex justify-center items-end h-[150px] overflow-hidden">
                            <img class="max-h-[130px] w-auto object-contain group-hover:scale-105 transition-transform duration-300 mix-blend-multiply"
                                src="assets/images/categories/24346c063eeb4bae1ece56d9ba8a20ca64ab2791.jpg"
                                alt="Kitchen & Dining" />
                        </div>
                    </div>

                    <!-- Inside & Outside Card -->
                    <div
                        class="flex flex-col items-center justify-between pt-6 pb-4 px-4 h-[250px] bg-[#F3F5F7] rounded-[16px] overflow-hidden relative group hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300 cursor-pointer w-[calc(50%-8px)] sm:w-[calc((100%-32px)/3)] lg:w-auto lg:flex-1">
                        <span
                            class="text-[#2C3642] text-[20px] font-sans font-bold leading-[30px] text-center z-10 group-hover:text-[#E9A821] transition-colors duration-300">
                            Inside & Outside
                        </span>
                        <div class="w-full flex justify-center items-end h-[150px] overflow-hidden">
                            <img class="max-h-[142px] w-auto object-contain group-hover:scale-105 transition-transform duration-300 mix-blend-multiply"
                                src="assets/images/categories/4f5e507bb3bb3081171328b2eb5b44bac61999f7.png"
                                alt="Inside & Outside" />
                        </div>
                    </div>

                    <!-- Living Room Card -->
                    <div
                        class="flex flex-col items-center justify-between pt-6 pb-4 px-4 h-[250px] bg-[#F3F5F7] rounded-[16px] overflow-hidden relative group hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300 cursor-pointer w-[calc(50%-8px)] sm:w-[calc((100%-32px)/3)] lg:w-auto lg:flex-1">
                        <span
                            class="text-[#2C3642] text-[20px] font-sans font-bold leading-[30px] text-center z-10 group-hover:text-[#E9A821] transition-colors duration-300">
                            Living Room
                        </span>
                        <div class="w-full flex justify-center items-end h-[150px] overflow-hidden">
                            <img class="max-h-[140px] w-auto object-contain group-hover:scale-105 transition-transform duration-300 mix-blend-multiply"
                                src="assets/images/categories/0f4371656ca10f875e8bb034a37e0bc949675849.jpg"
                                alt="Living Room" />
                        </div>
                    </div>

                    <!-- Bathroom Card -->
                    <div
                        class="flex flex-col items-center justify-between pt-6 pb-4 px-4 h-[250px] bg-[#F3F5F7] rounded-[16px] overflow-hidden relative group hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300 cursor-pointer w-[calc(50%-8px)] sm:w-[calc((100%-32px)/3)] lg:w-auto lg:flex-1">
                        <span
                            class="text-[#2C3642] text-[20px] font-sans font-bold leading-[30px] text-center z-10 group-hover:text-[#E9A821] transition-colors duration-300">
                            Bathroom
                        </span>
                        <div class="w-full flex justify-center items-end h-[150px] overflow-hidden">
                            <img class="max-h-[135px] w-auto object-contain group-hover:scale-105 transition-transform duration-300 mix-blend-multiply"
                                src="assets/images/categories/9e1134dbbe1d83bfe2a23ac10d2925801b83ce0a.png"
                                alt="Bathroom" />
                        </div>
                    </div>
                </div>
            </section>

            <!-- Featured Products / Most Purchased -->
            <section class="w-full lg:px-[156px] px-6 py-16 md:py-20 flex flex-col gap-10">

                <!-- Section Header -->
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                    <h2
                        class="w-full sm:w-auto text-center sm:text-left text-[#2C3642] text-3xl md:text-[36px] font-bold font-sans leading-tight md:leading-[54px]">
                        Most purchased</h2>
                    <a href="#"
                        class="w-full sm:w-auto h-12 px-6 py-3 rounded-lg border-[1.5px] border-[#E9A821] text-[#E9A821] text-[16px] font-bold font-sans leading-6 flex items-center justify-center hover:bg-[#E9A821]/10 transition-colors duration-200">
                        Discover more
                    </a>
                </div>

                <!-- Product Grid: 3 columns x 2 rows -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">

                    <article
                        class="bg-[#F3F5F7] rounded-xl border border-[#E9EEF4] shadow-[4px_6px_20px_rgba(109,109,120,0.04)] overflow-hidden flex flex-col group cursor-pointer hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                        <!-- Image area -->
                        <div class="relative h-[280px] flex items-center justify-center bg-[#F3F5F7] overflow-hidden">
                            <img src="assets/images/products/232af3f7e55772773e15990eccf0b2880a965307.png"
                                alt="JNF 16.312 Schuifdeurtrekring ovaal 154 x 29 mm RVS"
                                class="w-[500px] h-[500px] max-w-full max-h-full object-contain group-hover:scale-105 transition-transform duration-300 mix-blend-multiply" />
                            <!-- Sale badge -->
                            <span
                                class="absolute top-5 left-5 px-3 py-1.5 bg-[#2C3642] text-white text-[16px] font-semibold capitalize leading-[17.6px] rounded">Sale</span>
                            <!-- Add to Cart hover overlay -->
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
                        <!-- Info area -->
                        <div class="bg-white border-t border-[#E9EEF4] px-6 pt-5 pb-4 flex flex-col gap-4 flex-1">
                            <div class="flex flex-col gap-2">
                                <p class="text-[#6F7983] text-[16px] font-medium leading-6">Article number: GR900012</p>
                                <h3 class="text-[#2C3642] text-[20px] font-bold leading-7">JNF 16.312 Schuifdeurtrekring
                                    ovaal 154 x 29 mm RVS</h3>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[#2C3642] text-[24px] font-extrabold leading-7">$20.00</span>
                                <span class="text-[#6F7983] text-[14px] font-medium line-through capitalize">$39.00</span>
                            </div>
                        </div>
                    </article>
                    <article
                        class="bg-[#F3F5F7] rounded-xl border border-[#E9EEF4] shadow-[4px_6px_20px_rgba(109,109,120,0.12)] overflow-hidden flex flex-col group cursor-pointer hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                        <!-- Image area -->
                        <div class="relative h-[280px] flex items-center justify-center bg-[#F3F5F7] overflow-hidden">
                            <img src="assets/images/products/5125790ec379bcbb23b2d47e8def2d5bd30105ce.png"
                                alt="Vitility Bord met opstaande rand"
                                class="w-[500px] h-[500px] max-w-full max-h-full object-contain group-hover:scale-105 transition-transform duration-300 mix-blend-multiply" />

                            <!-- Add to Cart hover overlay -->
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
                        <!-- Info area -->
                        <div class="bg-white border-t border-[#E9EEF4] px-6 pt-5 pb-4 flex flex-col gap-4 flex-1">
                            <div class="flex flex-col gap-2">
                                <p class="text-[#6F7983] text-[16px] font-medium leading-6">Article number: GR900012</p>
                                <h3 class="text-[#2C3642] text-[20px] font-bold leading-7">Vitility Bord met opstaande rand
                                </h3>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[#2C3642] text-[24px] font-extrabold leading-7">$20.00</span>
                            </div>
                        </div>
                    </article>
                    <article
                        class="bg-[#F3F5F7] rounded-xl border border-[#E9EEF4] shadow-[4px_6px_20px_rgba(109,109,120,0.04)] overflow-hidden flex flex-col group cursor-pointer hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                        <!-- Image area -->
                        <div class="relative h-[280px] flex items-center justify-center bg-[#F3F5F7] overflow-hidden">
                            <img src="assets/images/products/69bddf9723b9c2ed7f4cfc93aecf3040c4f1a1db.png"
                                alt="Vitility Multi Opener Handy"
                                class="w-[500px] h-[500px] max-w-full max-h-full object-contain group-hover:scale-105 transition-transform duration-300 mix-blend-multiply" />

                            <!-- Add to Cart hover overlay -->
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
                        <!-- Info area -->
                        <div class="bg-white border-t border-[#E9EEF4] px-6 pt-5 pb-4 flex flex-col gap-4 flex-1">
                            <div class="flex flex-col gap-2">
                                <p class="text-[#6F7983] text-[16px] font-medium leading-6">Article number: GR900012</p>
                                <h3 class="text-[#2C3642] text-[20px] font-bold leading-7">Vitility Multi Opener Handy</h3>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[#2C3642] text-[24px] font-extrabold leading-7">$20.00</span>
                            </div>
                        </div>
                    </article>
                    <article
                        class="bg-[#F3F5F7] rounded-xl border border-[#E9EEF4] shadow-[4px_6px_20px_rgba(109,109,120,0.04)] overflow-hidden flex flex-col group cursor-pointer hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                        <!-- Image area -->
                        <div class="relative h-[280px] flex items-center justify-center bg-[#F3F5F7] overflow-hidden">
                            <img src="assets/images/products/7450286542afaa19d6ac6dc7c4352e08c2261add.png"
                                alt="Kruk en stokdoppen 19 mm zwart"
                                class="w-[500px] h-[500px] max-w-full max-h-full object-contain group-hover:scale-105 transition-transform duration-300 mix-blend-multiply" />

                            <!-- Add to Cart hover overlay -->
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
                        <!-- Info area -->
                        <div class="bg-white border-t border-[#E9EEF4] px-6 pt-5 pb-4 flex flex-col gap-4 flex-1">
                            <div class="flex flex-col gap-2">
                                <p class="text-[#6F7983] text-[16px] font-medium leading-6">Article number: GR900012</p>
                                <h3 class="text-[#2C3642] text-[20px] font-bold leading-7">Kruk en stokdoppen 19 mm zwart
                                </h3>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[#2C3642] text-[24px] font-extrabold leading-7">$20.00</span>
                            </div>
                        </div>
                    </article>
                    <article
                        class="bg-[#F3F5F7] rounded-xl border border-[#E9EEF4] shadow-[4px_6px_20px_rgba(109,109,120,0.04)] overflow-hidden flex flex-col group cursor-pointer hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                        <!-- Image area -->
                        <div class="relative h-[280px] flex items-center justify-center bg-[#F3F5F7] overflow-hidden">
                            <img src="assets/images/products/9c7cb82f48c5e97051c85e9192ab69a6fe7d59d0.png"
                                alt="Vitility Polsband voor wandelstok"
                                class="w-[500px] h-[500px] max-w-full max-h-full object-contain group-hover:scale-105 transition-transform duration-300 mix-blend-multiply" />
                            <!-- Sale badge -->
                            <span
                                class="absolute top-5 left-5 px-3 py-1.5 bg-[#2C3642] text-white text-[16px] font-semibold capitalize leading-[17.6px] rounded">Sale</span>
                            <!-- Add to Cart hover overlay -->
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
                        <!-- Info area -->
                        <div class="bg-white border-t border-[#E9EEF4] px-6 pt-5 pb-4 flex flex-col gap-4 flex-1">
                            <div class="flex flex-col gap-2">
                                <p class="text-[#6F7983] text-[16px] font-medium leading-6">Article number: GR900012</p>
                                <h3 class="text-[#2C3642] text-[20px] font-bold leading-7">Vitility Polsband voor wandelstok
                                </h3>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[#2C3642] text-[24px] font-extrabold leading-7">$20.00</span>
                                <span class="text-[#6F7983] text-[14px] font-medium line-through capitalize">$39.00</span>
                            </div>
                        </div>
                    </article>
                    <article
                        class="bg-[#F3F5F7] rounded-xl border border-[#E9EEF4] shadow-[4px_6px_20px_rgba(109,109,120,0.04)] overflow-hidden flex flex-col group cursor-pointer hover:-translate-y-1 hover:shadow-lg transition-all duration-300">
                        <!-- Image area -->
                        <div class="relative h-[280px] flex items-center justify-center bg-[#F3F5F7] overflow-hidden">
                            <img src="assets/images/products/ddd3c9cd450f7f848aaad72a4a94b374810e43c4.png"
                                alt="Vitility Handvatverdikkers 8 stuks"
                                class="w-[500px] h-[500px] max-w-full max-h-full object-contain group-hover:scale-105 transition-transform duration-300 mix-blend-multiply" />
                            <!-- Sale badge -->
                            <span
                                class="absolute top-5 left-5 px-3 py-1.5 bg-[#2C3642] text-white text-[16px] font-semibold capitalize leading-[17.6px] rounded">Sale</span>
                            <!-- Add to Cart hover overlay -->
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
                        <!-- Info area -->
                        <div class="bg-white border-t border-[#E9EEF4] px-6 pt-5 pb-4 flex flex-col gap-4 flex-1">
                            <div class="flex flex-col gap-2">
                                <p class="text-[#6F7983] text-[16px] font-medium leading-6">Article number: GR900012</p>
                                <h3 class="text-[#2C3642] text-[20px] font-bold leading-7">Vitility Handvatverdikkers 8
                                    stuks</h3>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[#2C3642] text-[24px] font-extrabold leading-7">$20.00</span>
                                <span class="text-[#6F7983] text-[14px] font-medium line-through capitalize">$39.00</span>
                            </div>
                        </div>
                    </article>

                </div>
            </section>

            <!-- Top Categories -->

            <section class="w-full bg-[#F3F5F7] py-16 md:py-20 overflow-hidden">
                <div class="mx-auto">
                    <!-- Heading & Navigation row -->
                    <div class="px-6 lg:px-[156px] flex justify-between items-center mb-10">
                        <h2
                            class="text-[#2C3642] text-3xl md:text-[36px] font-bold font-sans leading-tight md:leading-[54px]">
                            Top categories
                        </h2>
                        <!-- Navigation Buttons -->
                        <div class="flex items-center gap-4">
                            <button id="cat-prev-btn"
                                class="w-11 h-11 rounded-full bg-[#E9EEF4] flex items-center justify-center transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#E9A821] focus:ring-offset-2"
                                aria-label="Previous categories">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M8.5 16.5L4 12L8.5 7.5M4 12H20" stroke="#6F7983" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                            <button id="cat-next-btn"
                                class="w-11 h-11 rounded-full bg-[#E9A821] flex items-center justify-center transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#E9A821] focus:ring-offset-2"
                                aria-label="Next categories">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M15.5 16.5L20 12L15.5 7.5M20 12H4" stroke="#FFFFFF" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Cards Horizontal Container -->
                    <div class="relative w-full">
                        <!-- Left & Right Fade Overlays -->
                        <div
                            class="absolute left-0 top-0 bottom-0 w-8 md:w-32 bg-gradient-to-r from-[#F3F5F7] via-[#F3F5F7]/40 to-transparent pointer-events-none z-10">
                        </div>
                        <div
                            class="absolute right-0 top-0 bottom-0 w-8 md:w-32 bg-gradient-to-l from-[#F3F5F7] via-[#F3F5F7]/40 to-transparent pointer-events-none z-10">
                        </div>

                        <!-- Scroll Container -->
                        <div id="cat-scroll-container"
                            class="w-full overflow-x-auto scroll-smooth scrollbar-none px-6 lg:px-[156px] py-4 flex gap-6">
                            <!-- Card 1: Bath, Shower and Toilet -->
                            <div
                                class="flex-shrink-0 w-[282px] h-[360px] relative overflow-hidden rounded-xl group cursor-pointer shadow-[0_4px_20px_rgba(44,54,66,0.04)] hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300">
                                <img class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                    src="assets/images/categories/c035a5dccc1367a47ce4b39cb7faf9135f7b0b0a.png"
                                    alt="Bath, Shower and Toilet" />
                                <div
                                    class="absolute bottom-0 left-0 right-0 p-5 bg-[#2C3642]/60 backdrop-blur-[5px] flex flex-col justify-start items-start">
                                    <h3 class="text-white text-[20px] font-bold font-sans leading-8">Bath, Shower and Toilet
                                    </h3>
                                </div>
                            </div>

                            <!-- Card 2: Hingers and Locks -->
                            <div
                                class="flex-shrink-0 w-[282px] h-[360px] relative overflow-hidden rounded-xl group cursor-pointer shadow-[0_4px_20px_rgba(44,54,66,0.04)] hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300">
                                <img class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                    src="assets/images/categories/6cd3819b1b5e03f7d0a7ec45313d2860bcbed1f9.png"
                                    alt="Hingers and Locks" />
                                <div
                                    class="absolute bottom-0 left-0 right-0 p-5 bg-[#2C3642]/60 backdrop-blur-[5px] flex flex-col justify-start items-start">
                                    <h3 class="text-white text-[20px] font-bold font-sans leading-8">Hingers and Locks</h3>
                                </div>
                            </div>

                            <!-- Card 3: Ergonomic aids -->
                            <div
                                class="flex-shrink-0 w-[282px] h-[360px] relative overflow-hidden rounded-xl group cursor-pointer shadow-[0_4px_20px_rgba(44,54,66,0.04)] hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300">
                                <img class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                    src="assets/images/categories/60aebd7bc2a829095189279e7bffd914eb257a92.png"
                                    alt="Ergonomic aids" />
                                <div
                                    class="absolute bottom-0 left-0 right-0 p-5 bg-[#2C3642]/60 backdrop-blur-[5px] flex flex-col justify-start items-start">
                                    <h3 class="text-white text-[20px] font-bold font-sans leading-8">Ergonomic aids</h3>
                                </div>
                            </div>

                            <!-- Card 4: Mobility -->
                            <div
                                class="flex-shrink-0 w-[282px] h-[360px] relative overflow-hidden rounded-xl group cursor-pointer shadow-[0_4px_20px_rgba(44,54,66,0.04)] hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300">
                                <img class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                    src="assets/images/categories/b9fc2c2b71679220e505a08fa50c0457c35ff550.png"
                                    alt="Mobility" />
                                <div
                                    class="absolute bottom-0 left-0 right-0 p-5 bg-[#2C3642]/60 backdrop-blur-[5px] flex flex-col justify-start items-start">
                                    <h3 class="text-white text-[20px] font-bold font-sans leading-8">Mobility</h3>
                                </div>
                            </div>

                            <!-- Card 5: Orthopedic aids -->
                            <div
                                class="flex-shrink-0 w-[282px] h-[360px] relative overflow-hidden rounded-xl group cursor-pointer shadow-[0_4px_20px_rgba(44,54,66,0.04)] hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300">
                                <img class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                    src="assets/images/categories/10ca0497a9d82065628fa40404ae04cf23491ab5.png"
                                    alt="Orthopedic aids" />
                                <div
                                    class="absolute bottom-0 left-0 right-0 p-5 bg-[#2C3642]/60 backdrop-blur-[5px] flex flex-col justify-start items-start">
                                    <h3 class="text-white text-[20px] font-bold font-sans leading-8">Orthopedic aids</h3>
                                </div>
                            </div>

                            <!-- Card 6: Care -->
                            <div
                                class="flex-shrink-0 w-[282px] h-[360px] relative overflow-hidden rounded-xl group cursor-pointer shadow-[0_4px_20px_rgba(44,54,66,0.04)] hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300">
                                <img class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                    src="assets/images/categories/f4f4ca4761be78f075080049a0e3e927473a6ee2.png"
                                    alt="Care" />
                                <div
                                    class="absolute bottom-0 left-0 right-0 p-5 bg-[#2C3642]/60 backdrop-blur-[5px] flex flex-col justify-start items-start">
                                    <h3 class="text-white text-[20px] font-bold font-sans leading-8">Care</h3>
                                </div>
                            </div>

                            <!-- Card 7: Mobility -->
                            <div
                                class="flex-shrink-0 w-[282px] h-[360px] relative overflow-hidden rounded-xl group cursor-pointer shadow-[0_4px_20px_rgba(44,54,66,0.04)] hover:shadow-xl hover:-translate-y-1.5 transition-all duration-300">
                                <img class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                    src="assets/images/categories/b9fc2c2b71679220e505a08fa50c0457c35ff550.png"
                                    alt="Mobility" />
                                <div
                                    class="absolute bottom-0 left-0 right-0 p-5 bg-[#2C3642]/60 backdrop-blur-[5px] flex flex-col justify-start items-start">
                                    <h3 class="text-white text-[20px] font-bold font-sans leading-8">Mobility</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- JS Script for Carousel Scrolling -->
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const container = document.getElementById('cat-scroll-container');
                        const prevBtn = document.getElementById('cat-prev-btn');
                        const nextBtn = document.getElementById('cat-next-btn');

                        if (container && prevBtn && nextBtn) {
                            const scrollAmount = 306; // Card width (282) + gap (24)

                            prevBtn.addEventListener('click', () => {
                                container.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
                            });

                            nextBtn.addEventListener('click', () => {
                                container.scrollBy({ left: scrollAmount, behavior: 'smooth' });
                            });

                            const updateButtons = () => {
                                const scrollLeft = container.scrollLeft;
                                const maxScrollLeft = container.scrollWidth - container.clientWidth;

                                if (scrollLeft <= 5) {
                                    prevBtn.classList.add('opacity-50', 'cursor-not-allowed');
                                    prevBtn.classList.remove('hover:bg-slate-200');
                                } else {
                                    prevBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                                    prevBtn.classList.add('hover:bg-slate-200');
                                }

                                if (scrollLeft >= maxScrollLeft - 5) {
                                    nextBtn.classList.add('opacity-50', 'cursor-not-allowed');
                                    nextBtn.classList.remove('hover:bg-[#d09214]');
                                } else {
                                    nextBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                                    nextBtn.classList.add('hover:bg-[#d09214]');
                                }
                            };

                            container.addEventListener('scroll', updateButtons);
                            window.addEventListener('resize', updateButtons);
                            // Run once on load to init button states
                            setTimeout(updateButtons, 100);
                        }
                    });
                </script>
            </section>

            <!-- Why Choose Us -->
            <section class="w-full bg-white py-16 px-6 lg:px-[156px] font-sans">
                <!-- Top Row (4 Columns) -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-0">
                    <!-- Column 1 -->
                    <div class="flex flex-col gap-3 lg:pr-8 lg:border-r lg:border-[#E9EEF4] last:border-r-0">
                        <h3 class="text-[#2C3642] text-xl font-bold font-sans leading-6">Home aids</h3>
                        <p class="text-[#4D5964] text-base font-medium font-sans leading-[22.40px]">
                            Simplify daily tasks like cooking and cleaning with our ergonomic household aids and smart
                            utensils that offer support and require less effort.
                        </p>
                    </div>
                    <!-- Column 2 -->
                    <div class="flex flex-col gap-3 lg:px-8 lg:border-r lg:border-[#E9EEF4] last:border-r-0">
                        <h3 class="text-[#2C3642] text-xl font-bold font-sans leading-6">Mobility and movement</h3>
                        <p class="text-[#4D5964] text-base font-medium font-sans leading-[22.40px]">
                            Move confidently indoors and outdoors with our mobility aids: rollators, walking sticks, and
                            frames. They enhance movement and reduce fall risk.
                        </p>
                    </div>
                    <!-- Column 3 -->
                    <div class="flex flex-col gap-3 lg:px-8 lg:border-r lg:border-[#E9EEF4] last:border-r-0">
                        <h3 class="text-[#2C3642] text-xl font-bold font-sans leading-6">Sitting and sleeping aids</h3>
                        <p class="text-[#4D5964] text-base font-medium font-sans leading-[22.40px]">
                            Simplify daily tasks like cooking and cleaning with our ergonomic tools and household aids.
                            Consider smart utensils that require less effort.
                        </p>
                    </div>
                    <!-- Column 4 -->
                    <div class="flex flex-col gap-3 lg:pl-8 last:border-r-0">
                        <h3 class="text-[#2C3642] text-xl font-bold font-sans leading-6">Bathroom safety</h3>
                        <p class="text-[#4D5964] text-base font-medium font-sans leading-[22.40px]">
                            Simplify daily tasks like cooking and cleaning with our ergonomic tools. Consider smart utensils
                            that need less effort and offer support.
                        </p>
                    </div>
                </div>

                <!-- Divider -->
                <div class="w-full h-px bg-[#E9EEF4] my-10"></div>

                <!-- Bottom Row (Content & Image) -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                    <!-- Left: Text and List -->
                    <div class="flex flex-col gap-10">
                        <div class="flex flex-col gap-6">
                            <h2 class="text-[#2C3642] text-3xl md:text-[36px] font-bold font-sans leading-[54px]">
                                Why choose <span class="text-[#E9A821]">ZekerGemak?</span>
                            </h2>
                            <ul class="flex flex-col gap-5">
                                <li class="flex items-start gap-4">
                                    <svg class="w-6 h-6 flex-shrink-0 mt-0.5" width="22" height="22" viewBox="0 0 22 22"
                                        fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M20.7987 9.00341C21.2554 11.2447 20.9299 13.5748 19.8765 15.6052C18.8231 17.6356 17.1056 19.2435 15.0102 20.1607C12.9148 21.078 10.5683 21.2492 8.36196 20.6458C6.15563 20.0424 4.22285 18.7008 2.88593 16.8448C1.54902 14.9889 0.88878 12.7306 1.01532 10.4468C1.14186 8.16294 2.04754 5.9915 3.58131 4.29458C5.11508 2.59766 7.18424 1.47784 9.44372 1.12186C11.7032 0.765884 14.0164 1.19527 15.9977 2.33841"
                                            stroke="#00A63E" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                        <path d="M7.99805 10.0039L10.998 13.0039L20.998 3.00391" stroke="#00A63E"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span class="text-[#4D5964] text-lg font-bold font-sans leading-7">
                                        Wide range of aids for the home, mobility, sleeping, and bathroom.
                                    </span>
                                </li>
                                <li class="flex items-start gap-4">
                                    <svg class="w-6 h-6 flex-shrink-0 mt-0.5" width="22" height="22" viewBox="0 0 22 22"
                                        fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M20.7987 9.00341C21.2554 11.2447 20.9299 13.5748 19.8765 15.6052C18.8231 17.6356 17.1056 19.2435 15.0102 20.1607C12.9148 21.078 10.5683 21.2492 8.36196 20.6458C6.15563 20.0424 4.22285 18.7008 2.88593 16.8448C1.54902 14.9889 0.88878 12.7306 1.01532 10.4468C1.14186 8.16294 2.04754 5.9915 3.58131 4.29458C5.11508 2.59766 7.18424 1.47784 9.44372 1.12186C11.7032 0.765884 14.0164 1.19527 15.9977 2.33841"
                                            stroke="#00A63E" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                        <path d="M7.99805 10.0039L10.998 13.0039L20.998 3.00391" stroke="#00A63E"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span class="text-[#4D5964] text-lg font-bold font-sans leading-7">
                                        Quality & reliability – carefully selected products.
                                    </span>
                                </li>
                                <li class="flex items-start gap-4">
                                    <svg class="w-6 h-6 flex-shrink-0 mt-0.5" width="22" height="22" viewBox="0 0 22 22"
                                        fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M20.7987 9.00341C21.2554 11.2447 20.9299 13.5748 19.8765 15.6052C18.8231 17.6356 17.1056 19.2435 15.0102 20.1607C12.9148 21.078 10.5683 21.2492 8.36196 20.6458C6.15563 20.0424 4.22285 18.7008 2.88593 16.8448C1.54902 14.9889 0.88878 12.7306 1.01532 10.4468C1.14186 8.16294 2.04754 5.9915 3.58131 4.29458C5.11508 2.59766 7.18424 1.47784 9.44372 1.12186C11.7032 0.765884 14.0164 1.19527 15.9977 2.33841"
                                            stroke="#00A63E" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                        <path d="M7.99805 10.0039L10.998 13.0039L20.998 3.00391" stroke="#00A63E"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span class="text-[#4D5964] text-lg font-bold font-sans leading-7">
                                        Practical advice and clear product information for the right choice.
                                    </span>
                                </li>
                                <li class="flex items-start gap-4">
                                    <svg class="w-6 h-6 flex-shrink-0 mt-0.5" width="22" height="22" viewBox="0 0 22 22"
                                        fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M20.7987 9.00341C21.2554 11.2447 20.9299 13.5748 19.8765 15.6052C18.8231 17.6356 17.1056 19.2435 15.0102 20.1607C12.9148 21.078 10.5683 21.2492 8.36196 20.6458C6.15563 20.0424 4.22285 18.7008 2.88593 16.8448C1.54902 14.9889 0.88878 12.7306 1.01532 10.4468C1.14186 8.16294 2.04754 5.9915 3.58131 4.29458C5.11508 2.59766 7.18424 1.47784 9.44372 1.12186C11.7032 0.765884 14.0164 1.19527 15.9977 2.33841"
                                            stroke="#00A63E" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                        <path d="M7.99805 10.0039L10.998 13.0039L20.998 3.00391" stroke="#00A63E"
                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span class="text-[#4D5964] text-lg font-bold font-sans leading-7">
                                        Get started quickly – order directly and try it at home.
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <!-- Benefits block -->
                        <div class="pl-4 border-l-2 border-[#E9A821] flex flex-col gap-1">
                            <p class="text-lg leading-7 text-[#4D5964] font-medium font-sans">
                                <strong class="text-[#2C3642] font-semibold">Benefits:</strong> Comfortable living, greater
                                independence, and extra safety in and around the house. View all products or go directly to
                                bathroom aids, mobility, sitting & sleeping aids, or aids for the home.
                            </p>
                        </div>
                    </div>

                    <!-- Right: Image -->
                    <div class="w-full">
                        <img class="w-full h-auto rounded-[16px] shadow-[4px_6px_20px_rgba(109,109,120,0.08)] border-2 border-white object-cover"
                            src="assets/images/home/e789105b693b8395cc970d60825d19eb49dabcf3.png"
                            alt="Happy family sitting on the floor in their new home" />
                    </div>
                </div>
            </section>

            <!-- Testimonial section -->
            <section class="w-full bg-[#F3F5F7] py-20 px-6 lg:px-[156px]">
                <div class="mx-auto flex flex-col gap-10">
                    <!-- Heading & Navigation row -->
                    <div
                        class="w-full flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6 sm:gap-0">
                        <h2
                            class="text-[#2C3642] text-3xl md:text-[36px] font-bold font-sans leading-tight md:leading-[54px]">
                            What our customers are saying
                        </h2>
                        <!-- Navigation Buttons -->
                        <div class="flex items-center gap-4">
                            <button id="testimonial-prev-btn"
                                class="w-11 h-11 rounded-full bg-[#E9EEF4] flex items-center justify-center hover:bg-slate-200 active:scale-95 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#E9A821] focus:ring-offset-2 disabled:opacity-40 disabled:cursor-not-allowed"
                                aria-label="Previous testimonial">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M8.5 16.5L4 12L8.5 7.5M4 12H20" stroke="#6F7983" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                            <button id="testimonial-next-btn"
                                class="w-11 h-11 rounded-full bg-[#E9A821] flex items-center justify-center hover:bg-[#d09214] active:scale-95 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[#E9A821] focus:ring-offset-2 disabled:opacity-40 disabled:cursor-not-allowed"
                                aria-label="Next testimonial">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path d="M15.5 16.5L20 12L15.5 7.5M20 12H4" stroke="#FFFFFF" stroke-width="1.5"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Overflow wrapper -->
                    <div class="overflow-hidden">
                        <!-- Slides track -->
                        <div id="testimonial-track" class="flex gap-6 transition-transform duration-500 ease-in-out">

                            <!-- Testimonial 1 -->
                            <div
                                class="testimonial-slide flex-shrink-0 relative bg-white rounded-xl p-6 md:p-8 flex flex-col gap-10 justify-between shadow-[4px_6px_20px_rgba(109,109,120,0.04)] border border-slate-100/50 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 group">
                                <div class="flex flex-col gap-6">
                                    <div class="flex items-center gap-1">
                                        <?php for ($i = 0; $i < 5; $i++): ?>
                                            <svg width="28" height="28" viewBox="0 0 28 28" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                    d="M13.1921 1.94244C13.491 1.22381 14.509 1.22381 14.8079 1.94244L17.4978 8.40973C17.6238 8.71268 17.9087 8.91968 18.2358 8.9459L25.2178 9.50564C25.9936 9.56784 26.3082 10.536 25.7171 11.0424L20.3975 15.5991C20.1484 15.8126 20.0395 16.1475 20.1157 16.4667L21.7409 23.2799C21.9215 24.037 21.0979 24.6353 20.4336 24.2296L14.4561 20.5786C14.1761 20.4076 13.8239 20.4076 13.5439 20.5786L7.56635 24.2296C6.90214 24.6353 6.07854 24.037 6.25913 23.2799L7.88434 16.4667C7.96047 16.1475 7.85164 15.8126 7.60245 15.5991L2.28292 11.0424C1.69183 10.536 2.00641 9.56784 2.78223 9.50564L9.76422 8.9459C10.0913 8.91968 10.3762 8.71268 10.5022 8.40973L13.1921 1.94244Z"
                                                    fill="#E9A821" />
                                            </svg>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="border-l-2 border-[#E9A821] pl-6">
                                        <p
                                            class="text-[#4D5964] text-lg md:text-[20px] font-sans italic font-semibold leading-[32px]">
                                            “The grab bars I ordered arrived quickly and were exactly as described.
                                            Installing them gave my mother so much more confidence in the bathroom.
                                            Wonderful quality!”
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <img class="w-[60px] h-[60px] rounded-full object-cover select-none"
                                        src="assets/icons/Image.svg" alt="Jenny Wilson" />
                                    <div class="flex flex-col">
                                        <span
                                            class="text-[#2C3642] text-[22px] md:text-2xl font-bold font-sans leading-tight">Jenny
                                            Wilson</span>
                                        <span
                                            class="text-[#6F7983] text-sm md:text-base font-medium font-sans leading-normal">Fashion
                                            Designer</span>
                                    </div>
                                </div>
                                <svg class="absolute right-6 bottom-6 w-[85px] h-[86px] pointer-events-none select-none transition-transform duration-300 group-hover:scale-110"
                                    width="85" height="86" viewBox="0 0 85 86" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M27.0123 40.7747C21.4625 40.7747 17.7084 36.8617 17.7084 31.0639C17.7084 25.8466 22.015 21.5 27.8482 21.5C34.2373 21.5 38.9584 26.7173 38.9584 34.6867C38.9584 52.8004 25.9038 60.0459 17.7084 60.9167V52.9473C23.2652 51.9332 29.5127 46.2823 29.7925 40.0473C29.5127 40.1907 28.4042 40.7747 27.0123 40.7747Z"
                                        fill="#E9EEF4" />
                                    <path
                                        d="M55.3457 40.7747C49.7924 40.7747 46.0417 36.8617 46.0417 31.0639C46.0417 25.8466 50.3484 21.5 56.1815 21.5C62.5707 21.5 67.2917 26.7173 67.2917 34.6867C67.2917 52.8004 54.2372 60.0459 46.0417 60.9167V52.9473C51.5986 51.9332 57.8461 46.2823 58.1259 40.0473C57.8461 40.1907 56.7376 40.7747 55.3457 40.7747Z"
                                        fill="#E9EEF4" />
                                </svg>
                            </div>

                            <!-- Testimonial 2 -->
                            <div
                                class="testimonial-slide flex-shrink-0 relative bg-white rounded-xl p-6 md:p-8 flex flex-col gap-10 justify-between shadow-[4px_6px_20px_rgba(109,109,120,0.04)] border border-slate-100/50 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 group">
                                <div class="flex flex-col gap-6">
                                    <div class="flex items-center gap-1">
                                        <?php for ($i = 0; $i < 5; $i++): ?>
                                            <svg width="28" height="28" viewBox="0 0 28 28" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                    d="M13.1921 1.94244C13.491 1.22381 14.509 1.22381 14.8079 1.94244L17.4978 8.40973C17.6238 8.71268 17.9087 8.91968 18.2358 8.9459L25.2178 9.50564C25.9936 9.56784 26.3082 10.536 25.7171 11.0424L20.3975 15.5991C20.1484 15.8126 20.0395 16.1475 20.1157 16.4667L21.7409 23.2799C21.9215 24.037 21.0979 24.6353 20.4336 24.2296L14.4561 20.5786C14.1761 20.4076 13.8239 20.4076 13.5439 20.5786L7.56635 24.2296C6.90214 24.6353 6.07854 24.037 6.25913 23.2799L7.88434 16.4667C7.96047 16.1475 7.85164 15.8126 7.60245 15.5991L2.28292 11.0424C1.69183 10.536 2.00641 9.56784 2.78223 9.50564L9.76422 8.9459C10.0913 8.91968 10.3762 8.71268 10.5022 8.40973L13.1921 1.94244Z"
                                                    fill="#E9A821" />
                                            </svg>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="border-l-2 border-[#E9A821] pl-6">
                                        <p
                                            class="text-[#4D5964] text-lg md:text-[20px] font-sans italic font-semibold leading-[32px]">
                                            “I’ve been searching for a good walking cane for months. ZekerGemak had exactly
                                            what I needed at a fair price. The whole ordering process was smooth and
                                            delivery was fast.”
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <img class="w-[60px] h-[60px] rounded-full object-cover select-none"
                                        src="assets/icons/Image (1).svg" alt="Cameron Williamson" />
                                    <div class="flex flex-col">
                                        <span
                                            class="text-[#2C3642] text-[22px] md:text-2xl font-bold font-sans leading-tight">Cameron
                                            Williamson</span>
                                        <span
                                            class="text-[#6F7983] text-sm md:text-base font-medium font-sans leading-normal">Retired
                                            Engineer</span>
                                    </div>
                                </div>
                                <svg class="absolute right-6 bottom-6 w-[85px] h-[86px] pointer-events-none select-none transition-transform duration-300 group-hover:scale-110"
                                    width="85" height="86" viewBox="0 0 85 86" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M27.0123 40.7747C21.4625 40.7747 17.7084 36.8617 17.7084 31.0639C17.7084 25.8466 22.015 21.5 27.8482 21.5C34.2373 21.5 38.9584 26.7173 38.9584 34.6867C38.9584 52.8004 25.9038 60.0459 17.7084 60.9167V52.9473C23.2652 51.9332 29.5127 46.2823 29.7925 40.0473C29.5127 40.1907 28.4042 40.7747 27.0123 40.7747Z"
                                        fill="#E9EEF4" />
                                    <path
                                        d="M55.3457 40.7747C49.7924 40.7747 46.0417 36.8617 46.0417 31.0639C46.0417 25.8466 50.3484 21.5 56.1815 21.5C62.5707 21.5 67.2917 26.7173 67.2917 34.6867C67.2917 52.8004 54.2372 60.0459 46.0417 60.9167V52.9473C51.5986 51.9332 57.8461 46.2823 58.1259 40.0473C57.8461 40.1907 56.7376 40.7747 55.3457 40.7747Z"
                                        fill="#E9EEF4" />
                                </svg>
                            </div>

                            <!-- Testimonial 3 -->
                            <div
                                class="testimonial-slide flex-shrink-0 relative bg-white rounded-xl p-6 md:p-8 flex flex-col gap-10 justify-between shadow-[4px_6px_20px_rgba(109,109,120,0.04)] border border-slate-100/50 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 group">
                                <div class="flex flex-col gap-6">
                                    <div class="flex items-center gap-1">
                                        <?php for ($i = 0; $i < 5; $i++): ?>
                                            <svg width="28" height="28" viewBox="0 0 28 28" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                    d="M13.1921 1.94244C13.491 1.22381 14.509 1.22381 14.8079 1.94244L17.4978 8.40973C17.6238 8.71268 17.9087 8.91968 18.2358 8.9459L25.2178 9.50564C25.9936 9.56784 26.3082 10.536 25.7171 11.0424L20.3975 15.5991C20.1484 15.8126 20.0395 16.1475 20.1157 16.4667L21.7409 23.2799C21.9215 24.037 21.0979 24.6353 20.4336 24.2296L14.4561 20.5786C14.1761 20.4076 13.8239 20.4076 13.5439 20.5786L7.56635 24.2296C6.90214 24.6353 6.07854 24.037 6.25913 23.2799L7.88434 16.4667C7.96047 16.1475 7.85164 15.8126 7.60245 15.5991L2.28292 11.0424C1.69183 10.536 2.00641 9.56784 2.78223 9.50564L9.76422 8.9459C10.0913 8.91968 10.3762 8.71268 10.5022 8.40973L13.1921 1.94244Z"
                                                    fill="#E9A821" />
                                            </svg>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="border-l-2 border-[#E9A821] pl-6">
                                        <p
                                            class="text-[#4D5964] text-lg md:text-[20px] font-sans italic font-semibold leading-[32px]">
                                            “After my hip surgery, ZekerGemak was a lifesaver. The selection of recovery
                                            aids is incredible and the staff were so helpful in finding the right products
                                            for my recovery.”
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <img class="w-[60px] h-[60px] rounded-full object-cover select-none"
                                        src="assets/icons/Image.svg" alt="Sandra Bakker" />
                                    <div class="flex flex-col">
                                        <span
                                            class="text-[#2C3642] text-[22px] md:text-2xl font-bold font-sans leading-tight">Sandra
                                            Bakker</span>
                                        <span
                                            class="text-[#6F7983] text-sm md:text-base font-medium font-sans leading-normal">Physical
                                            Therapist</span>
                                    </div>
                                </div>
                                <svg class="absolute right-6 bottom-6 w-[85px] h-[86px] pointer-events-none select-none transition-transform duration-300 group-hover:scale-110"
                                    width="85" height="86" viewBox="0 0 85 86" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M27.0123 40.7747C21.4625 40.7747 17.7084 36.8617 17.7084 31.0639C17.7084 25.8466 22.015 21.5 27.8482 21.5C34.2373 21.5 38.9584 26.7173 38.9584 34.6867C38.9584 52.8004 25.9038 60.0459 17.7084 60.9167V52.9473C23.2652 51.9332 29.5127 46.2823 29.7925 40.0473C29.5127 40.1907 28.4042 40.7747 27.0123 40.7747Z"
                                        fill="#E9EEF4" />
                                    <path
                                        d="M55.3457 40.7747C49.7924 40.7747 46.0417 36.8617 46.0417 31.0639C46.0417 25.8466 50.3484 21.5 56.1815 21.5C62.5707 21.5 67.2917 26.7173 67.2917 34.6867C67.2917 52.8004 54.2372 60.0459 46.0417 60.9167V52.9473C51.5986 51.9332 57.8461 46.2823 58.1259 40.0473C57.8461 40.1907 56.7376 40.7747 55.3457 40.7747Z"
                                        fill="#E9EEF4" />
                                </svg>
                            </div>

                            <!-- Testimonial 4 -->
                            <div
                                class="testimonial-slide flex-shrink-0 relative bg-white rounded-xl p-6 md:p-8 flex flex-col gap-10 justify-between shadow-[4px_6px_20px_rgba(109,109,120,0.04)] border border-slate-100/50 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 group">
                                <div class="flex flex-col gap-6">
                                    <div class="flex items-center gap-1">
                                        <?php for ($i = 0; $i < 5; $i++): ?>
                                            <svg width="28" height="28" viewBox="0 0 28 28" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                    d="M13.1921 1.94244C13.491 1.22381 14.509 1.22381 14.8079 1.94244L17.4978 8.40973C17.6238 8.71268 17.9087 8.91968 18.2358 8.9459L25.2178 9.50564C25.9936 9.56784 26.3082 10.536 25.7171 11.0424L20.3975 15.5991C20.1484 15.8126 20.0395 16.1475 20.1157 16.4667L21.7409 23.2799C21.9215 24.037 21.0979 24.6353 20.4336 24.2296L14.4561 20.5786C14.1761 20.4076 13.8239 20.4076 13.5439 20.5786L7.56635 24.2296C6.90214 24.6353 6.07854 24.037 6.25913 23.2799L7.88434 16.4667C7.96047 16.1475 7.85164 15.8126 7.60245 15.5991L2.28292 11.0424C1.69183 10.536 2.00641 9.56784 2.78223 9.50564L9.76422 8.9459C10.0913 8.91968 10.3762 8.71268 10.5022 8.40973L13.1921 1.94244Z"
                                                    fill="#E9A821" />
                                            </svg>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="border-l-2 border-[#E9A821] pl-6">
                                        <p
                                            class="text-[#4D5964] text-lg md:text-[20px] font-sans italic font-semibold leading-[32px]">
                                            “Ordered the ergonomic kitchen tools as a gift for my father-in-law. He
                                            absolutely loves them! Great packaging, speedy shipping, and the quality
                                            exceeded our expectations.”
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <img class="w-[60px] h-[60px] rounded-full object-cover select-none"
                                        src="assets/icons/Image (1).svg" alt="Lisa de Vries" />
                                    <div class="flex flex-col">
                                        <span
                                            class="text-[#2C3642] text-[22px] md:text-2xl font-bold font-sans leading-tight">Lisa
                                            de Vries</span>
                                        <span
                                            class="text-[#6F7983] text-sm md:text-base font-medium font-sans leading-normal">Home
                                            Care Specialist</span>
                                    </div>
                                </div>
                                <svg class="absolute right-6 bottom-6 w-[85px] h-[86px] pointer-events-none select-none transition-transform duration-300 group-hover:scale-110"
                                    width="85" height="86" viewBox="0 0 85 86" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M27.0123 40.7747C21.4625 40.7747 17.7084 36.8617 17.7084 31.0639C17.7084 25.8466 22.015 21.5 27.8482 21.5C34.2373 21.5 38.9584 26.7173 38.9584 34.6867C38.9584 52.8004 25.9038 60.0459 17.7084 60.9167V52.9473C23.2652 51.9332 29.5127 46.2823 29.7925 40.0473C29.5127 40.1907 28.4042 40.7747 27.0123 40.7747Z"
                                        fill="#E9EEF4" />
                                    <path
                                        d="M55.3457 40.7747C49.7924 40.7747 46.0417 36.8617 46.0417 31.0639C46.0417 25.8466 50.3484 21.5 56.1815 21.5C62.5707 21.5 67.2917 26.7173 67.2917 34.6867C67.2917 52.8004 54.2372 60.0459 46.0417 60.9167V52.9473C51.5986 51.9332 57.8461 46.2823 58.1259 40.0473C57.8461 40.1907 56.7376 40.7747 55.3457 40.7747Z"
                                        fill="#E9EEF4" />
                                </svg>
                            </div>

                        </div>
                    </div>

                    <!-- Pagination Dots -->
                    <div id="testimonial-dots" class="flex items-center justify-center gap-4 mt-4">
                        <button
                            class="testimonial-dot w-3 h-3 rounded-full bg-[#E9A821] outline outline-2 outline-offset-[2px] outline-[#E9A821]/50 transition-all duration-300"
                            aria-label="Go to slide 1"></button>
                        <button class="testimonial-dot w-3 h-3 rounded-full bg-[#CCD1D7] transition-all duration-300"
                            aria-label="Go to slide 2"></button>
                        <button class="testimonial-dot w-3 h-3 rounded-full bg-[#CCD1D7] transition-all duration-300"
                            aria-label="Go to slide 3"></button>
                    </div>
                </div>

                <!-- Testimonial Carousel Script -->
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const track = document.getElementById('testimonial-track');
                        const prevBtn = document.getElementById('testimonial-prev-btn');
                        const nextBtn = document.getElementById('testimonial-next-btn');
                        const dots = document.querySelectorAll('.testimonial-dot');
                        const slides = document.querySelectorAll('.testimonial-slide');

                        if (!track || !prevBtn || !nextBtn) return;

                        let currentIndex = 0;
                        let perPage = window.innerWidth >= 1024 ? 2 : 1;
                        const totalSlides = slides.length;

                        function getMaxIndex() {
                            return totalSlides - perPage;
                        }

                        function setSlideWidths() {
                            perPage = window.innerWidth >= 1024 ? 2 : 1;
                            const gap = 24;
                            const containerWidth = track.parentElement.clientWidth;
                            const slideWidth = perPage === 2
                                ? (containerWidth - gap) / 2
                                : containerWidth;

                            slides.forEach(slide => {
                                slide.style.width = slideWidth + 'px';
                            });
                        }

                        function goTo(index) {
                            const maxIndex = getMaxIndex();
                            currentIndex = Math.max(0, Math.min(index, maxIndex));

                            const gap = 24;
                            const slideWidth = slides[0].offsetWidth;
                            const offset = currentIndex * (slideWidth + gap);
                            track.style.transform = `translateX(-${offset}px)`;

                            dots.forEach((dot, i) => {
                                const isActive = i === currentIndex;
                                dot.classList.toggle('bg-[#E9A821]', isActive);
                                dot.classList.toggle('outline', isActive);
                                dot.classList.toggle('outline-2', isActive);
                                dot.classList.toggle('outline-offset-[2px]', isActive);
                                dot.classList.toggle('outline-[#E9A821]/50', isActive);
                                dot.classList.toggle('bg-[#CCD1D7]', !isActive);
                            });

                            prevBtn.disabled = currentIndex === 0;
                            nextBtn.disabled = currentIndex >= maxIndex;
                        }

                        dots.forEach((dot, i) => {
                            dot.addEventListener('click', () => goTo(i));
                        });

                        prevBtn.addEventListener('click', () => goTo(currentIndex - 1));
                        nextBtn.addEventListener('click', () => goTo(currentIndex + 1));

                        let touchStartX = 0;
                        track.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; }, { passive: true });
                        track.addEventListener('touchend', e => {
                            const diff = touchStartX - e.changedTouches[0].clientX;
                            if (Math.abs(diff) > 50) {
                                diff > 0 ? goTo(currentIndex + 1) : goTo(currentIndex - 1);
                            }
                        }, { passive: true });

                        window.addEventListener('resize', () => {
                            setSlideWidths();
                            goTo(currentIndex);
                        });

                        setSlideWidths();
                        goTo(0);
                    });
                </script>
            </section>

            <!-- Saving Banner -->
            <section class="w-full">
                <div class="w-full flex flex-col md:flex-row items-stretch">
                    <div class="w-full md:w-1/2 min-h-[320px] md:min-h-[406px] relative overflow-hidden">
                        <img class="absolute inset-0 w-full h-full object-cover" src="assets/images/home/saving-banner.jpg"
                            alt="Big savings in interior style" />
                    </div>
                    <div
                        class="w-full md:w-1/2 bg-[#FEDD99] px-6 py-12 md:py-[60px] md:pl-[60px] md:pr-[60px] lg:pr-[156px] flex flex-col justify-center items-start gap-6">
                        <div class="flex flex-col gap-4 w-full">
                            <span
                                class="text-[#6F7983] text-sm md:text-[16px] font-sans font-bold uppercase tracking-wider leading-6">
                                SALE UP TO 35% OFF
                            </span>
                            <h2
                                class="text-[#2C3642] text-3xl md:text-[36px] font-sans font-bold leading-snug md:leading-[46.80px]">
                                Big savings!<br />Unbeatable prices!
                            </h2>
                            <p
                                class="text-[#4D5964] text-base md:text-[18px] font-sans font-medium leading-[28px] max-w-[420px]">
                                It’s more affordable than ever to give every room in your home a stylish makeover
                            </p>
                        </div>
                        <a href="#"
                            class="w-full md:w-auto h-14 px-[26px] py-4 bg-white shadow-[4px_6px_20px_rgba(109,109,120,0.08)] rounded-xl flex md:inline-flex justify-center items-center gap-2 hover:bg-slate-50 hover:shadow-md transition-all duration-200 cursor-pointer active:scale-95">
                            <span class="text-[#2C3642] text-[18px] font-sans font-bold leading-[26px]">
                                Shop our collections
                            </span>
                        </a>
                    </div>
                </div>
            </section>
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