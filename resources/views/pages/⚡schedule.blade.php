<?php

use App\Models\Schedule;
use App\Models\Show;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

// If your layout prints <meta name="description" content="{{ $description ?? '' }}">, this feeds it.
new #[Layout('layouts.guest', ['description' => "Browse AbundantTV's weekly programme schedule. See what's on air now, what's next, and every show, start time and presenter, day by day."])]
    #[Title('Our Schedule | AbundantTV Programmes, Times and Presenters')]
class extends Component
{
    public const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    // Empty day means "today", so the page always opens on the current day.
    #[Url(as: 'day', except: '', history: true)]
    public string $day = '';

    #[Url(as: 'q', except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $presenter = '';

    #[Url(except: '')]
    public string $time = '';

    // ───────── Actions ─────────

    public function setDay(string $day): void
    {
        if ($day === 'all' || in_array($day, self::DAYS, true)) {
            $this->day = $day;
        }
    }

    public function setCategory(string $category): void
    {
        $this->category = $this->category === $category ? '' : $category;
    }

    public function setPresenter(string $slug): void
    {
        $this->presenter = $this->presenter === $slug ? '' : $slug;
    }

    public function resetFilters(): void
    {
        $this->reset(['q', 'category', 'presenter', 'time']);
    }

    public function updatedQ(): void
    {
        $this->q = mb_substr($this->q, 0, 60);
    }

    /** Clicking a smart suggestion. */
    public function pick(string $type, string $value): void
    {
        if ($type === 'presenter') {
            $this->presenter = $value;
            $this->q = '';
            return;
        }

        if ($type === 'category') {
            $this->category = $value;
            $this->q = '';
            return;
        }

        // A show: search its exact title, and hop to the next day it airs if the current day doesn't have it
        $this->q = $value;
        $show = Show::where('title', $value)->first();

        if ($show && $this->activeDay() !== 'all' && ! $show->airsOn($this->activeDay())) {
            $this->day = $this->nextAiringDay($show->airing_days ?? []);
        }
    }

    // ───────── Helpers ─────────

    protected function activeDay(): string
    {
        if ($this->day === 'all' || in_array($this->day, self::DAYS, true)) {
            return $this->day;
        }

        return strtolower(now()->englishDayOfWeek);
    }

    protected function nextAiringDay(array $airing): string
    {
        $airing = array_map('strtolower', $airing);

        for ($i = 0; $i < 7; $i++) {
            $d = strtolower(now()->addDays($i)->englishDayOfWeek);
            if (in_array($d, $airing, true)) {
                return $d;
            }
        }

        return $this->activeDay();
    }

    protected function daysLabel(array $days): string
    {
        $d = array_values(array_intersect(self::DAYS, array_map('strtolower', $days)));

        if (count($d) === 7) return 'Daily';
        if ($d === ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']) return 'Weekdays';
        if ($d === ['saturday', 'sunday']) return 'Weekends';

        return implode(', ', array_map(fn ($x) => ucfirst(substr($x, 0, 3)), $d));
    }

    /** Typo-tolerant "did you mean". */
    protected function closest(string $term, array $candidates): ?array
    {
        $best = null;
        $bestScore = PHP_INT_MAX;
        $limit = max(1, intdiv(strlen($term), 3));

        foreach ($candidates as $c) {
            $label = Str::lower($c['label']);
            $score = levenshtein($term, $label);

            foreach (preg_split('/\s+/', $label, -1, PREG_SPLIT_NO_EMPTY) as $word) {
                if (strlen($word) >= 3) {
                    $score = min($score, levenshtein($term, $word));
                }
            }

            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $c;
            }
        }

        return $best && $bestScore <= $limit ? $best : null;
    }

    // ───────── Data ─────────

    public function with(): array
    {
        $now     = now();
        $nowTime = $now->format('H:i:s');
        $today   = strtolower($now->englishDayOfWeek);
        $active  = $this->activeDay();

        // One query for the whole week; everything below is derived from it in PHP.
        $all = Schedule::with(['show', 'presenters'])
            ->whereHas('show')
            ->orderBy('start_time')
            ->get();

        $byDay = [];
        foreach (self::DAYS as $d) {
            $byDay[$d] = $all
                ->filter(fn ($s) => in_array($d, array_map('strtolower', $s->show->airing_days ?? []), true))
                ->values();
        }

        // ── Filters
        $tokens = preg_split('/\s+/', Str::lower(trim($this->q)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $inBand = function ($slot) {
            $h = (int) substr($slot->start_time, 0, 2);

            return match ($this->time) {
                'morning'   => $h >= 5 && $h <= 11,
                'afternoon' => $h >= 12 && $h <= 16,
                'evening'   => $h >= 17 && $h <= 20,
                'night'     => $h >= 21 || $h <= 4,
                default     => true,
            };
        };

        $passes = function ($slot) use ($tokens, $inBand) {
            if ($this->category !== '' && $slot->show->category !== $this->category) return false;
            if ($this->presenter !== '' && ! $slot->presenters->contains('slug', $this->presenter)) return false;
            if (! $inBand($slot)) return false;

            if ($tokens) {
                $hay = Str::lower(implode(' ', [
                    $slot->show->title,
                    $slot->show->category,
                    $slot->show->description,
                    $slot->label,
                    $slot->presenters->pluck('name')->join(' '),
                ]));

                foreach ($tokens as $t) {
                    if (! str_contains($hay, $t)) return false;
                }
            }

            return true;
        };

        $filtered = [];
        $counts   = [];
        foreach (self::DAYS as $d) {
            $filtered[$d] = $byDay[$d]->filter($passes)->values();
            $counts[$d]   = $filtered[$d]->count();
        }
        $totalAll = array_sum($counts);

        // ── Day tabs (Monday first, today flagged, today active by default)
        $weekStart = $now->copy()->startOfWeek();
        $tabs = [];
        foreach (self::DAYS as $i => $d) {
            $tabs[] = [
                'key'   => $d,
                'short' => ucfirst(substr($d, 0, 3)),
                'date'  => $weekStart->copy()->addDays($i)->day,
                'today' => $d === $today,
                'count' => $counts[$d],
            ];
        }
        $tabs[] = ['key' => 'all', 'short' => 'All', 'date' => null, 'today' => false, 'count' => $totalAll];

        // ── Result groups
        $groups = [];
        foreach (($active === 'all' ? self::DAYS : [$active]) as $d) {
            if ($filtered[$d]->isEmpty()) continue;
            $groups[] = ['day' => $d, 'label' => ucfirst($d), 'today' => $d === $today, 'slots' => $filtered[$d]];
        }

        if ($active === 'all') {
            $heading = 'The whole week';
        } else {
            $date    = $weekStart->copy()->addDays(array_search($active, self::DAYS, true));
            $heading = ($active === $today ? 'Today, ' : '') . $date->format('l j M');
        }

        $otherDays = collect(self::DAYS)
            ->filter(fn ($d) => $d !== $active && $counts[$d] > 0)
            ->map(fn ($d) => ['key' => $d, 'label' => ucfirst($d), 'count' => $counts[$d]])
            ->values();

        // ── What's happening today (unfiltered, so the sidebar is always accurate)
        $endOf      = fn ($s) => $s->end_time <= $s->start_time ? '23:59:59' : $s->end_time;
        $todaySlots = $byDay[$today];
        $liveNow    = $todaySlots->first(fn ($s) => $s->start_time <= $nowTime && $endOf($s) > $nowTime);
        $upNext     = $todaySlots->first(fn ($s) => $s->start_time > $nowTime);
        $airedIds   = $todaySlots->filter(fn ($s) => $endOf($s) <= $nowTime)->pluck('id')->all();

        $liveProgress = 0;
        $liveEndsAt   = null;
        if ($liveNow) {
            $start        = Carbon::parse($liveNow->start_time)->timestamp;
            $end          = Carbon::parse($endOf($liveNow))->timestamp;
            $liveProgress = (int) max(0, min(100, ($now->timestamp - $start) / max(1, $end - $start) * 100));
            $liveEndsAt   = $end;
        }

        // ── Filter options
        $categories = $all->pluck('show.category')->filter()->unique()->sort()->values();
        $presenters = $all->flatMap->presenters->unique('id')->sortBy('name')->values();

        // ── Smart suggestions
        $term    = Str::lower(trim($this->q));
        $suggest = ['shows' => [], 'presenters' => [], 'categories' => [], 'didYouMean' => null];

        if (mb_strlen($term) >= 2) {
            $shows = $all->pluck('show')->unique('id');

            $suggest['shows'] = $shows
                ->filter(fn ($s) => str_contains(Str::lower($s->title), $term))
                ->take(4)
                ->map(function ($s) use ($all) {
                    $slot  = $all->firstWhere('show_id', $s->id);
                    $parts = array_filter([
                        $s->category ? ucfirst($s->category) : null,
                        $slot ? Carbon::parse($slot->start_time)->format('g:i A') : null,
                        $this->daysLabel($s->airing_days ?? []) ?: null,
                    ]);

                    return ['title' => $s->title, 'meta' => implode(', ', $parts)];
                })->values()->all();

            $suggest['presenters'] = $presenters
                ->filter(fn ($p) => str_contains(Str::lower($p->name), $term))
                ->take(3)
                ->map(fn ($p) => ['name' => $p->name, 'slug' => $p->slug, 'role' => $p->role])
                ->values()->all();

            $suggest['categories'] = $categories
                ->filter(fn ($c) => str_contains(Str::lower($c), $term))
                ->take(3)->values()->all();

            if (! $suggest['shows'] && ! $suggest['presenters'] && ! $suggest['categories']) {
                $candidates = array_merge(
                    $shows->map(fn ($s) => ['label' => $s->title, 'type' => 'show', 'value' => $s->title])->all(),
                    $presenters->map(fn ($p) => ['label' => $p->name, 'type' => 'presenter', 'value' => $p->slug])->all(),
                    $categories->map(fn ($c) => ['label' => ucfirst($c), 'type' => 'category', 'value' => $c])->all(),
                );
                $suggest['didYouMean'] = $this->closest($term, $candidates);
            }
        }

        return [
            'today'        => $today,
            'active'       => $active,
            'tabs'         => $tabs,
            'groups'       => $groups,
            'heading'      => $heading,
            'activeCount'  => $active === 'all' ? $totalAll : $counts[$active],
            'otherDays'    => $otherDays,
            'filtersActive' => $this->q !== '' || $this->category !== '' || $this->presenter !== '' || $this->time !== '',
            'tokens'       => $tokens,
            'liveNow'      => $liveNow,
            'upNext'       => $upNext,
            'anchorId'     => $liveNow?->id ?? $upNext?->id,
            'airedIds'     => $airedIds,
            'liveProgress' => $liveProgress,
            'liveEndsAt'   => $liveEndsAt,
            'nowTime'      => $nowTime,
            'categories'   => $categories,
            'presenters'   => $presenters,
            'suggest'      => $suggest,
            'example'      => $all->pluck('show.title')->first() ?? 'news',
            'daysLabel'    => fn (array $d) => $this->daysLabel($d),
        ];
    }
};

?>

<div wire:poll.60s class="min-h-screen bg-white dark:bg-zinc-950">

    @php
        $fmt = fn ($t) => \Illuminate\Support\Carbon::parse($t)->format('g:i A');

        $initials = fn (string $n) => \Illuminate\Support\Str::of($n)
            ->trim()->explode(' ')->filter()->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->join('') ?: '?';

        $until = function (int $ts): string {
            $m = max(0, (int) ceil(($ts - now()->timestamp) / 60));
            if ($m < 1)  return 'any moment now';
            if ($m < 60) return "in {$m} min";
            $r = $m % 60;
            return 'in ' . intdiv($m, 60) . 'h' . ($r ? " {$r}m" : '');
        };

        // Highlights search terms. Splits first, escapes every piece, so it is XSS-safe.
        $hl = function (?string $text) use ($tokens) {
            $text = (string) $text;
            if (! $tokens) return new \Illuminate\Support\HtmlString(e($text));

            $re  = '/(' . implode('|', array_map(fn ($t) => preg_quote($t, '/'), $tokens)) . ')/iu';
            $out = '';
            foreach (preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE) as $i => $part) {
                $out .= $i % 2
                    ? '<mark class="rounded bg-amber-500/30 px-0.5 text-inherit">' . e($part) . '</mark>'
                    : e($part);
            }

            return new \Illuminate\Support\HtmlString($out);
        };

        $timeLabels = [
            'morning'   => 'Morning',
            'afternoon' => 'Afternoon',
            'evening'   => 'Evening',
            'night'     => 'Late night',
        ];

        $showSuggest = mb_strlen(trim($q)) >= 2;
        $hasSuggest  = $suggest['shows'] || $suggest['presenters'] || $suggest['categories'];
    @endphp

    {{-- ═══════════ HERO (same recipe as About and FAQs) ═══════════ --}}
    <div class="relative">
        {{-- Asymmetrical theme glows (maroon left, cyan right) in their own clipped layer,
             so the search dropdown below is never cut off. --}}
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute -left-40 top-0 h-[500px] w-[500px] rounded-full bg-red-800/5 blur-[120px] dark:bg-red-900/10"></div>
            <div class="absolute -right-40 top-20 h-[500px] w-[500px] rounded-full bg-cyan-500/10 blur-[120px] dark:bg-cyan-500/5"></div>
        </div>

        <div class="relative mx-auto max-w-7xl px-4 pb-10 pt-8 sm:px-6 lg:px-8 lg:pb-12 lg:pt-8">
            {{-- Gold badge --}}
            <span class="inline-flex items-center gap-2 rounded-full bg-amber-500/10 px-3 py-1 text-xs font-bold uppercase tracking-wider text-amber-600 ring-1 ring-amber-500/20 dark:text-amber-400">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                Program Guide
            </span>

            <h1 class="mt-6 text-4xl font-extrabold tracking-tight text-zinc-900 dark:text-white sm:text-5xl lg:text-6xl">Our schedule</h1>

            <p class="mt-6 max-w-3xl text-lg leading-relaxed text-zinc-600 dark:text-zinc-400">Browse our programmes, find your favourite shows and never miss a start time.</p>

    {{-- ═══════════ SEARCH + SMART SUGGESTIONS ═══════════ --}}
    <div data-search-wrap class="group/search relative mt-10 max-w-2xl">
        <label for="schedule-search" class="sr-only">Search programmes, presenters or categories</label>

        <flux:icon name="magnifying-glass" class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-zinc-400" />

        <input
            id="schedule-search"
            type="text"
            inputmode="search"
            enterkeyhint="search"
            autocomplete="off"
            maxlength="60"
            wire:model.live.debounce.250ms="q"
            placeholder='Search shows, presenters or categories, e.g. "{{ $example }}"'
            class="w-full rounded-2xl border border-zinc-300 bg-white py-3.5 pl-12 pr-12 text-base text-zinc-900 placeholder-zinc-400 shadow-sm transition focus:border-cyan-500 focus:outline-none focus:ring-4 focus:ring-cyan-500/15 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white dark:placeholder-zinc-500"
        />

        <div class="absolute right-3 top-1/2 flex -translate-y-1/2 items-center">
            <span wire:loading.delay wire:target="q" class="mr-1 h-4 w-4 animate-spin rounded-full border-2 border-cyan-500/30 border-t-cyan-500"></span>
            @if($q !== '')
                <button type="button" wire:click="$set('q', '')" aria-label="Clear search"
                    class="flex h-8 w-8 items-center justify-center rounded-full text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 dark:hover:bg-zinc-800 dark:hover:text-zinc-200">
                    <flux:icon name="x-mark" class="h-4 w-4" />
                </button>
            @endif
        </div>

        @if($showSuggest)
            {{-- mousedown is blocked so the input keeps focus while you click a suggestion --}}
            <div onmousedown="event.preventDefault()"
                 class="absolute inset-x-0 top-full z-30 mt-2 hidden max-h-[26rem] overflow-y-auto rounded-2xl border border-zinc-200 bg-white py-2 shadow-2xl shadow-black/10 group-focus-within/search:block dark:border-zinc-700 dark:bg-zinc-900">

                @if($suggest['shows'])
                    <p class="px-4 pb-1 pt-2 text-xs font-medium text-zinc-500 dark:text-zinc-400">Programmes</p>
                    @foreach($suggest['shows'] as $s)
                        <button type="button" data-suggest wire:key="sg-show-{{ $loop->index }}"
                            wire:click="pick('show', @js($s['title']))" onclick="document.activeElement?.blur()"
                            class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition hover:bg-zinc-50 focus:bg-zinc-50 focus:outline-none dark:hover:bg-zinc-800 dark:focus:bg-zinc-800">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-800 dark:bg-red-950/50 dark:text-red-400">
                                <flux:icon name="tv" class="h-4 w-4" />
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $hl($s['title']) }}</span>
                                @if($s['meta'])<span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $s['meta'] }}</span>@endif
                            </span>
                        </button>
                    @endforeach
                @endif

                @if($suggest['presenters'])
                    <p class="px-4 pb-1 pt-3 text-xs font-medium text-zinc-500 dark:text-zinc-400">Presenters</p>
                    @foreach($suggest['presenters'] as $p)
                        <button type="button" data-suggest wire:key="sg-pres-{{ $p['slug'] }}"
                            wire:click="pick('presenter', @js($p['slug']))" onclick="document.activeElement?.blur()"
                            class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition hover:bg-zinc-50 focus:bg-zinc-50 focus:outline-none dark:hover:bg-zinc-800 dark:focus:bg-zinc-800">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white"
                                  style="background-color: hsl({{ abs(crc32($p['name'])) % 360 }} 55% 42%)">{{ $initials($p['name']) }}</span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $hl($p['name']) }}</span>
                                <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $p['role'] ?: 'Presenter' }}. Show all their programmes</span>
                            </span>
                        </button>
                    @endforeach
                @endif

                @if($suggest['categories'])
                    <p class="px-4 pb-1 pt-3 text-xs font-medium text-zinc-500 dark:text-zinc-400">Categories</p>
                    @foreach($suggest['categories'] as $c)
                        <button type="button" data-suggest wire:key="sg-cat-{{ $loop->index }}"
                            wire:click="pick('category', @js($c))" onclick="document.activeElement?.blur()"
                            class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition hover:bg-zinc-50 focus:bg-zinc-50 focus:outline-none dark:hover:bg-zinc-800 dark:focus:bg-zinc-800">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-cyan-50 text-cyan-600 dark:bg-cyan-950/50 dark:text-cyan-400">
                                <flux:icon name="squares-2x2" class="h-4 w-4" />
                            </span>
                            <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ ucfirst($c) }}</span>
                        </button>
                    @endforeach
                @endif

                @if(! $hasSuggest)
                    @if($suggest['didYouMean'])
                        <button type="button" data-suggest
                            wire:click="pick(@js($suggest['didYouMean']['type']), @js($suggest['didYouMean']['value']))" onclick="document.activeElement?.blur()"
                            class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-zinc-50 focus:bg-zinc-50 focus:outline-none dark:hover:bg-zinc-800 dark:focus:bg-zinc-800">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400">
                                <flux:icon name="sparkles" class="h-4 w-4" />
                            </span>
                            <span class="text-sm text-zinc-600 dark:text-zinc-300">Did you mean <span class="font-semibold text-zinc-900 dark:text-white">{{ $suggest['didYouMean']['label'] }}</span>?</span>
                        </button>
                    @else
                        <p class="px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">Nothing matches "{{ $q }}". Try a show name, a presenter or a category.</p>
                    @endif
                @endif
            </div>
        @endif
    </div>

        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4 pb-20 sm:px-6 lg:px-8">

    {{-- ═══════════ DAY TABS ═══════════ --}}
    <nav aria-label="Choose a day">
        <div role="tablist" class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-2 pt-3 sm:mx-0 sm:px-0">
            @foreach($tabs as $tab)
                @php $isActive = $active === $tab['key']; @endphp
                <button
                    type="button" role="tab"
                    wire:key="tab-{{ $tab['key'] }}"
                    wire:click="setDay('{{ $tab['key'] }}')"
                    aria-selected="{{ $isActive ? 'true' : 'false' }}"
                    @class([
                        'relative flex min-w-[5rem] flex-1 flex-col items-start gap-0.5 rounded-2xl border px-3 py-2.5 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-zinc-950',
                        'border-red-800 bg-red-800 text-white shadow-lg shadow-red-800/25' => $isActive,
                        'border-zinc-200 bg-white text-zinc-700 hover:border-zinc-300 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:border-zinc-700' => ! $isActive,
                        'border-amber-500 dark:border-amber-500' => $tab['today'] && ! $isActive,
                    ])
                >
                    @if($tab['today'])
                        <span class="absolute -top-2.5 left-3 rounded-full bg-amber-400 px-2 py-0.5 text-[11px] font-semibold leading-none text-zinc-900">Today</span>
                    @endif

                    <span class="flex w-full items-baseline justify-between gap-2">
                        <span class="text-sm font-semibold">{{ $tab['short'] }}</span>
                        @if($tab['date'])<span class="text-xs tabular-nums opacity-70">{{ $tab['date'] }}</span>@endif
                    </span>
                    <span @class(['text-xs tabular-nums', 'text-white/75' => $isActive, 'text-zinc-500 dark:text-zinc-400' => ! $isActive])>
                        {{ $tab['count'] }} {{ \Illuminate\Support\Str::plural('show', $tab['count']) }}
                    </span>
                </button>
            @endforeach
        </div>
    </nav>

    {{-- ═══════════ FILTERS ═══════════ --}}
    <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">

        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 lg:mx-0 lg:flex-wrap lg:overflow-visible lg:px-0 lg:pb-0">
            <button type="button" wire:click="$set('category', '')"
                @class([
                    'shrink-0 rounded-full border px-3.5 py-1.5 text-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500',
                    'border-cyan-600 bg-cyan-600 text-white' => $category === '',
                    'border-zinc-200 text-zinc-700 hover:border-cyan-500 hover:text-cyan-700 dark:border-zinc-700 dark:text-zinc-300 dark:hover:text-cyan-400' => $category !== '',
                ])>All categories</button>

            @foreach($categories as $cat)
                <button type="button" wire:key="cat-{{ $loop->index }}" wire:click="setCategory(@js($cat))"
                    @class([
                        'shrink-0 rounded-full border px-3.5 py-1.5 text-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500',
                        'border-cyan-600 bg-cyan-600 text-white' => $category === $cat,
                        'border-zinc-200 text-zinc-700 hover:border-cyan-500 hover:text-cyan-700 dark:border-zinc-700 dark:text-zinc-300 dark:hover:text-cyan-400' => $category !== $cat,
                    ])>{{ ucfirst($cat) }}</button>
            @endforeach
        </div>

        <div class="flex shrink-0 flex-wrap gap-2">
            <label class="sr-only" for="f-presenter">Presenter</label>
            <select id="f-presenter" wire:model.live="presenter"
                class="rounded-xl border border-zinc-200 bg-white py-2 pl-3 pr-9 text-sm text-zinc-700 focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                <option value="">Any presenter</option>
                @foreach($presenters as $p)
                    <option value="{{ $p->slug }}">{{ $p->name }}</option>
                @endforeach
            </select>

            <label class="sr-only" for="f-time">Time of day</label>
            <select id="f-time" wire:model.live="time"
                class="rounded-xl border border-zinc-200 bg-white py-2 pl-3 pr-9 text-sm text-zinc-700 focus:border-cyan-500 focus:outline-none focus:ring-2 focus:ring-cyan-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200">
                <option value="">Any time</option>
                <option value="morning">Morning (5am to 12pm)</option>
                <option value="afternoon">Afternoon (12pm to 5pm)</option>
                <option value="evening">Evening (5pm to 9pm)</option>
                <option value="night">Late night (9pm to 5am)</option>
            </select>
        </div>
    </div>

    @if($filtersActive)
        <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
            <span class="text-zinc-500 dark:text-zinc-400">Showing</span>

            @if($q !== '')
                <button type="button" wire:click="$set('q', '')" class="inline-flex items-center gap-1 rounded-full bg-zinc-100 py-1 pl-3 pr-2 text-zinc-700 transition hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700">
                    "{{ $q }}" <flux:icon name="x-mark" class="h-3.5 w-3.5" />
                </button>
            @endif
            @if($category !== '')
                <button type="button" wire:click="$set('category', '')" class="inline-flex items-center gap-1 rounded-full bg-zinc-100 py-1 pl-3 pr-2 text-zinc-700 transition hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700">
                    {{ ucfirst($category) }} <flux:icon name="x-mark" class="h-3.5 w-3.5" />
                </button>
            @endif
            @if($presenter !== '')
                <button type="button" wire:click="$set('presenter', '')" class="inline-flex items-center gap-1 rounded-full bg-zinc-100 py-1 pl-3 pr-2 text-zinc-700 transition hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700">
                    With {{ $presenters->firstWhere('slug', $presenter)?->name ?? $presenter }} <flux:icon name="x-mark" class="h-3.5 w-3.5" />
                </button>
            @endif
            @if($time !== '')
                <button type="button" wire:click="$set('time', '')" class="inline-flex items-center gap-1 rounded-full bg-zinc-100 py-1 pl-3 pr-2 text-zinc-700 transition hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700">
                    {{ $timeLabels[$time] ?? $time }} <flux:icon name="x-mark" class="h-3.5 w-3.5" />
                </button>
            @endif

            <button type="button" wire:click="resetFilters" class="ml-1 font-medium text-cyan-700 hover:underline dark:text-cyan-400">Clear all</button>
        </div>
    @endif

    {{-- ═══════════ RESULTS + SIDEBAR ═══════════ --}}
    <div class="mt-8 grid gap-10 lg:grid-cols-[minmax(0,1fr)_340px]">

        <section class="min-w-0" aria-live="polite"
                 wire:loading.class="opacity-60" wire:target="q,category,presenter,time,setDay,setCategory,setPresenter,pick,resetFilters">

            <div wire:loading wire:target="q,category,presenter,time,setDay,setCategory,setPresenter,pick,resetFilters"
                 class="mb-3 h-0.5 w-full animate-pulse rounded-full bg-cyan-500"></div>

            <div class="mb-3 flex flex-wrap items-end justify-between gap-x-4 gap-y-1">
                <div>
                    <div class="flex items-center gap-3">
                        <span class="h-px w-6 bg-amber-500"></span>
                        <h2 class="text-xl font-bold uppercase tracking-wide text-zinc-900 dark:text-white">{{ $heading }}</h2>
                    </div>
                    <p class="mt-1 pl-9 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $activeCount }} {{ \Illuminate\Support\Str::plural('programme', $activeCount) }} {{ $filtersActive ? 'match' : 'scheduled' }}
                    </p>
                </div>

                @if($anchorId && ($active === $today || $active === 'all') && collect($groups)->contains('today', true))
                    <a href="#now" class="text-sm font-medium text-cyan-700 hover:underline dark:text-cyan-400">Jump to what's on now</a>
                @endif
            </div>

            @forelse($groups as $g)
                <div wire:key="group-{{ $g['day'] }}" class="mb-10 last:mb-0">

                    @if($active === 'all')
                        <h3 class="mb-2 flex items-center gap-2 text-lg font-bold text-zinc-900 dark:text-white">
                            {{ $g['label'] }}
                            @if($g['today'])
                                <span class="rounded-full bg-amber-400 px-2 py-0.5 text-[11px] font-semibold leading-none text-zinc-900">Today</span>
                            @endif
                            <span class="text-sm font-normal text-zinc-500 dark:text-zinc-400">{{ $g['slots']->count() }} {{ \Illuminate\Support\Str::plural('show', $g['slots']->count()) }}</span>
                        </h3>
                    @endif

                    <ol class="divide-y divide-zinc-200 overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-sm dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-900">
                        @foreach($g['slots'] as $slot)
                            @php
                                $isToday  = $g['today'];
                                $status   = null;
                                if ($isToday) {
                                    if ($liveNow && $slot->id === $liveNow->id)      $status = 'live';
                                    elseif ($upNext && $slot->id === $upNext->id)    $status = 'next';
                                    elseif (in_array($slot->id, $airedIds, true))    $status = 'aired';
                                }
                                $startTs  = \Illuminate\Support\Carbon::parse($slot->start_time)->timestamp;
                                $names    = $slot->presenters->pluck('name')->join(', ');
                                $airs     = $daysLabel($slot->show->airing_days ?? []);
                            @endphp

                            <li wire:key="slot-{{ $g['day'] }}-{{ $slot->id }}"
                                @if($isToday && $slot->id === $anchorId) id="now" @endif
                                @class([
                                    'relative grid scroll-mt-24 grid-cols-[4.75rem_minmax(0,1fr)] gap-x-4 px-5 py-5 transition sm:grid-cols-[5.5rem_minmax(0,1fr)] sm:px-6',
                                    'bg-red-800/5 dark:bg-red-900/20' => $status === 'live',
                                    'bg-amber-500/10'                      => $status === 'next',
                                    'opacity-60'                                => $status === 'aired',
                                    'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' => ! in_array($status, ['live', 'next'], true),
                                ])>

                                @if($status === 'live')
                                    <span class="absolute inset-y-0 left-0 w-1 bg-red-800 dark:bg-red-500"></span>
                                @elseif($status === 'next')
                                    <span class="absolute inset-y-0 left-0 w-1 bg-amber-500"></span>
                                @endif

                                <div class="tabular-nums">
                                    <p @class([
                                        'text-base font-semibold',
                                        'text-red-800 dark:text-red-400'   => $status === 'live',
                                        'text-amber-600 dark:text-amber-400'   => $status === 'next',
                                        'text-zinc-900 dark:text-white'        => ! in_array($status, ['live', 'next'], true),
                                    ])>{{ $fmt($slot->start_time) }}</p>
                                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">to {{ $fmt($slot->end_time) }}</p>
                                </div>

                                <div class="min-w-0">
                                    @if($status === 'live')
                                        <p class="mb-1 flex items-center gap-2 text-sm font-semibold text-red-800 dark:text-red-400">
                                            <span class="relative flex h-2 w-2">
                                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-800 opacity-60 dark:bg-red-400"></span>
                                                <span class="relative inline-flex h-2 w-2 rounded-full bg-red-800 dark:bg-red-400"></span>
                                            </span>
                                            On air now, ends {{ $until($liveEndsAt) }}
                                        </p>
                                    @elseif($status === 'next')
                                        <p class="mb-1 text-sm font-semibold text-amber-600 dark:text-amber-400">Up next, {{ $until($startTs) }}</p>
                                    @elseif($status === 'aired')
                                        <p class="mb-1 text-xs text-zinc-500 dark:text-zinc-400">Already aired</p>
                                    @endif

                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <h4 class="text-lg font-bold leading-snug text-zinc-900 dark:text-white">{{ $hl($slot->show->title) }}</h4>
                                        @if($slot->show->category)
                                            <span class="rounded-full bg-cyan-500/10 px-2.5 py-0.5 text-xs font-medium text-cyan-700 dark:text-cyan-400">{{ $hl(ucfirst($slot->show->category)) }}</span>
                                        @endif
                                    </div>

                                    <p class="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                        @if($slot->show->duration_minutes)<span>{{ $slot->show->duration_label }}</span>@endif
                                        @if($airs)<span>Airs {{ $airs }}</span>@endif
                                    </p>

                                    @if($slot->presenters->isNotEmpty())
                                        <div class="mt-2.5 flex items-center gap-2">
                                            <div class="flex -space-x-1.5">
                                                @foreach($slot->presenters->take(3) as $p)
                                                    <span class="flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-semibold text-white ring-2 ring-white dark:ring-zinc-950"
                                                          style="background-color: hsl({{ abs(crc32($p->name)) % 360 }} 55% 42%)">{{ $initials($p->name) }}</span>
                                                @endforeach
                                            </div>
                                            <span class="truncate text-sm text-zinc-600 dark:text-zinc-300">with {{ $hl($names) }}</span>
                                        </div>
                                    @endif

                                    @if($slot->show->description)
                                        <p class="mt-2 max-w-prose text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">{{ $hl(\Illuminate\Support\Str::limit($slot->show->description, 160)) }}</p>
                                    @endif

                                    @if($status === 'live')
                                        <div class="mt-3 h-1 max-w-sm overflow-hidden rounded-full bg-red-800/15 dark:bg-white/10">
                                            <div class="h-full rounded-full bg-red-800 transition-[width] duration-1000 dark:bg-amber-400" style="width: {{ $liveProgress }}%"></div>
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @empty
                <div class="rounded-3xl border border-dashed border-zinc-300 p-6 sm:p-8 dark:border-zinc-700">
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white">No programmes found</h3>
                    <p class="mt-1 max-w-prose text-sm text-zinc-600 dark:text-zinc-400">
                        @if($q !== '')
                            Nothing matches "{{ $q }}" {{ $active === 'all' ? 'this week' : 'on ' . ucfirst($active) }}.
                        @elseif($filtersActive)
                            No programmes fit these filters {{ $active === 'all' ? 'this week' : 'on ' . ucfirst($active) }}.
                        @else
                            Nothing is scheduled for {{ $active === 'all' ? 'this week' : ucfirst($active) }} yet.
                        @endif
                    </p>

                    @if($suggest['didYouMean'])
                        <button type="button" wire:click="pick(@js($suggest['didYouMean']['type']), @js($suggest['didYouMean']['value']))"
                            class="mt-4 inline-flex items-center gap-2 rounded-full bg-amber-500/20 px-4 py-2 text-sm text-zinc-800 transition hover:bg-amber-500/30 dark:text-zinc-100">
                            <flux:icon name="sparkles" class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                            Did you mean <span class="font-semibold">{{ $suggest['didYouMean']['label'] }}</span>?
                        </button>
                    @endif

                    @if($otherDays->isNotEmpty() && $active !== 'all')
                        <p class="mt-5 text-sm font-medium text-zinc-900 dark:text-white">Available on</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach($otherDays as $od)
                                <button type="button" wire:key="od-{{ $od['key'] }}" wire:click="setDay('{{ $od['key'] }}')"
                                    class="rounded-full border border-zinc-200 px-3.5 py-1.5 text-sm text-zinc-700 transition hover:border-red-800 hover:text-red-800 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-red-400 dark:hover:text-red-400">
                                    {{ $od['label'] }} ({{ $od['count'] }})
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @if($filtersActive)
                        <button type="button" wire:click="resetFilters" class="mt-5 text-sm font-medium text-cyan-700 hover:underline dark:text-cyan-400">Clear search and filters</button>
                    @endif
                </div>
            @endforelse
        </section>

        {{-- Sidebar --}}
        <aside class="min-w-0 space-y-8 lg:sticky lg:top-6 lg:self-start">

            <div class="rounded-3xl bg-red-800 p-6 text-white shadow-xl shadow-red-800/25">
                @if($liveNow)
                    <p class="flex items-center gap-2 text-sm font-medium text-amber-300">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-amber-300 opacity-70"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-amber-300"></span>
                        </span>
                        On air now
                    </p>
                    <h2 class="mt-2 text-2xl font-bold leading-snug">{{ $liveNow->show->title }}</h2>
                    <p class="mt-1 text-sm text-white/70">
                        {{ $fmt($liveNow->start_time) }} to {{ $fmt($liveNow->end_time) }}
                        @if($liveNow->presenters->isNotEmpty()) with {{ $liveNow->presenters->pluck('name')->join(', ') }} @endif
                    </p>

                    <div class="mt-4 h-1 overflow-hidden rounded-full bg-white/15">
                        <div class="h-full rounded-full bg-amber-400 transition-[width] duration-1000" style="width: {{ $liveProgress }}%"></div>
                    </div>
                    <p class="mt-2 text-xs text-white/70">Ends {{ $until($liveEndsAt) }}</p>

                    <a href="{{ url('/') }}" wire:navigate
                       class="mt-5 inline-flex items-center gap-2 rounded-full bg-amber-400 px-4 py-2 text-sm font-semibold text-zinc-900 transition hover:bg-amber-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                        <flux:icon name="play" variant="solid" class="h-4 w-4" />
                        Watch live
                    </a>

                    @if($upNext)
                        <div class="mt-5 border-t border-white/15 pt-4">
                            <p class="text-xs text-white/60">Up next, {{ $until(\Illuminate\Support\Carbon::parse($upNext->start_time)->timestamp) }}</p>
                            <p class="mt-0.5 font-medium">{{ $upNext->show->title }}</p>
                        </div>
                    @endif
                @else
                    <p class="text-sm font-medium text-amber-300">We're off air right now</p>
                    @if($upNext)
                        <h2 class="mt-2 text-2xl font-bold leading-snug">{{ $upNext->show->title }}</h2>
                        <p class="mt-1 text-sm text-white/70">
                            Starts at {{ $fmt($upNext->start_time) }}, {{ $until(\Illuminate\Support\Carbon::parse($upNext->start_time)->timestamp) }}
                        </p>
                    @else
                        <h2 class="mt-2 text-2xl font-bold leading-snug">That's a wrap for today</h2>
                        <p class="mt-1 text-sm text-white/70">Pick another day above to see what's coming up.</p>
                    @endif
                @endif
            </div>

            @if($presenters->isNotEmpty())
                <div>
                    <div class="flex items-center gap-3">
                        <span class="h-px w-6 bg-amber-500"></span>
                        <h2 class="text-sm font-bold uppercase tracking-wide text-zinc-900 dark:text-white">Browse by presenter</h2>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($presenters->take(10) as $p)
                            <button type="button" wire:key="side-p-{{ $p->id }}" wire:click="setPresenter(@js($p->slug))"
                                @class([
                                    'flex items-center gap-2 rounded-full border py-1 pl-1 pr-3 text-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500',
                                    'border-cyan-600 bg-cyan-600 text-white' => $presenter === $p->slug,
                                    'border-zinc-200 text-zinc-700 hover:border-cyan-500 dark:border-zinc-700 dark:text-zinc-300' => $presenter !== $p->slug,
                                ])>
                                <span class="flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-semibold text-white"
                                      style="background-color: hsl({{ abs(crc32($p->name)) % 360 }} 55% 42%)">{{ $initials($p->name) }}</span>
                                {{ $p->name }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </aside>
    </div>

    </div>{{-- /content --}}

    @script
    <script>
    (() => {
        const input = document.getElementById('schedule-search');
        const wrap  = document.querySelector('[data-search-wrap]');
        if (!input || !wrap) return;

        const items = () => Array.from(wrap.querySelectorAll('[data-suggest]'));

        // Arrow keys move through suggestions; Escape clears the search
        input.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') {
                const list = items();
                if (list.length) { e.preventDefault(); list[0].focus(); }
            } else if (e.key === 'Escape') {
                $wire.$set('q', '');
                input.blur();
            }
        });

        wrap.addEventListener('keydown', (e) => {
            const current = e.target.closest('[data-suggest]');
            if (!current) return;

            const list = items();
            const i = list.indexOf(current);

            if (e.key === 'ArrowDown') { e.preventDefault(); (list[i + 1] || list[0]).focus(); }
            if (e.key === 'ArrowUp')   { e.preventDefault(); i > 0 ? list[i - 1].focus() : input.focus(); }
            if (e.key === 'Escape')    { input.focus(); }
        });
    })();
    </script>
    @endscript
</div>
