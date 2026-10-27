<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    // Static page
};
?>

<div class="bg-white dark:bg-zinc-950 min-h-screen">

    {{-- ═══════════ HERO: MATCHING FAQs & SCHEDULE ═══════════ --}}
    <div class="relative overflow-hidden">
        {{-- Asymmetrical Theme Glows (Maroon Left, Cyan Right) --}}
        <div class="absolute -left-40 top-0 -z-10 h-[500px] w-[500px] rounded-full bg-red-800/5 blur-[120px] dark:bg-red-900/10"></div>
        <div class="absolute -right-40 top-20 -z-10 h-[500px] w-[500px] rounded-full bg-cyan-500/10 blur-[120px] dark:bg-cyan-500/5"></div>

        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:py-24 lg:px-8">
            {{-- Gold Badge --}}
            <span class="inline-flex items-center gap-2 rounded-full bg-amber-500/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-amber-600 ring-1 ring-amber-500/20 dark:text-amber-400">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                Our Story
            </span>

            {{-- Heading --}}
            <h1 class="mt-6 text-4xl font-extrabold tracking-tight text-zinc-900 dark:text-white sm:text-5xl lg:text-6xl">
                About us</span>
            </h1>

            {{-- Subheading --}}
            <p class="mt-6 max-w-7xl text-lg leading-relaxed text-zinc-600 dark:text-zinc-400">
                AbundantTV is your home for locally produced, Gospel-centered broadcasting. We are dedicated to impacting lives, strengthening families, and clarifying the Gospel for generations through the power of media.
            </p>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">

        {{-- ═══════════ STATS SECTION ═══════════ --}}
        <div class="grid grid-cols-2 gap-4 border-y border-zinc-200 py-8 dark:border-zinc-800 md:grid-cols-4 md:gap-8 lg:py-12">
            <div class="flex flex-col justify-center">
                <dt class="text-sm font-medium text-zinc-500">Broadcast</dt>
                <dd class="order-first text-3xl font-extrabold tracking-tight text-red-800 dark:text-red-500 sm:text-4xl">24/7</dd>
                <dd class="mt-1 text-sm text-zinc-500">Live Stream</dd>
            </div>
            <div class="flex flex-col justify-center">
                <dt class="text-sm font-medium text-zinc-500">Content</dt>
                <dd class="order-first text-3xl font-extrabold tracking-tight text-cyan-600 dark:text-cyan-400 sm:text-4xl">100%</dd>
                <dd class="mt-1 text-sm text-zinc-500">Gospel Focused</dd>
            </div>
            <div class="flex flex-col justify-center">
                <dt class="text-sm font-medium text-zinc-500">Production</dt>
                <dd class="order-first text-3xl font-extrabold tracking-tight text-amber-500 dark:text-amber-400 sm:text-4xl">100%</dd>
                <dd class="mt-1 text-sm text-zinc-500">Locally Made</dd>
            </div>
            <div class="flex flex-col justify-center">
                <dt class="text-sm font-medium text-zinc-500">Focus</dt>
                <dd class="order-first text-3xl font-extrabold tracking-tight text-zinc-900 dark:text-white sm:text-4xl">Family</dd>
                <dd class="mt-1 text-sm text-zinc-500">First</dd>
            </div>
        </div>

        {{-- ═══════════ VISION & MISSION ═══════════ --}}
        <div class="mt-16 grid gap-6 lg:grid-cols-2">
            {{-- Vision --}}
            <div class="rounded-3xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-cyan-50 text-cyan-600 dark:bg-cyan-950/50 dark:text-cyan-400">
                    <flux:icon name="eye" class="h-6 w-6" />
                </div>
                <h3 class="mt-6 text-2xl font-bold text-zinc-900 dark:text-white">Our Vision</h3>
                <p class="mt-4 text-base leading-relaxed text-zinc-600 dark:text-zinc-400">
                    To be the leading digital sanctuary in East Africa, broadcasting the clarified Gospel and strengthening families across generations.
                </p>
            </div>

            {{-- Mission --}}
            <div class="rounded-3xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                    <flux:icon name="paper-airplane" class="h-6 w-6" />
                </div>
                <h3 class="mt-6 text-2xl font-bold text-zinc-900 dark:text-white">Our Mission</h3>
                <p class="mt-4 text-base leading-relaxed text-zinc-600 dark:text-zinc-400">
                    To produce and air high-quality, locally crafted Christian content that educates, entertains, and empowers our viewers to live victoriously in Christ.
                </p>
            </div>
        </div>

        {{-- ═══════════ CORE VALUES ═══════════ --}}
        <div class="mt-16 lg:mt-24">
            <div class="mb-8 flex items-center gap-3">
                <span class="h-px w-6 bg-amber-500"></span>
                <h2 class="text-xl font-bold uppercase tracking-wide text-zinc-900 dark:text-white">Core Values</h2>
            </div>

            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @php
                    $values = [
                        ['icon' => 'book-open', 'title' => 'Clarified Gospel', 'desc' => 'Presenting the Word so people are anchored in the New Covenant and free to live as God’s children.', 'color' => 'text-cyan-600 bg-cyan-50 dark:bg-cyan-950/50 dark:text-cyan-400'],
                        ['icon' => 'home-modern', 'title' => 'Family Enrichment', 'desc' => 'Building solid families through our programming. Strong families make stronger societies.', 'color' => 'text-red-800 bg-red-50 dark:bg-red-950/50 dark:text-red-400'],
                        ['icon' => 'star', 'title' => 'Local Excellence', 'desc' => 'We believe in the talent right here at home. 100% of our shows are produced locally.', 'color' => 'text-amber-600 bg-amber-50 dark:bg-amber-950/50 dark:text-amber-400'],
                        ['icon' => 'heart', 'title' => 'Compassion', 'desc' => 'Reaching out to the destitute and providing chances for a better life through our ministry arms.', 'color' => 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/50 dark:text-emerald-400'],
                    ];
                @endphp

                @foreach($values as $value)
                    <div class="rounded-2xl border border-zinc-200 bg-white p-6 transition hover:border-zinc-300 hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-zinc-700">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg {{ $value['color'] }}">
                            <flux:icon name="{{ $value['icon'] }}" class="h-5 w-5" />
                        </div>
                        <h4 class="mt-4 text-base font-bold text-zinc-900 dark:text-white">{{ $value['title'] }}</h4>
                        <p class="mt-2 text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">{{ $value['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ═══════════ FOUNDER SECTION ═══════════ --}}
        <div class="mt-16 lg:mt-24">
            <div class="overflow-hidden rounded-3xl bg-zinc-900 ring-1 ring-white/10 dark:bg-zinc-900">
                <div class="grid items-center gap-8 lg:grid-cols-2 lg:gap-12">

                    {{-- Placeholder Image Area --}}
                    <div class="relative h-64 w-full overflow-hidden bg-zinc-800 lg:h-[450px]">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-7xl font-black text-white/5">SG</span>
                        </div>
                        {{-- Replace the div above with this image tag when you have the photo --}}
                        {{-- <img src="{{ asset('storage/dr-gitaari.jpg') }}" class="h-full w-full object-cover object-top opacity-90" alt="Bishop Dr. Sammy Gitaari" /> --}}
                    </div>

                    {{-- Bio Content --}}
                    <div class="p-8 lg:p-12 lg:pl-0">
                        <div class="flex items-center gap-3">
                            <span class="h-px w-8 bg-amber-500"></span>
                            <span class="text-xs font-bold uppercase tracking-widest text-amber-500">The Man Behind the Mission</span>
                        </div>

                        <h2 class="mt-4 text-3xl font-bold text-white sm:text-4xl">
                            Bishop Dr. Sammy Gitaari
                        </h2>

                        <div class="mt-6 space-y-4 text-base leading-relaxed text-zinc-400">
                            <p>
                                AbundantTV was launched by <strong class="text-white">Bishop Dr. Sammy Gitaari</strong>, a theologian, educator, and bestselling author whose ministry has transformed lives across Kenya and beyond since 1977.
                            </p>
                            <p>
                                With a deep passion for family enrichment and a vision to clarify the Gospel, Dr. Gitaari recognized the power of media to reach generations. His radio and television broadcasts have long been a source of wisdom for East Africa, leading to the birth of AbundantTV as a permanent home for this vision.
                            </p>
                            <p>
                                From engaging preachings and kids' shows to family-focused programming, his mission ensures that every viewer is anchored in the Word, strengthened in their family, and inspired to live a life of purpose.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
