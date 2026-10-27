<?php

use App\Models\Faq;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $search = '';

    public function with(): array
    {
        $faqs = Faq::published()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('question', 'like', "%{$this->search}%")
                      ->orWhere('answer', 'like', "%{$this->search}%");
                });
            })
            ->get();

        return [
            'faqs'    => $faqs,
            'grouped' => $faqs->groupBy(fn ($f) => $f->category ?: 'General'),
        ];
    }
};

?>

<div class="bg-white dark:bg-zinc-950 min-h-screen">

    {{-- HERO --}}
    <div class="relative overflow-hidden">
        <div class="absolute -left-40 top-0 -z-10 h-[500px] w-[500px] rounded-full bg-red-800/5 blur-[120px] dark:bg-red-900/10"></div>
        <div class="absolute -right-40 top-20 -z-10 h-[500px] w-[500px] rounded-full bg-cyan-500/10 blur-[120px] dark:bg-cyan-500/5"></div>

        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:py-8 lg:px-8">
            <span class="inline-flex items-center gap-2 rounded-full bg-amber-500/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-amber-600 ring-1 ring-amber-500/20 dark:text-amber-400">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                Help Center
            </span>

            <h1 class="mt-6 text-4xl font-extrabold tracking-tight text-zinc-900 dark:text-white sm:text-5xl lg:text-6xl">
                FAQs
            </h1>

            <p class="mt-6 max-w-7xl text-lg leading-relaxed text-zinc-600 dark:text-zinc-400">
                Everything you need to know about AbundantTV, our broadcast schedule, and how to get involved in the ministry.
            </p>

            {{-- Search Bar (Full Width) --}}
            <div class="mt-10 w-full">
                <div class="relative w-full">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                        <flux:icon name="magnifying-glass" class="h-5 w-5 text-zinc-400" />
                    </div>
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search for answers..."
                        class="w-full rounded-2xl border border-zinc-300 bg-white py-4 pl-12 pr-4 text-zinc-900 placeholder-zinc-400 shadow-sm transition focus:border-cyan-500 focus:outline-none focus:ring-4 focus:ring-cyan-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white dark:placeholder-zinc-500"
                    >
                </div>
                @if($search)
                    <p class="mt-3 text-sm text-zinc-500">
                        Showing results for "<span class="font-medium text-zinc-900 dark:text-white">{{ $search }}</span>"
                        <button wire:click="$set('search', '')" class="ml-2 font-semibold text-cyan-600 hover:text-cyan-500">Clear</button>
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- FAQ CONTENT --}}
    <div class="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">

        @if($faqs->isEmpty())
            <div class="rounded-3xl border border-dashed border-zinc-300 py-20 text-center dark:border-zinc-800">
                <flux:icon name="magnifying-glass" class="mx-auto h-12 w-12 text-zinc-300 dark:text-zinc-700" />
                <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">No results found</h3>
                <p class="mt-1 text-sm text-zinc-500">We couldn't find anything matching "{{ $search }}".</p>
                <button wire:click="$set('search', '')" class="mt-6 text-sm font-semibold text-cyan-600 hover:text-cyan-500 dark:text-cyan-400">
                    Clear search
                </button>
            </div>
        @else
            <div class="space-y-12">
                @foreach($grouped as $category => $items)
                    <div>
                        <div class="mb-6 flex items-center gap-3">
                            <span class="h-px w-6 bg-amber-500"></span>
                            <h2 class="text-xl font-bold uppercase tracking-wide text-zinc-900 dark:text-white">{{ $category }}</h2>
                            <span class="rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                {{ $items->count() }} {{ Str::plural('question', $items->count()) }}
                            </span>
                        </div>

                        <div class="space-y-3">
                            @foreach($items as $faq)
                                <details class="group overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm transition-all duration-300 hover:border-cyan-300 dark:border-zinc-800 dark:bg-zinc-900 dark:hover:border-cyan-800 [&_summary::-webkit-details-marker]:hidden">

                                    <summary class="flex cursor-pointer list-none items-center justify-between px-6 py-5 focus:outline-none group-open:border-l-4 group-open:border-l-cyan-500 group-open:pl-[20px]">
                                        <span class="pr-4 text-base font-semibold text-zinc-900 transition group-hover:text-cyan-600 dark:text-white dark:group-hover:text-cyan-400">
                                            {{ $faq->question }}
                                        </span>

                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-zinc-50 transition-all duration-300 group-open:rotate-180 group-open:bg-cyan-500 group-open:text-white dark:bg-zinc-800">
                                            <flux:icon name="chevron-down" class="h-4 w-4 text-zinc-500 transition group-open:text-white dark:text-zinc-400" />
                                        </div>
                                    </summary>

                                    <div class="border-t border-zinc-100 px-6 pb-6 pt-4 text-zinc-600 dark:border-zinc-800/50 dark:text-zinc-400">
                                        <div class="prose prose-sm prose-zinc dark:prose-invert max-w-none">
                                            {!! nl2br(e($faq->answer)) !!}
                                        </div>
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-20 overflow-hidden rounded-3xl bg-zinc-900 p-8 ring-1 ring-white/10 dark:bg-zinc-900 sm:flex sm:items-center sm:justify-between sm:p-12">
            <div>
                <h2 class="text-2xl font-bold text-white">Still have questions?</h2>
                <p class="mt-2 text-zinc-400">Can't find the answer you're looking for? Please chat to our friendly team.</p>
            </div>
            <div class="mt-6 sm:ml-6 sm:mt-0 sm:shrink-0">
                <a href="{{ route('contact') }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl bg-cyan-500 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 focus:ring-offset-zinc-900">
                    Get in touch
                    <flux:icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>
        </div>

    </div>
</div>
