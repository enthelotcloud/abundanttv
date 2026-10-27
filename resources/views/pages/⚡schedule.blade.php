<?php

use App\Models\Schedule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    #[Url(as: 'day', history: true)]
    public string $activeDay = '';

    public array $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    public function mount(): void
    {
        // Default to today if no day is in the URL
        if (! $this->activeDay || ! in_array($this->activeDay, $this->days)) {
            $this->activeDay = strtolower(now()->englishDayOfWeek);
        }
    }

    public function setDay(string $day): void
    {
        if (in_array($day, $this->days)) {
            $this->activeDay = $day;
        }
    }

    public function with(): array
    {
        $schedules = Schedule::with(['show', 'presenters'])
            ->onDay($this->activeDay)
            ->orderBy('start_time')
            ->get();

        $isToday = $this->activeDay === strtolower(now()->englishDayOfWeek);

        return [
            'schedules' => $schedules,
            'isToday'   => $isToday,
        ];
    }
};

?>

<div class="bg-white dark:bg-zinc-950 min-h-screen">

    {{-- ═══════════ HERO: MATCHING FAQs ═══════════ --}}
    <div class="relative overflow-hidden">
        {{-- Asymmetrical Theme Glows (Maroon Left, Cyan Right) --}}
        <div class="absolute -left-40 top-0 -z-10 h-[500px] w-[500px] rounded-full bg-red-800/5 blur-[120px] dark:bg-red-900/10"></div>
        <div class="absolute -right-40 top-20 -z-10 h-[500px] w-[500px] rounded-full bg-cyan-500/10 blur-[120px] dark:bg-cyan-500/5"></div>

        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:py-24 lg:px-8">

            {{-- Gold Badge --}}
            <span class="inline-flex items-center gap-2 rounded-full bg-amber-500/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-amber-600 ring-1 ring-amber-500/20 dark:text-amber-400">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                Program Guide
            </span>

            {{-- Heading --}}
            <h1 class="mt-6 text-4xl font-extrabold tracking-tight text-zinc-900 dark:text-white sm:text-5xl lg:text-6xl">
                TV Schedule
            </h1>

            {{-- Subheading --}}
            <p class="mt-6 max-w-2xl text-lg leading-relaxed text-zinc-600 dark:text-zinc-400">
                Browse our weekly lineup of locally produced gospel content, talk shows, and worship services.
            </p>

        </div>
    </div>

    {{-- ═══════════ DAY NAVIGATION & CONTENT ═══════════ --}}
    <div class="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">

        {{-- Day Tabs --}}
        <div class="flex overflow-x-auto pb-4 hide-scrollbar border-b border-zinc-200 dark:border-zinc-800">
            <div class="flex gap-2 rounded-2xl bg-zinc-100 p-1.5 dark:bg-zinc-900">
                @foreach($days as $day)
                    @php
                        $isCurrentDay = $day === strtolower(now()->englishDayOfWeek);
                        $isActive = $day === $this->activeDay;
                    @endphp
                    <button
                        wire:click="setDay('{{ $day }}')"
                        @class([
                            'relative flex min-w-[90px] flex-col items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold transition-all duration-200',
                            'bg-white text-cyan-600 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-800 dark:text-cyan-400 dark:ring-zinc-700' => $isActive,
                            'text-zinc-600 hover:bg-zinc-200/50 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800/50 dark:hover:text-white' => ! $isActive,
                        ])
                    >
                        <span>{{ ucfirst($day) }}</span>
                        @if($isCurrentDay)
                            <span @class([
                                'mt-0.5 text-[10px] font-bold uppercase tracking-wider',
                                'text-cyan-600 dark:text-cyan-400' => $isActive,
                                'text-zinc-400 dark:text-zinc-500' => ! $isActive,
                            ])>Today</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Schedule List --}}
        <div class="mt-8 overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            @forelse($schedules as $slot)
                @php
                    $isLive = $isToday && $slot->is_live_now;
                    $isUpcoming = $isToday
                        && ! $isLive
                        && $slot->start_time > now()->format('H:i:s');
                @endphp

                <div
                    wire:key="schedule-slot-{{ $slot->id }}"
                    @class([
                        'group relative flex flex-col gap-4 border-b border-zinc-200 px-6 py-5 transition last:border-b-0 sm:flex-row sm:items-center dark:border-zinc-800',
                        // LIVE (Maroon)
                        'bg-gradient-to-r from-red-900/5 to-transparent dark:from-red-950/20' => $isLive,
                        // UPCOMING (Gold)
                        'bg-gradient-to-r from-amber-500/5 to-transparent dark:from-amber-950/20' => $isUpcoming,
                        // REST
                        'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' => ! $isLive && ! $isUpcoming,
                    ])
                >
                    {{-- Accent Bar --}}
                    @if($isLive)
                        <span class="absolute left-0 top-0 h-full w-1.5 bg-red-800"></span>
                    @elseif($isUpcoming)
                        <span class="absolute left-0 top-0 h-full w-1.5 bg-amber-500"></span>
                    @endif

                    {{-- Time --}}
                    <div class="w-full shrink-0 sm:w-32">
                        <p @class([
                            'text-lg font-bold tabular-nums',
                            'text-red-800 dark:text-red-400' => $isLive,
                            'text-amber-600 dark:text-amber-400' => $isUpcoming,
                            'text-zinc-900 dark:text-white' => ! $isLive && ! $isUpcoming,
                        ])>
                            {{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('H:i') }}
                        </p>
                        <p class="text-sm tabular-nums text-zinc-500">
                            to {{ \Illuminate\Support\Carbon::parse($slot->end_time)->format('H:i') }}
                        </p>
                    </div>

                    {{-- Show Info --}}
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 @class([
                                'truncate text-lg text-zinc-900 dark:text-white',
                                'font-extrabold' => $isLive,
                                'font-bold' => ! $isLive,
                            ])>
                                {{ $slot->show->title }}
                            </h3>

                            @if($isLive)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-red-800 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white shadow-sm">
                                    <span class="relative flex h-1.5 w-1.5">
                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                                        <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-white"></span>
                                    </span>
                                    Live Now
                                </span>
                            @elseif($isUpcoming)
                                <span class="inline-flex items-center rounded-full bg-amber-500/15 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">
                                    Upcoming
                                </span>
                            @endif

                            @if($slot->show->category)
                                <span class="rounded-full bg-zinc-100 px-2.5 py-0.5 text-[10px] font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                    {{ ucfirst($slot->show->category) }}
                                </span>
                            @endif
                        </div>

                        <p class="mt-1 line-clamp-1 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $slot->show->description }}
                        </p>
                    </div>

                    {{-- Presenters --}}
                    <div class="w-full shrink-0 sm:w-64 sm:text-right">
                        @if($slot->presenters->isNotEmpty())
                            <div class="flex flex-wrap gap-2 sm:justify-end">
                                @foreach($slot->presenters as $presenter)
                                    <div class="flex items-center gap-2 rounded-full border border-zinc-200 bg-zinc-50 py-1 pl-1 pr-3 dark:border-zinc-700 dark:bg-zinc-800">
                                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-cyan-100 text-[10px] font-bold text-cyan-700 dark:bg-cyan-900 dark:text-cyan-300">
                                            {{ collect(explode(' ', $presenter->name))->map(fn($w) => mb_substr($w, 0, 1))->take(2)->join('') }}
                                        </div>
                                        <div class="text-left">
                                            <p class="text-xs font-semibold text-zinc-900 dark:text-white">{{ $presenter->name }}</p>
                                            @if($presenter->pivot->role)
                                                <p class="text-[10px] text-zinc-500">{{ $presenter->pivot->role }}</p>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <span class="text-xs italic text-zinc-400">No presenters assigned</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-6 py-24 text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                        <flux:icon name="calendar" class="h-8 w-8 text-zinc-400" />
                    </div>
                    <h3 class="mt-4 text-lg font-bold text-zinc-900 dark:text-white">No shows scheduled</h3>
                    <p class="mt-1 text-sm text-zinc-500">There are no broadcasts listed for {{ ucfirst($activeDay) }}.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

<style>
    /* Hide scrollbar for the day tabs on mobile */
    .hide-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .hide-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>
