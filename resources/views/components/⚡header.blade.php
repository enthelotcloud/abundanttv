<?php

use Livewire\Component;

new class extends Component
{
    // Simple component for the main header
};
?>

<header
    x-data="{ mobileMenuOpen: false }"
    class="sticky top-0 z-50 w-full border-b border-zinc-200/50 bg-white/70 backdrop-blur-xl dark:border-zinc-800/50 dark:bg-zinc-950/70"
>
    <div class="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 ">

        {{-- Brand & Slogan --}}
        <a href="/" wire:navigate class="group flex items-center gap-3 focus:outline-none">
            {{-- Logo Image --}}
            <img src="{{ asset('logo.png') }}" alt="Abundant TV" class="h-10 w-auto object-contain sm:h-12" />

            <div class="flex flex-col justify-center">
                <span class="text-xl font-black tracking-tighter text-zinc-900 dark:text-white sm:text-2xl">
                    Abundant<span class="text-cyan-500">TV</span>
                </span>
                <span class="text-[9px] font-bold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400 group-hover:text-cyan-600 dark:group-hover:text-cyan-400 transition-colors">
                    Gospel & Entertainment
                </span>
            </div>
        </a>

        {{-- Desktop Navigation --}}
        <nav class="hidden items-center gap-6 md:flex">
            <a href="/schedule" wire:navigate class="text-sm font-semibold text-zinc-600 transition hover:text-cyan-600 dark:text-zinc-300 dark:hover:text-cyan-400">
                Schedule
            </a>
            <a href="/about" wire:navigate class="text-sm font-semibold text-zinc-600 transition hover:text-cyan-600 dark:text-zinc-300 dark:hover:text-cyan-400">
                About Us
            </a>
            <a href="/faqs" wire:navigate class="text-sm font-semibold text-zinc-600 transition hover:text-cyan-600 dark:text-zinc-300 dark:hover:text-cyan-400">
                FAQs
            </a>

            <div class="mx-2 h-4 w-px bg-zinc-300 dark:bg-zinc-700"></div>

            {{-- 24/7 Live Indicator --}}
            <div class="flex items-center gap-2 rounded-full bg-red-50 px-3 py-1.5 ring-1 ring-red-100 dark:bg-red-950/30 dark:ring-red-900/50">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-red-600"></span>
                </span>
                <span class="text-xs font-bold uppercase tracking-wider text-red-700 dark:text-red-400">24/7 Live</span>
            </div>

            {{-- Contact Us Button --}}
            <a href="/contact" wire:navigate class="group relative inline-flex items-center gap-2 rounded-full bg-cyan-500 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-cyan-500/30 transition-all hover:bg-cyan-400 hover:shadow-cyan-400/40">
                Contact Us
            </a>
        </nav>

        {{-- Mobile Menu Toggle --}}
        <button
            @click="mobileMenuOpen = !mobileMenuOpen"
            class="flex h-10 w-10 items-center justify-center rounded-lg text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800 md:hidden"
            aria-label="Toggle menu"
        >
            <flux:icon name="bars-3" class="h-6 w-6" x-show="!mobileMenuOpen" />
            <flux:icon name="x-mark" class="h-6 w-6" x-show="mobileMenuOpen" x-cloak />
        </button>
    </div>

    {{-- Mobile Navigation Dropdown (Glassmorphism) --}}
    <div
        x-show="mobileMenuOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="absolute inset-x-0 top-full border-b border-zinc-200/50 bg-white/95 backdrop-blur-xl dark:border-zinc-800/50 dark:bg-zinc-950/95 md:hidden"
        x-cloak
    >
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
            <div class="flex flex-col gap-2">
                {{-- Mobile 24/7 Live Badge --}}
                <div class="mb-2 flex items-center justify-center gap-2 rounded-xl bg-red-50 py-2 ring-1 ring-red-100 dark:bg-red-950/30 dark:ring-red-900/50">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-red-600"></span>
                    </span>
                    <span class="text-xs font-bold uppercase tracking-wider text-red-700 dark:text-red-400">24/7 Live</span>
                </div>

                <a href="/schedule" wire:navigate class="rounded-xl px-4 py-3 text-base font-semibold text-zinc-900 hover:bg-zinc-100 dark:text-white dark:hover:bg-zinc-800">
                    Schedule
                </a>
                <a href="/about" wire:navigate class="rounded-xl px-4 py-3 text-base font-semibold text-zinc-900 hover:bg-zinc-100 dark:text-white dark:hover:bg-zinc-800">
                    About Us
                </a>
                <a href="/faqs" wire:navigate class="rounded-xl px-4 py-3 text-base font-semibold text-zinc-900 hover:bg-zinc-100 dark:text-white dark:hover:bg-zinc-800">
                    FAQs
                </a>
                <a href="/contact" wire:navigate class="rounded-xl px-4 py-3 text-base font-semibold text-zinc-900 hover:bg-zinc-100 dark:text-white dark:hover:bg-zinc-800">
                    Contact
                </a>

                <div class="my-2 h-px bg-zinc-200 dark:bg-zinc-800"></div>

                {{-- Mobile Contact Us Button --}}
                <a href="/contact" wire:navigate class="flex items-center justify-center gap-2 rounded-xl bg-cyan-500 px-4 py-4 text-base font-bold text-white shadow-lg shadow-cyan-500/20 hover:bg-cyan-400">
                    Contact Us
                </a>
            </div>
        </div>
    </div>
</header>
