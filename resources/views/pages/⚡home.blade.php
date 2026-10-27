<?php

use App\Models\Comment;
use App\Models\Schedule;
use App\Models\Viewlog;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name     = '';
    public string $phone    = '';
    public string $body     = '';
    public string $deviceId = '';
    public bool   $joined   = false;

    public function mount(): void
    {
        $this->deviceId = request()->cookie('tv_device_id', '');

        $live = Schedule::liveNow()->first();
        if ($live) {
            Viewlog::create([
                'show_id'     => $live->show_id,
                'schedule_id' => $live->id,
                'ip_address'  => request()->ip(),
                'user_agent'  => request()->userAgent(),
            ]);
        }
    }

    public function updatedDeviceId(string $value): void
    {
        cookie()->queue('tv_device_id', $value, 60 * 24 * 365);
    }

    public function submitComment(): void
    {
        $this->validate([
            'name'  => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:30'],
            'body'  => ['required', 'string', 'max:500'],
        ]);

        $live = Schedule::liveNow()->first();

        Comment::create([
            'show_id'     => $live?->show_id,
            'schedule_id' => $live?->id,
            'name'        => $this->name,
            'phone'       => $this->phone,
            'device_id'   => $this->deviceId ?: null,
            'body'        => $this->body,
            'ip_address'  => request()->ip(),
        ]);

        $this->joined = true;
        $this->reset('body');
        Flux::toast('Comment posted!', variant: 'success');
    }

    public function with(): array
    {
        $now     = now();
        $nowTime = $now->format('H:i:s');

        // One query for today's lineup. Live / next / upcoming / aired are all derived
        // from it in PHP, so every poll stays cheap and the three can never disagree.
        $todaySlots = Schedule::with(['show', 'presenters'])
            ->today()
            ->orderBy('start_time')
            ->get();

        // A slot that wraps past midnight (end <= start) runs until the end of the day.
        $endOf = fn (Schedule $s) => $s->end_time <= $s->start_time ? '23:59:59' : $s->end_time;

        $liveNow    = $todaySlots->first(fn ($s) => $s->start_time <= $nowTime && $endOf($s) > $nowTime);
        $upcoming   = $todaySlots->filter(fn ($s) => $s->start_time > $nowTime)->values();
        $upNext     = $upcoming->first();
        $airedCount = $todaySlots->filter(fn ($s) => $endOf($s) <= $nowTime)->count();

        $liveProgress = 0;
        $liveEndsAt   = null;
        if ($liveNow) {
            $start        = Carbon::parse($liveNow->start_time)->timestamp;
            $end          = Carbon::parse($endOf($liveNow))->timestamp;
            $liveProgress = (int) max(0, min(100, ($now->timestamp - $start) / max(1, $end - $start) * 100));
            $liveEndsAt   = $end;
        }

        // Which day (1 to 7 days ahead) has programs next. Only this small integer is cached:
        // Laravel 13 refuses to unserialize cached objects such as Collections and models.
        $nextOffset = Cache::remember('tv.next-day-offset.' . $now->toDateString(), 300, function () use ($now) {
            for ($i = 1; $i <= 7; $i++) {
                if (Schedule::onDay(strtolower($now->copy()->addDays($i)->englishDayOfWeek))->exists()) {
                    return $i;
                }
            }

            return 0;
        });

        $nextDay = ['label' => 'Coming up', 'date' => null, 'slots' => collect()];

        if ($nextOffset > 0) {
            $day = $now->copy()->addDays($nextOffset);

            $nextDay = [
                'label' => $nextOffset === 1 ? 'Tomorrow' : $day->format('l'),
                'date'  => $day->format('M j'),
                'slots' => Schedule::with(['show', 'presenters'])
                    ->onDay(strtolower($day->englishDayOfWeek))
                    ->orderBy('start_time')
                    ->get(),
            ];
        }

        return [
            'liveNow'      => $liveNow,
            'upNext'       => $upNext,
            'upcoming'     => $upcoming,
            'airedCount'   => $airedCount,
            'liveProgress' => $liveProgress,
            'liveEndsAt'   => $liveEndsAt,
            'nextDay'      => $nextDay,
            'comments'     => Comment::latest()->limit(40)->get()->reverse(),
        ];
    }
};

?>

<div class="mx-auto max-w-[1500px] px-4 py-6 lg:px-8 lg:py-10">

    @php
        $fmt = fn ($t) => \Illuminate\Support\Carbon::parse($t)->format('g:i A');

        $initials = fn (string $n) => \Illuminate\Support\Str::of($n)
            ->trim()->explode(' ')->filter()->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->join('') ?: '?';

        // "in 12 min", "in 2h 10m"
        $until = function (int $ts): string {
            $m = max(0, (int) ceil(($ts - now()->timestamp) / 60));
            if ($m < 1)  return 'any moment now';
            if ($m < 60) return "in {$m} min";
            $r = $m % 60;
            return 'in ' . intdiv($m, 60) . 'h' . ($r ? " {$r}m" : '');
        };

        $timeline = $liveNow ? collect([$liveNow])->merge($upcoming) : $upcoming;
    @endphp

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] xl:grid-cols-[minmax(0,1fr)_400px]">

        {{-- ═══════════ PLAYER + NOW ON AIR ═══════════ --}}
        <section class="min-w-0">

            <div @class([
                'relative overflow-hidden rounded-3xl bg-black shadow-2xl ring-1 transition-shadow duration-500',
                'shadow-red-950/40 ring-red-500/30' => $liveNow,
                'shadow-black/30 ring-white/10'     => ! $liveNow,
            ])>

                {{-- wire:ignore keeps Livewire's poll from resetting the player's DOM --}}
                <div wire:ignore id="tv-player" class="relative aspect-video w-full select-none bg-black">

                    {{-- No "controls" attribute: the native bar has the seek slider and 0:30 / 1:22 timer.
                         Our own bar below only has live-appropriate controls. --}}
                    <video
                        id="video"
                        playsinline
                        muted
                        preload="auto"
                        disablepictureinpicture
                        disableremoteplayback
                        controlslist="nodownload noplaybackrate noremoteplayback"
                        class="h-full w-full bg-black object-contain opacity-0 transition-opacity duration-500"
                    ></video>

                    {{-- Buffering ring with percentage --}}
                    <div data-overlay="buffer" class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-black text-white transition-colors duration-300">
                        <div class="relative h-24 w-24">
                            <div data-spin class="h-full w-full">
                                <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90" aria-hidden="true">
                                    <circle cx="50" cy="50" r="44" fill="none" stroke="currentColor" stroke-opacity="0.15" stroke-width="6" />
                                    <circle data-ring cx="50" cy="50" r="44" fill="none" stroke-width="6" stroke-linecap="round"
                                        stroke-dasharray="276.46" stroke-dashoffset="276.46"
                                        class="stroke-cyan-400 transition-[stroke-dashoffset] duration-300" />
                                </svg>
                            </div>
                            <span data-pct class="absolute inset-0 flex items-center justify-center text-xl font-semibold tabular-nums">0%</span>
                        </div>
                        <p data-label class="text-sm text-zinc-300" role="status">Connecting to the stream</p>
                    </div>

                    <div data-overlay="offline" class="absolute inset-0 z-10 hidden flex-col items-center justify-center gap-1 bg-zinc-950 px-6 text-center text-white">
                        <flux:icon name="signal-slash" class="mb-2 h-9 w-9 text-zinc-600" />
                        <h2 class="text-xl font-semibold">We'll be live in a few minutes</h2>
                        <p class="text-sm text-zinc-400">The stream is not available right now. We keep retrying, so this page will start playing on its own.</p>
                    </div>

                    <button type="button" data-action="unmute"
                        class="absolute right-4 top-4 z-20 hidden items-center gap-2 rounded-full bg-white px-3.5 py-2 text-sm font-semibold text-zinc-900 shadow-lg transition hover:bg-zinc-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400">
                        <flux:icon name="speaker-x-mark" class="h-4 w-4" />
                        Tap to unmute
                    </button>

                    {{-- Live-only controls: no timeline, no time counter, no skip --}}
                    <div data-controls class="pointer-events-none absolute inset-x-0 bottom-0 z-20 flex items-center gap-1 bg-gradient-to-t from-black/80 to-transparent px-3 pb-3 pt-12 text-white opacity-0 transition-opacity duration-200">
                        <button type="button" data-action="play" aria-label="Pause or resume"
                            class="flex h-10 w-10 items-center justify-center rounded-full transition hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400">
                            <span data-icon="pause"><flux:icon name="pause" variant="solid" class="h-5 w-5" /></span>
                            <span data-icon="play" class="hidden"><flux:icon name="play" variant="solid" class="h-5 w-5" /></span>
                        </button>

                        <button type="button" data-action="mute" aria-label="Mute or unmute"
                            class="flex h-10 w-10 items-center justify-center rounded-full transition hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400">
                            <span data-icon="vol" class="hidden"><flux:icon name="speaker-wave" class="h-5 w-5" /></span>
                            <span data-icon="muted"><flux:icon name="speaker-x-mark" class="h-5 w-5" /></span>
                        </button>

                        <input type="range" data-volume min="0" max="1" step="0.05" value="0" aria-label="Volume"
                            class="hidden w-24 accent-cyan-400 sm:block" />

                        <span class="ml-2 inline-flex items-center gap-1.5 text-sm font-medium">
                            <span class="h-2 w-2 rounded-full bg-red-500"></span>
                            Live
                        </span>

                        <button type="button" data-action="fullscreen" aria-label="Fullscreen"
                            class="ml-auto flex h-10 w-10 items-center justify-center rounded-full transition hover:bg-white/15 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400">
                            <span data-icon="fs-in"><flux:icon name="arrows-pointing-out" class="h-5 w-5" /></span>
                            <span data-icon="fs-out" class="hidden"><flux:icon name="arrows-pointing-in" class="h-5 w-5" /></span>
                        </button>
                    </div>
                </div>

                <div class="pointer-events-none absolute inset-x-0 top-0 z-10 h-24 bg-gradient-to-b from-black/60 to-transparent"></div>

                @if($liveNow)
                    <div class="pointer-events-none absolute left-4 top-4 z-20 flex max-w-[70%] items-center gap-2 rounded-full bg-red-600 py-1.5 pl-3 pr-4 text-white shadow-lg">
                        <span class="relative flex h-2 w-2 shrink-0">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-white"></span>
                        </span>
                        <span class="text-sm font-semibold">Live</span>
                        <span class="truncate text-sm text-white/90">{{ $liveNow->show->title }}</span>
                    </div>
                @endif
            </div>

            {{-- On air strip --}}
            <div class="mt-4 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900 sm:p-5">
                @if($liveNow)
                    <div class="flex flex-wrap items-start justify-between gap-x-8 gap-y-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-red-600 dark:text-red-400">On air now</p>
                            <h1 class="mt-0.5 truncate text-xl font-semibold text-zinc-900 dark:text-white sm:text-2xl">{{ $liveNow->show->title }}</h1>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $fmt($liveNow->start_time) }} to {{ $fmt($liveNow->end_time) }}
                                @if($liveNow->presenters->isNotEmpty())
                                    with {{ $liveNow->presenters->pluck('name')->join(', ') }}
                                @endif
                            </p>
                        </div>

                        @if($upNext)
                            <div class="min-w-0 text-sm sm:text-right">
                                <p class="font-medium text-amber-600 dark:text-amber-400">Up next, {{ $until(\Illuminate\Support\Carbon::parse($upNext->start_time)->timestamp) }}</p>
                                <p class="mt-0.5 truncate font-semibold text-zinc-900 dark:text-white">{{ $upNext->show->title }}</p>
                            </div>
                        @endif
                    </div>

                    <div class="mt-4 h-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $liveProgress }}" aria-label="Show progress">
                        <div class="h-full rounded-full bg-red-600 transition-[width] duration-1000" style="width: {{ $liveProgress }}%"></div>
                    </div>
                @else
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">We're between programs</p>
                    @if($upNext)
                        <h1 class="mt-0.5 truncate text-xl font-semibold text-zinc-900 dark:text-white sm:text-2xl">{{ $upNext->show->title }}</h1>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Starts at {{ $fmt($upNext->start_time) }}, {{ $until(\Illuminate\Support\Carbon::parse($upNext->start_time)->timestamp) }}
                            @if($upNext->presenters->isNotEmpty())
                                with {{ $upNext->presenters->pluck('name')->join(', ') }}
                            @endif
                        </p>
                    @else
                        <h1 class="mt-0.5 text-xl font-semibold text-zinc-900 dark:text-white sm:text-2xl">Welcome to AbundantTV</h1>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">See the program guide below for what's coming up.</p>
                    @endif
                @endif
            </div>
        </section>

        {{-- ═══════════ CHAT ═══════════ --}}
        <aside class="min-w-0">
            <div class="flex h-[560px] flex-col overflow-hidden rounded-3xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900 lg:sticky lg:top-6 lg:h-[min(720px,calc(100vh-3rem))]">

                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    <div>
                        <h2 class="text-base font-semibold text-zinc-900 dark:text-white">Live chat</h2>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $comments->count() }} {{ \Illuminate\Support\Str::plural('message', $comments->count()) }}
                        </p>
                    </div>
                    <span class="relative flex h-2.5 w-2.5" title="Chat is updating live">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-cyan-500 opacity-60"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-cyan-500"></span>
                    </span>
                </div>

                {{-- This poll also refreshes the schedule, progress bar and now/next state every 5s --}}
                <div id="chat-history" class="flex-1 space-y-4 overflow-y-auto px-5 py-4" wire:poll.5s>
                    @forelse($comments as $comment)
                        @php $hue = abs(crc32($comment->name)) % 360; @endphp
                        <div wire:key="comment-{{ $comment->id }}" class="flex gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white"
                                 style="background-color: hsl({{ $hue }} 55% 42%)" aria-hidden="true">{{ $initials($comment->name) }}</div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-2">
                                    <span class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $comment->name }}</span>
                                    <span class="shrink-0 text-xs text-zinc-400">{{ $comment->created_at->diffForHumans(short: true) }}</span>
                                </div>

                                @if($comment->has_banned_word)
                                    <p class="mt-0.5 text-sm italic text-zinc-400">This message was hidden by a moderator.</p>
                                @else
                                    <p class="mt-0.5 break-words text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">{{ $comment->body }}</p>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="flex h-full flex-col items-center justify-center gap-1 text-center">
                            <flux:icon name="chat-bubble-oval-left" class="mb-1 h-8 w-8 text-zinc-300 dark:text-zinc-700" />
                            <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">No messages yet</p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">Start the conversation below.</p>
                        </div>
                    @endforelse
                </div>

                <div class="border-t border-zinc-200 p-4 dark:border-zinc-800">
                    <form id="chat-form" wire:submit="submitComment" class="space-y-2.5">
                        @if($joined)
                            <div class="flex items-center justify-between gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                <span class="truncate">Chatting as <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $name }}</span></span>
                                <button type="button" wire:click="$set('joined', false)" class="shrink-0 font-medium text-cyan-600 hover:underline dark:text-cyan-400">Change name</button>
                            </div>
                        @else
                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" wire:model="name" placeholder="Your name" autocomplete="name" required
                                    class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 transition focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:placeholder-zinc-500" />
                                <input type="tel" wire:model="phone" placeholder="+254…" autocomplete="tel" required
                                    class="w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 transition focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:placeholder-zinc-500" />
                            </div>
                        @endif

                        <div class="flex gap-2">
                            <input type="text" wire:model="body" placeholder="Write a message" maxlength="500" required
                                class="min-w-0 flex-1 rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 transition focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/20 dark:border-zinc-700 dark:bg-zinc-950 dark:text-white dark:placeholder-zinc-500" />
                            <button type="submit" wire:loading.attr="disabled" wire:target="submitComment" aria-label="Send message"
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-cyan-500 text-white transition hover:bg-cyan-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500/50 disabled:opacity-60">
                                <flux:icon name="paper-airplane" class="h-4 w-4" />
                            </button>
                        </div>

                        @if($errors->any())
                            <p class="text-xs text-red-600 dark:text-red-400">{{ $errors->first() }}</p>
                        @endif
                    </form>
                </div>
            </div>
        </aside>
    </div>

    {{-- ═══════════ PROGRAM GUIDE ═══════════ --}}
    <section class="mt-12">
        <div class="mb-6 flex items-end justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold text-zinc-900 dark:text-white">Program guide</h2>
                <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">Today, {{ now()->format('l, M j') }}</p>
            </div>
            <a href="{{ route('schedule') }}" wire:navigate
               class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-zinc-200 px-4 py-2 text-sm font-medium text-zinc-700 transition hover:border-cyan-500 hover:text-cyan-600 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-cyan-500 dark:hover:text-cyan-400">
                Full schedule
                <flux:icon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>

        <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_340px]">

            {{-- Today: on air, up next, later --}}
            <div class="min-w-0">
                @forelse($timeline as $slot)
                    @php
                        $isLive  = $liveNow && $slot->id === $liveNow->id;
                        $isNext  = ! $isLive && $upNext && $slot->id === $upNext->id;
                        $startTs = \Illuminate\Support\Carbon::parse($slot->start_time)->timestamp;
                        $names   = $slot->presenters->pluck('name')->join(', ');
                    @endphp

                    <div wire:key="slot-{{ $slot->id }}" class="grid grid-cols-[4.5rem_1rem_minmax(0,1fr)] gap-x-3">

                        <div class="pt-0.5 text-right tabular-nums">
                            <p @class([
                                'text-sm font-semibold',
                                'text-red-600 dark:text-red-400'     => $isLive,
                                'text-amber-600 dark:text-amber-400' => $isNext,
                                'text-zinc-900 dark:text-white'      => ! $isLive && ! $isNext,
                            ])>{{ $fmt($slot->start_time) }}</p>
                            <p class="text-xs text-zinc-500">{{ $fmt($slot->end_time) }}</p>
                        </div>

                        {{-- Timeline rail --}}
                        <div class="relative">
                            @unless($loop->first && $loop->last)
                                <span @class([
                                    'absolute left-1/2 w-px -translate-x-1/2 bg-zinc-200 dark:bg-zinc-800',
                                    'top-3'    => $loop->first,
                                    'top-0'    => ! $loop->first,
                                    'h-3'      => $loop->last,
                                    'bottom-0' => ! $loop->last,
                                ])></span>
                            @endunless
                            <span @class([
                                'absolute left-1/2 top-1.5 h-2.5 w-2.5 -translate-x-1/2 rounded-full',
                                'bg-red-600 ring-4 ring-red-600/20'    => $isLive,
                                'bg-amber-500 ring-4 ring-amber-500/20' => $isNext,
                                'bg-white ring-2 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700' => ! $isLive && ! $isNext,
                            ])></span>
                        </div>

                        <div class="min-w-0 pb-7">
                            @if($isLive)
                                <p class="text-sm font-medium text-red-600 dark:text-red-400">On air now, ends {{ $until($liveEndsAt) }}</p>
                            @elseif($isNext)
                                <p class="text-sm font-medium text-amber-600 dark:text-amber-400">Up next, {{ $until($startTs) }}</p>
                            @endif

                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <h3 @class([
                                    'truncate text-zinc-900 dark:text-white',
                                    'text-lg font-semibold'  => $isLive || $isNext,
                                    'text-base font-medium'  => ! $isLive && ! $isNext,
                                ])>{{ $slot->show->title }}</h3>

                                @if($slot->show->category)
                                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">{{ ucfirst($slot->show->category) }}</span>
                                @endif

                                @if(! $isLive && ! $isNext)
                                    <span class="text-xs text-zinc-400">{{ $until($startTs) }}</span>
                                @endif
                            </div>

                            @if($slot->presenters->isNotEmpty())
                                @if($isLive || $isNext)
                                    <div class="mt-2 flex items-center gap-2">
                                        <div class="flex -space-x-1.5">
                                            @foreach($slot->presenters->take(3) as $p)
                                                <span class="flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-semibold text-white ring-2 ring-zinc-50 dark:ring-zinc-950"
                                                      style="background-color: hsl({{ abs(crc32($p->name)) % 360 }} 55% 42%)">{{ $initials($p->name) }}</span>
                                            @endforeach
                                        </div>
                                        <span class="truncate text-xs text-zinc-500 dark:text-zinc-400">with {{ $names }}</span>
                                    </div>
                                @else
                                    <p class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">with {{ $names }}</p>
                                @endif
                            @endif

                            @if(($isLive || $isNext) && $slot->show->description)
                                <p class="mt-2 max-w-prose text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($slot->show->description, 150) }}</p>
                            @endif

                            @if($isLive)
                                <div class="mt-3 h-1 max-w-md overflow-hidden rounded-full bg-red-200 dark:bg-red-950">
                                    <div class="h-full rounded-full bg-red-600 transition-[width] duration-1000" style="width: {{ $liveProgress }}%"></div>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-zinc-300 px-6 py-12 text-center dark:border-zinc-700">
                        <flux:icon name="calendar" class="mx-auto h-9 w-9 text-zinc-300 dark:text-zinc-700" />
                        <p class="mt-3 text-sm font-medium text-zinc-700 dark:text-zinc-300">That's all for today</p>
                        <p class="mt-0.5 text-sm text-zinc-500">See what's on {{ strtolower($nextDay['label']) }} in the next panel.</p>
                    </div>
                @endforelse

                @if($airedCount > 0)
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-500">
                        {{ $airedCount }} {{ \Illuminate\Support\Str::plural('program', $airedCount) }} already aired today.
                    </p>
                @endif
            </div>

            {{-- Next day --}}
            <div class="min-w-0">
                <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                        <h3 class="font-semibold text-zinc-900 dark:text-white">{{ $nextDay['label'] }}</h3>
                        @if($nextDay['date'])
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $nextDay['date'] }}</p>
                        @endif
                    </div>

                    <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($nextDay['slots']->take(6) as $slot)
                            <li wire:key="next-{{ $slot->id }}" class="flex gap-4 px-5 py-3">
                                <span class="w-16 shrink-0 pt-0.5 text-sm font-medium tabular-nums text-zinc-900 dark:text-white">{{ $fmt($slot->start_time) }}</span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $slot->show->title }}</p>
                                    @if($slot->presenters->isNotEmpty())
                                        <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">with {{ $slot->presenters->pluck('name')->join(', ') }}</p>
                                    @endif
                                </div>
                            </li>
                        @empty
                            <li class="px-5 py-8 text-center text-sm text-zinc-500">Nothing scheduled in the next 7 days.</li>
                        @endforelse
                    </ul>

                    @if($nextDay['slots']->count() > 6)
                        <a href="{{ route('schedule') }}" wire:navigate
                           class="block border-t border-zinc-200 px-5 py-3 text-center text-sm font-medium text-cyan-600 transition hover:bg-zinc-50 dark:border-zinc-800 dark:text-cyan-400 dark:hover:bg-zinc-800/50">
                            See all {{ $nextDay['slots']->count() }} programs
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- hls.js loads once, pinned to a fixed version --}}
    @assets
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js"></script>
    @endassets

    @script
    <script>
    (() => {
        const SRC = 'https://gcctv.streamkeys.xyz/index.m3u8';

        const RETRY_MS      = 5000;   // wait before reconnecting after the stream drops
        const START_BUFFER  = 4;      // seconds buffered before the first play
        const RESUME_BUFFER = 5;      // seconds buffered before resuming after a stall
        const MAX_WAIT_MS   = 10000;  // resume anyway after this long if some data is buffered
        const DEAD_AFTER_MS = 30000;  // reconnect if nothing moves for this long
        const REWIND_LIMIT  = 2;      // seconds; seeking further back than this is blocked
        const CIRC          = 2 * Math.PI * 44;

        const root = document.getElementById('tv-player');
        if (!root) return;

        const $q = (sel) => root.querySelector(sel);
        const video     = $q('video');
        const bufferEl  = $q('[data-overlay="buffer"]');
        const offlineEl = $q('[data-overlay="offline"]');
        const ringEl    = $q('[data-ring]');
        const spinEl    = $q('[data-spin]');
        const pctEl     = $q('[data-pct]');
        const labelEl   = $q('[data-label]');
        const unmuteBtn = $q('[data-action="unmute"]');
        const controls  = $q('[data-controls]');
        const playBtn   = $q('[data-action="play"]');
        const muteBtn   = $q('[data-action="mute"]');
        const volumeEl  = $q('[data-volume]');
        const fsBtn     = $q('[data-action="fullscreen"]');

        let hls = null;
        let state = 'loading';          // loading | live | offline
        let buffering = false;
        let bufferMode = 'start';       // start | rebuffer
        let bufferingSince = 0;
        let stageFloor = 0;
        let shownPct = 0;
        let userPaused = false;
        let maxTime = 0;
        let lastTime = 0;
        let lastProgressAt = Date.now();
        let mediaRecoveries = 0;
        let networkRecoveries = 0;
        let retryTimer = null;
        let hideTimer = null;
        let observer = null;
        let pointerType = 'mouse';
        let controlsWereHidden = true;

        const show = (el, on, display = 'flex') => {
            el.classList.toggle('hidden', !on);
            el.classList.toggle(display, on);
        };
        const icon = (name, on) => $q('[data-icon="' + name + '"]').classList.toggle('hidden', !on);

        // ── Buffer helpers ─────────────────────────────────────────────
        function bufferAhead() {
            const t = video.currentTime, b = video.buffered;
            for (let i = 0; i < b.length; i++) {
                if (b.end(i) > t) return Math.max(0, b.end(i) - Math.max(t, b.start(i)));
            }
            return 0;
        }

        function atLiveEdge() {
            const s = video.seekable, b = video.buffered;
            if (!s.length || !b.length) return false;
            return s.end(s.length - 1) - b.end(b.length - 1) < 1.5;
        }

        function goLive(minDiff = 1) {
            let target = null;
            if (hls && hls.liveSyncPosition) target = hls.liveSyncPosition;
            else if (video.seekable.length) target = video.seekable.end(video.seekable.length - 1) - 1;
            if (target && Math.abs(target - video.currentTime) > minDiff) {
                maxTime = target;
                video.currentTime = target;
            }
        }

        // ── Buffering ring ─────────────────────────────────────────────
        function setProgress(p) {
            const pct = Math.max(0, Math.min(100, Math.round(p)));
            const indeterminate = pct < 3;
            pctEl.textContent = pct + '%';
            spinEl.classList.toggle('animate-spin', indeterminate);
            ringEl.style.strokeDashoffset = String(CIRC * (indeterminate ? 0.75 : 1 - pct / 100));
        }

        function startBuffering(mode) {
            buffering = true;
            bufferMode = mode;
            bufferingSince = Date.now();
            shownPct = mode === 'start' ? stageFloor : 0;

            show(bufferEl, true);
            bufferEl.classList.toggle('bg-black', mode === 'start');
            bufferEl.classList.toggle('bg-black/60', mode === 'rebuffer');
            labelEl.textContent = mode === 'start' ? 'Connecting to the stream' : 'Buffering';
            setProgress(shownPct);

            // Hold playback until enough is buffered, so it resumes smoothly instead of stuttering
            if (mode === 'rebuffer') video.pause();
        }

        function playSafe() {
            const p = video.play();
            if (p && p.catch) {
                p.catch(() => { video.muted = true; video.play().catch(() => {}); });
            }
        }

        function finishBuffering() {
            setProgress(100);
            buffering = false;
            state = 'live';
            show(bufferEl, false);
            video.classList.remove('opacity-0');
            if (!userPaused) playSafe();
            syncVolume();
        }

        // ── Main loop (4x per second) ──────────────────────────────────
        function tick() {
            if (state === 'offline') return;
            const now = Date.now();

            if (buffering) {
                const target = bufferMode === 'start' ? START_BUFFER : RESUME_BUFFER;
                const ahead  = bufferAhead();
                const ratio  = Math.min(1, ahead / target);
                const waited = now - bufferingSince;

                shownPct = Math.max(shownPct, stageFloor + (100 - stageFloor) * ratio);
                setProgress(shownPct);
                labelEl.textContent = (bufferMode === 'start' && ahead === 0) ? 'Connecting to the stream' : 'Buffering';

                if (ratio >= 1
                    || (ahead > 0.5 && waited > MAX_WAIT_MS)
                    || (ahead > 0.5 && atLiveEdge() && waited > 1500)) {
                    finishBuffering();
                } else if (waited > DEAD_AFTER_MS) {
                    goOffline();
                }
                return;
            }

            // Watchdog: playing but the clock isn't moving
            if (state === 'live' && !userPaused && !video.paused) {
                if (video.currentTime > lastTime + 0.05) {
                    lastTime = video.currentTime;
                    lastProgressAt = now;
                } else if (now - lastProgressAt > DEAD_AFTER_MS) {
                    goOffline();
                }
            } else {
                lastProgressAt = now;
            }
        }

        // ── Connection ─────────────────────────────────────────────────
        const onNativeError = () => goOffline();

        function teardown() {
            if (hls) { hls.destroy(); hls = null; }
            video.removeEventListener('error', onNativeError);
            video.removeAttribute('src');
            video.load();
            maxTime = 0;
            lastTime = 0;
        }

        function goOffline() {
            teardown();
            state = 'offline';
            buffering = false;
            show(bufferEl, false);
            show(offlineEl, true);
            show(unmuteBtn, false, 'inline-flex');
            clearTimeout(retryTimer);
            retryTimer = setTimeout(connect, RETRY_MS);
        }

        function connect() {
            clearTimeout(retryTimer);
            teardown();

            state = 'loading';
            mediaRecoveries = 0;
            networkRecoveries = 0;
            stageFloor = 0;
            lastProgressAt = Date.now();

            show(offlineEl, false);
            video.classList.add('opacity-0');
            startBuffering('start');

            if (window.Hls && Hls.isSupported()) {
                const h = new Hls({
                    enableWorker: true,
                    lowLatencyMode: false,          // stability over lowest delay
                    // Adaptive bitrate: start cautious, switch down fast, switch up slowly
                    capLevelToPlayerSize: true,
                    abrEwmaDefaultEstimate: 1000000,
                    abrBandWidthFactor: 0.85,
                    abrBandWidthUpFactor: 0.6,
                    // Stay a few segments behind the live edge so there is room to absorb network dips
                    liveSyncDurationCount: 4,
                    liveMaxLatencyDurationCount: 12,
                    maxLiveSyncPlaybackRate: 1.06,  // gently catch up after a stall
                    maxBufferLength: 20,
                    maxMaxBufferLength: 40,
                    backBufferLength: 0,            // drop segments as soon as they have played
                    manifestLoadingMaxRetry: 2,
                    manifestLoadingRetryDelay: 1000,
                    levelLoadingMaxRetry: 4,
                    fragLoadingMaxRetry: 6,
                    fragLoadingRetryDelay: 1000,
                });
                hls = h;

                h.on(Hls.Events.MANIFEST_PARSED, () => { stageFloor = Math.max(stageFloor, 10); });
                h.on(Hls.Events.LEVEL_LOADED,    () => { stageFloor = Math.max(stageFloor, 25); });
                h.on(Hls.Events.FRAG_LOADED,     () => { networkRecoveries = 0; });

                h.on(Hls.Events.ERROR, (event, data) => {
                    if (hls !== h || !data.fatal) return;

                    if (data.type === Hls.ErrorTypes.MEDIA_ERROR && mediaRecoveries < 2) {
                        mediaRecoveries++;
                        h.recoverMediaError();
                        return;
                    }

                    const streamDown = data.details === Hls.ErrorDetails.MANIFEST_LOAD_ERROR
                                    || data.details === Hls.ErrorDetails.MANIFEST_LOAD_TIMEOUT;

                    if (data.type === Hls.ErrorTypes.NETWORK_ERROR && !streamDown && networkRecoveries < 3) {
                        networkRecoveries++;
                        setTimeout(() => { if (hls === h) h.startLoad(); }, 1500 * networkRecoveries);
                        return;
                    }

                    console.warn('[tv] fatal stream error:', data.type, data.details);
                    goOffline();
                });

                h.loadSource(SRC);
                h.attachMedia(video);
            } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                // Safari / iPhone: native HLS (the browser does its own adaptive switching)
                video.addEventListener('error', onNativeError, { once: true });
                video.src = SRC;
            } else {
                goOffline();
            }
        }

        // ── Live-only behaviour: no rewinding ──────────────────────────
        video.addEventListener('timeupdate', () => {
            if (video.currentTime > maxTime) maxTime = video.currentTime;
        });
        video.addEventListener('seeking', () => {
            if (state === 'live' && video.currentTime < maxTime - REWIND_LIMIT) goLive(0.5);
        });
        video.addEventListener('contextmenu', (e) => e.preventDefault());

        // Network ran dry: show the ring and wait for a real buffer before resuming
        video.addEventListener('waiting', () => {
            if (state !== 'live' || buffering || userPaused || video.seeking) return;
            if (bufferAhead() > 2) return;
            startBuffering('rebuffer');
        });

        // ── Controls ───────────────────────────────────────────────────
        const syncPlayIcon = () => { icon('play', userPaused); icon('pause', !userPaused); };

        function togglePlay() {
            if (state === 'offline') return;
            if (userPaused) {
                userPaused = false;
                goLive();                       // resume at the live edge, never where we paused
                if (!buffering) playSafe();
            } else {
                userPaused = true;
                video.pause();
            }
            syncPlayIcon();
            showControls();
        }

        function syncVolume() {
            const silent = video.muted || video.volume === 0;
            icon('vol', !silent);
            icon('muted', silent);
            volumeEl.value = silent ? 0 : video.volume;
            show(unmuteBtn, state === 'live' && !buffering && silent, 'inline-flex');
        }

        function unmute() {
            video.muted = false;
            if (video.volume === 0) video.volume = 0.8;
            playSafe();
        }

        function toggleFullscreen() {
            if (document.fullscreenElement) { document.exitFullscreen(); return; }
            if (root.requestFullscreen) root.requestFullscreen().catch(() => {});
            else if (root.webkitRequestFullscreen) root.webkitRequestFullscreen();
            else if (video.webkitEnterFullscreen) video.webkitEnterFullscreen();   // iPhone
        }

        function showControls() {
            controls.classList.remove('opacity-0', 'pointer-events-none');
            clearTimeout(hideTimer);
            hideTimer = setTimeout(() => {
                if (userPaused) return;
                controls.classList.add('opacity-0', 'pointer-events-none');
            }, 3000);
        }

        playBtn.addEventListener('click', togglePlay);
        unmuteBtn.addEventListener('click', unmute);
        muteBtn.addEventListener('click', () => {
            if (video.muted || video.volume === 0) unmute(); else video.muted = true;
        });
        volumeEl.addEventListener('input', () => {
            const v = Number(volumeEl.value);
            video.volume = v;
            video.muted = v === 0;
        });
        video.addEventListener('volumechange', syncVolume);
        fsBtn.addEventListener('click', toggleFullscreen);

        root.addEventListener('pointerdown', (e) => {
            pointerType = e.pointerType;
            controlsWereHidden = controls.classList.contains('opacity-0');
        }, true);
        root.addEventListener('pointerdown', showControls);
        root.addEventListener('pointermove', showControls);
        root.addEventListener('focusin', showControls);

        video.addEventListener('click', () => {
            // On touch, the first tap only reveals the controls
            if (pointerType === 'touch' && controlsWereHidden) return;
            togglePlay();
        });

        const onFsChange = () => {
            const fs = !!document.fullscreenElement;
            icon('fs-in', !fs);
            icon('fs-out', fs);
        };
        document.addEventListener('fullscreenchange', onFsChange);

        const onVisible = () => {
            if (!document.hidden && state === 'live' && !userPaused && !buffering) goLive(3);
        };
        document.addEventListener('visibilitychange', onVisible);

        syncPlayIcon();
        syncVolume();
        const tickTimer = setInterval(tick, 250);
        connect();

        // ── Chat: stay pinned to the newest message unless the viewer scrolled up
        const chat = document.getElementById('chat-history');
        if (chat) {
            let stick = true;
            const toBottom = () => { chat.scrollTop = chat.scrollHeight; };

            toBottom();
            chat.addEventListener('scroll', () => {
                stick = chat.scrollHeight - chat.scrollTop - chat.clientHeight < 120;
            }, { passive: true });

            document.getElementById('chat-form')?.addEventListener('submit', () => { stick = true; });

            observer = new MutationObserver(() => { if (stick) toBottom(); });
            observer.observe(chat, { childList: true, subtree: true });
        }

        // ── Device id (localStorage + cookie)
        let deviceId = null;
        try { deviceId = localStorage.getItem('tv_device_id'); } catch (e) {}

        if (!deviceId) {
            deviceId = (window.crypto && crypto.randomUUID)
                ? crypto.randomUUID()
                : Date.now().toString(36) + Math.random().toString(36).slice(2);
            try { localStorage.setItem('tv_device_id', deviceId); } catch (e) {}
        }

        if ($wire.deviceId !== deviceId) {
            $wire.$set('deviceId', deviceId, false);
        }

        // ── Clean up when navigating away with wire:navigate
        document.addEventListener('livewire:navigating', () => {
            clearTimeout(retryTimer);
            clearTimeout(hideTimer);
            clearInterval(tickTimer);
            teardown();
            observer?.disconnect();
            document.removeEventListener('visibilitychange', onVisible);
            document.removeEventListener('fullscreenchange', onFsChange);
        }, { once: true });
    })();
    </script>
    @endscript
</div>
