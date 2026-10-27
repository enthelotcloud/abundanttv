<?php

use App\Models\Contact;
use Livewire\Component;

new class extends Component
{
    public function with(): array
    {
        return [
            'socials' => Contact::active()->get(),
        ];
    }
};
?>

<footer class="border-t border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8 lg:py-16">

        {{-- Main Footer Grid --}}
        <div class="grid grid-cols-1 gap-12 md:grid-cols-2 lg:grid-cols-4 lg:gap-8">

            {{-- Brand Column --}}
            <div class="lg:col-span-1">
                <a href="/" wire:navigate class="group flex items-center gap-3">
                    <img src="{{ asset('logo.png') }}" alt="Abundant TV" class="h-10 w-auto object-contain" />
                    <div class="flex flex-col justify-center">
                        <span class="text-xl font-black tracking-tighter text-zinc-900 dark:text-white">
                            Abundant<span class="text-cyan-500">TV</span>
                        </span>
                        <span class="text-[9px] font-bold uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                            Gospel & Entertainment
                        </span>
                    </div>
                </a>
                <p class="mt-4 text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">
                    Your home for locally produced, Gospel-centered broadcasting. Impacting lives, strengthening families, and clarifying the Gospel for generations.
                </p>

                {{-- 24/7 Badge --}}
                <div class="mt-6 inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1.5 ring-1 ring-red-100 dark:bg-red-950/30 dark:ring-red-900/50">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-red-600"></span>
                    </span>
                    <span class="text-xs font-bold uppercase tracking-wider text-red-700 dark:text-red-400">24/7 Live Broadcast</span>
                </div>
            </div>

            {{-- Quick Links --}}
            <div>
                <h3 class="text-sm font-bold uppercase tracking-wider text-zinc-900 dark:text-white">Explore</h3>
                <ul class="mt-4 space-y-3">
                    <li>
                        <a href="/schedule" wire:navigate class="text-sm text-zinc-500 transition hover:text-cyan-600 dark:text-zinc-400 dark:hover:text-cyan-400">TV Schedule</a>
                    </li>
                    <li>
                        <a href="/about" wire:navigate class="text-sm text-zinc-500 transition hover:text-cyan-600 dark:text-zinc-400 dark:hover:text-cyan-400">About Us</a>
                    </li>
                    <li>
                        <a href="/faqs" wire:navigate class="text-sm text-zinc-500 transition hover:text-cyan-600 dark:text-zinc-400 dark:hover:text-cyan-400">FAQs</a>
                    </li>
                    <li>
                        <a href="/contact" wire:navigate class="text-sm text-zinc-500 transition hover:text-cyan-600 dark:text-zinc-400 dark:hover:text-cyan-400">Contact Us</a>
                    </li>
                </ul>
            </div>

            {{-- Legal Links --}}
            <div>
                <h3 class="text-sm font-bold uppercase tracking-wider text-zinc-900 dark:text-white">Legal</h3>
                <ul class="mt-4 space-y-3">
                    <li>
                        <a href="/terms" wire:navigate class="text-sm text-zinc-500 transition hover:text-cyan-600 dark:text-zinc-400 dark:hover:text-cyan-400">Terms of Service</a>
                    </li>
                    <li>
                        <a href="/privacy" wire:navigate class="text-sm text-zinc-500 transition hover:text-cyan-600 dark:text-zinc-400 dark:hover:text-cyan-400">Privacy Policy</a>
                    </li>
                    <li>
                        <a href="/contact" wire:navigate class="text-sm text-zinc-500 transition hover:text-cyan-600 dark:text-zinc-400 dark:hover:text-cyan-400">Report an Issue</a>
                    </li>
                </ul>
            </div>

            {{-- Social & Contact Links --}}
            <div>
                <h3 class="text-sm font-bold uppercase tracking-wider text-zinc-900 dark:text-white">Connect</h3>
                <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">Follow us on social media or reach out directly.</p>

                <div class="mt-4 flex flex-wrap gap-3">
                    @forelse($socials as $social)
                        <a href="{{ $social->href }}" target="_blank" rel="noopener noreferrer"
                           class="group flex h-10 w-10 items-center justify-center rounded-xl bg-zinc-50 ring-1 ring-zinc-200 transition hover:bg-cyan-50 hover:ring-cyan-200 dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:bg-cyan-950/50 dark:hover:ring-cyan-900"
                           title="{{ $social->label }}">
                            @if($social->icon_path)
                                <img src="{{ asset('storage/'.$social->icon_path) }}" alt="{{ $social->label }}" class="h-5 w-5 object-contain dark:brightness-200" />
                            @else
                                <flux:icon name="link" class="h-5 w-5 text-zinc-500 transition group-hover:text-cyan-600 dark:text-zinc-400 dark:group-hover:text-cyan-400" />
                            @endif
                        </a>
                    @empty
                        <span class="text-sm italic text-zinc-500">No social links configured.</span>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Bottom Bar: Copyright & Enthelotcloud Credit --}}
        <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-zinc-200 pt-8 dark:border-zinc-800 sm:flex-row">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                &copy; {{ date('Y') }} AbundantTV. All rights reserved.
            </p>

            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Developed by
                <a href="https://enthelotcloud.com" target="_blank" rel="noopener noreferrer" class="font-semibold text-zinc-900 hover:text-cyan-600 dark:text-white dark:hover:text-cyan-400 transition">
                    Enthelotcloud
                </a>
            </p>
        </div>
    </div>
</footer>
