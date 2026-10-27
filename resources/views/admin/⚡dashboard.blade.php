<?php

use App\Models\Bannedword;
use App\Models\Comment;
use App\Models\Contactform;
use App\Models\Presenter;
use App\Models\Schedule;
use App\Models\Show;
use App\Models\Viewlog;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function mount(): void
    {
        $unread   = Contactform::where('is_read', false)->count();
        $flagged  = Comment::where('has_banned_word', true)->count();

        if ($unread > 0) {
            Flux::toast(
                text: "You have {$unread} unread contact form message" . ($unread === 1 ? '' : 's'),
                variant: 'info',
            );
        }
        if ($flagged > 0) {
            Flux::toast(
                text: "{$flagged} comment" . ($flagged === 1 ? ' is' : 's are') . ' flagged for moderation',
                variant: 'warning',
            );
        }
    }

    public function with(): array
    {
        $today         = today();
        $weekStart     = now()->startOfWeek();
        $lastWeekStart = now()->subWeek()->startOfWeek();
        $lastWeekEnd   = now()->subWeek()->endOfWeek();

        // ── Live / Up Next ──
        $liveNow = Schedule::with(['show:id,title,thumbnail,category', 'presenters:id,name'])
            ->liveNow()
            ->first();

        $upNext = Schedule::with(['show:id,title,category', 'presenters:id,name'])
            ->upNext()
            ->first();

        $todaySchedule = Schedule::with(['show:id,title', 'presenters:id,name'])
            ->today()
            ->orderBy('start_time')
            ->get();

        // ── Views ──
        $viewsToday     = Viewlog::whereDate('created_at', $today)->count();
        $viewsYesterday = Viewlog::whereDate('created_at', today()->subDay())->count();
        $viewsWeek      = Viewlog::whereBetween('created_at', [$weekStart, now()])->count();
        $viewsLastWeek  = Viewlog::whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])->count();

        $viewsDelta = $viewsYesterday > 0
            ? round((($viewsToday - $viewsYesterday) / $viewsYesterday) * 100, 1)
            : ($viewsToday > 0 ? 100.0 : 0.0);

        // ── 7-day sparkline ──
        $daily = Viewlog::select(
                DB::raw('date(created_at) as day'),
                DB::raw('count(*) as total')
            )
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->toArray();

        $sparkline = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $sparkline[] = [
                'date'  => $date,
                'label' => Carbon::parse($date)->format('D'),
                'count' => (int) ($daily[$date] ?? 0),
            ];
        }
        $sparklineMax = max(1, collect($sparkline)->max('count'));

        // ── Unique visitors this week ──
        $uniqueVisitors = Viewlog::whereBetween('created_at', [$weekStart, now()])
            ->distinct('ip_address')
            ->count('ip_address');

        // ── Peak hour this week ──
        $hourlyBuckets = Viewlog::whereBetween('created_at', [$weekStart, now()])
            ->get(['created_at'])
            ->groupBy(fn ($v) => $v->created_at->hour)
            ->map->count()
            ->sortDesc();

        $peakHour  = $hourlyBuckets->keys()->first();
        $peakCount = $hourlyBuckets->first() ?? 0;
        $peakLabel = $peakHour !== null
            ? (($peakHour % 12) ?: 12) . ' ' . ($peakHour < 12 ? 'AM' : 'PM')
            : '—';

        // ── Top shows this week ──
        $topShows = Viewlog::whereBetween('created_at', [$weekStart, now()])
            ->whereNotNull('show_id')
            ->select('show_id', DB::raw('count(*) as views'))
            ->groupBy('show_id')
            ->orderByDesc('views')
            ->limit(5)
            ->with('show:id,title,thumbnail')
            ->get()
            ->filter(fn ($v) => $v->show);

        // ── Top presenters this week ──
        $viewsWithPresenters = Viewlog::whereBetween('created_at', [$weekStart, now()])
            ->whereNotNull('schedule_id')
            ->with('schedule.presenters:id,name')
            ->get();

        $presenterCounts = $viewsWithPresenters
            ->flatMap(fn ($v) => $v->schedule?->presenters->pluck('id') ?? [])
            ->countBy()
            ->toArray();

        $topPresenters = Presenter::active()
            ->get(['id', 'name'])
            ->map(fn ($p) => [
                'id'    => $p->id,
                'name'  => $p->name,
                'views' => (int) ($presenterCounts[$p->id] ?? 0),
            ])
            ->sortByDesc('views')
            ->take(5)
            ->values();

        // ── Alerts ──
        $unreadContacts  = Contactform::where('is_read', false)->count();
        $flaggedComments = Comment::where('has_banned_word', true)->count();
        $todayComments   = Comment::whereDate('created_at', $today)->count();
        $activeBanned    = Bannedword::where('is_active', true)->count();

        // ── Recent activity ──
        $recentComments = Comment::with('show:id,title')
            ->latest()
            ->limit(5)
            ->get();

        $recentContacts = Contactform::latest()
            ->limit(5)
            ->get();

        // ── Content counts ──
        $totalShows      = Show::count();
        $activeShows     = Show::where('is_active', true)->count();
        $totalPresenters = Presenter::count();
        $totalSlots      = Schedule::count();

        return compact(
            'liveNow', 'upNext', 'todaySchedule',
            'viewsToday', 'viewsDelta', 'viewsWeek', 'viewsLastWeek',
            'sparkline', 'sparklineMax', 'uniqueVisitors', 'peakLabel', 'peakCount',
            'topShows', 'topPresenters',
            'unreadContacts', 'flaggedComments', 'todayComments', 'activeBanned',
            'recentComments', 'recentContacts',
            'totalShows', 'activeShows', 'totalPresenters', 'totalSlots',
        );
    }
};

?>

<div class="space-y-6">

    {{-- ── Header ── --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl">Welcome back, {{ auth()->user()->name }}</flux:heading>
            <flux:subheading>{{ now()->format('l, F j, Y') }} · {{ now()->format('g:i A') }}</flux:subheading>
        </div>
        <flux:button icon="arrow-path" wire:click="$refresh" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="$refresh">Refresh</span>
            <span wire:loading wire:target="$refresh">Loading…</span>
        </flux:button>
    </div>

    {{-- ── Alert banners ── --}}
    @if($unreadContacts > 0 || $flaggedComments > 0)
        <div class="grid gap-3 sm:grid-cols-2">
            @if($unreadContacts > 0)
                <a href="{{ route('admin.contactforms') }}" wire:navigate
                   class="flex items-center gap-3 rounded-lg border border-indigo-200 bg-indigo-50 p-3 transition hover:border-indigo-400 hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-950/40 dark:hover:bg-indigo-950/60">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-500 text-white">
                        <flux:icon name="envelope" class="h-5 w-5" />
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-indigo-900 dark:text-indigo-200">
                            {{ $unreadContacts }} unread message{{ $unreadContacts === 1 ? '' : 's' }}
                        </p>
                        <p class="text-xs text-indigo-700 dark:text-indigo-400">Click to open inbox</p>
                    </div>
                    <flux:icon name="chevron-right" class="h-4 w-4 text-indigo-500" />
                </a>
            @endif

            @if($flaggedComments > 0)
                <a href="{{ route('admin.comments') }}" wire:navigate
                   class="flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 p-3 transition hover:border-red-400 hover:bg-red-100 dark:border-red-800 dark:bg-red-950/40 dark:hover:bg-red-950/60">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-500 text-white">
                        <flux:icon name="shield-exclamation" class="h-5 w-5" />
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-red-900 dark:text-red-200">
                            {{ $flaggedComments }} flagged comment{{ $flaggedComments === 1 ? '' : 's' }}
                        </p>
                        <p class="text-xs text-red-700 dark:text-red-400">Review banned-word hits</p>
                    </div>
                    <flux:icon name="chevron-right" class="h-4 w-4 text-red-500" />
                </a>
            @endif
        </div>
    @endif

    {{-- ── Live Now + Up Next ── --}}
    <div class="grid gap-4 lg:grid-cols-3">

        {{-- Live now --}}
        <div class="lg:col-span-2">
            @if($liveNow)
                <div class="relative overflow-hidden rounded-xl border border-emerald-300 bg-gradient-to-br from-emerald-50 to-white p-5 dark:border-emerald-800 dark:from-emerald-950/40 dark:to-zinc-900">
                    <div class="flex items-start gap-4">
                        <div class="shrink-0">
                            @if($liveNow->show->thumbnail)
                                <img src="{{ asset('storage/'.$liveNow->show->thumbnail) }}"
                                     class="h-24 w-40 rounded-lg object-cover shadow-sm" alt="" />
                            @else
                                <div class="flex h-24 w-40 items-center justify-center rounded-lg bg-zinc-200 dark:bg-zinc-800">
                                    <flux:icon name="tv" class="h-8 w-8 text-zinc-400" />
                                </div>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="relative flex h-2 w-2">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                                    <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500"></span>
                                </span>
                                <span class="text-xs font-bold uppercase tracking-wider text-red-600 dark:text-red-400">On Air Now</span>
                            </div>
                            <h3 class="mt-1 truncate text-xl font-bold">{{ $liveNow->show->title }}</h3>
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ \Illuminate\Support\Carbon::parse($liveNow->start_time)->format('g:i A') }}
                                – {{ \Illuminate\Support\Carbon::parse($liveNow->end_time)->format('g:i A') }}
                            </p>
                            @if($liveNow->presenters->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach($liveNow->presenters as $p)
                                        <span class="rounded-full bg-white px-2 py-0.5 text-xs font-medium shadow-sm dark:bg-zinc-800">
                                            {{ $p->name }}
                                            @if($p->pivot->role)
                                                <span class="text-zinc-500">· {{ $p->pivot->role }}</span>
                                            @endif
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="flex h-full items-center justify-center rounded-xl border border-dashed border-zinc-300 bg-zinc-50 p-8 dark:border-zinc-700 dark:bg-zinc-900/50">
                    <div class="text-center">
                        <flux:icon name="tv" class="mx-auto h-10 w-10 text-zinc-400" />
                        <p class="mt-2 text-sm text-zinc-500">Nothing airing right now</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- Up next --}}
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">Up Next</p>
            @if($upNext)
                <p class="mt-2 text-lg font-semibold truncate">{{ $upNext->show->title }}</p>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">
                    Starts {{ \Illuminate\Support\Carbon::parse($upNext->start_time)->format('g:i A') }}
                </p>
                @if($upNext->presenters->isNotEmpty())
                    <p class="mt-1 truncate text-xs text-zinc-500">
                        with {{ $upNext->presenters->pluck('name')->join(', ') }}
                    </p>
                @endif
            @else
                <p class="mt-2 text-sm text-zinc-500">Nothing else scheduled today</p>
            @endif

            <div class="mt-4 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                <p class="text-xs font-medium uppercase tracking-wider text-zinc-500">Content</p>
                <div class="mt-2 space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span class="text-zinc-600 dark:text-zinc-400">Active shows</span>
                        <span class="font-medium">{{ $activeShows }} / {{ $totalShows }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-600 dark:text-zinc-400">Presenters</span>
                        <span class="font-medium">{{ $totalPresenters }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-zinc-600 dark:text-zinc-400">Total slots</span>
                        <span class="font-medium">{{ $totalSlots }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── KPI row ── --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-zinc-500">Views Today</p>
                @if($viewsDelta > 0)
                    <span class="rounded-full bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400">
                        ▲ {{ $viewsDelta }}%
                    </span>
                @elseif($viewsDelta < 0)
                    <span class="rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-700 dark:bg-red-950 dark:text-red-400">
                        ▼ {{ abs($viewsDelta) }}%
                    </span>
                @else
                    <span class="rounded-full bg-zinc-100 px-1.5 py-0.5 text-[10px] font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                        —
                    </span>
                @endif
            </div>
            <p class="mt-1 text-2xl font-bold">{{ number_format($viewsToday) }}</p>
            <p class="text-xs text-zinc-500">vs yesterday</p>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">This Week</p>
            <p class="mt-1 text-2xl font-bold">{{ number_format($viewsWeek) }}</p>
            <p class="text-xs text-zinc-500">
                Last week: {{ number_format($viewsLastWeek) }}
            </p>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Unique Visitors</p>
            <p class="mt-1 text-2xl font-bold">{{ number_format($uniqueVisitors) }}</p>
            <p class="text-xs text-zinc-500">Distinct IPs this week</p>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Peak Hour</p>
            <p class="mt-1 text-2xl font-bold">{{ $peakLabel }}</p>
            <p class="text-xs text-zinc-500">{{ number_format($peakCount) }} views at peak</p>
        </div>
    </div>

    {{-- ── Views trend + summary ── --}}
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900 lg:col-span-2">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold">Views — last 7 days</h3>
                <span class="text-xs text-zinc-500">{{ array_sum(array_column($sparkline, 'count')) }} total</span>
            </div>
            <div class="mt-4 flex h-40 items-end gap-2">
                @foreach($sparkline as $day)
                    <div class="group flex flex-1 flex-col items-center gap-1">
                        <span class="text-xs font-medium text-zinc-500 opacity-0 transition group-hover:opacity-100">
                            {{ $day['count'] }}
                        </span>
                        <div class="w-full rounded-t-md bg-indigo-500 transition group-hover:bg-indigo-600"
                             style="height: {{ max(4, ($day['count'] / $sparklineMax) * 100) }}%;"
                             title="{{ $day['date'] }} — {{ $day['count'] }} views"></div>
                        <span class="text-xs text-zinc-500">{{ $day['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <h3 class="text-sm font-semibold">Community</h3>
            <div class="mt-3 space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-zinc-600 dark:text-zinc-400">Comments today</span>
                    <span class="font-semibold">{{ number_format($todayComments) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-zinc-600 dark:text-zinc-400">Flagged</span>
                    <span @class([
                        'font-semibold',
                        'text-red-600 dark:text-red-400' => $flaggedComments > 0,
                    ])>{{ number_format($flaggedComments) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-zinc-600 dark:text-zinc-400">Unread contacts</span>
                    <span @class([
                        'font-semibold',
                        'text-indigo-600 dark:text-indigo-400' => $unreadContacts > 0,
                    ])>{{ number_format($unreadContacts) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-zinc-600 dark:text-zinc-400">Banned words</span>
                    <span class="font-semibold">{{ number_format($activeBanned) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Top shows + Top presenters ── --}}
    <div class="grid gap-4 lg:grid-cols-2">

        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold">Top Shows This Week</h3>
                <a href="{{ route('admin.shows') }}" wire:navigate class="text-xs text-indigo-600 hover:underline dark:text-indigo-400">View all</a>
            </div>
            @if($topShows->isEmpty())
                <p class="py-8 text-center text-xs text-zinc-500">No views logged this week.</p>
            @else
                <ol class="mt-3 space-y-2">
                    @foreach($topShows as $i => $row)
                        <li class="flex items-center gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                {{ $i + 1 }}
                            </span>
                            @if($row->show->thumbnail)
                                <img src="{{ asset('storage/'.$row->show->thumbnail) }}" class="h-8 w-12 rounded object-cover" alt="" />
                            @else
                                <div class="flex h-8 w-12 items-center justify-center rounded bg-zinc-100 dark:bg-zinc-800">
                                    <flux:icon name="tv" class="h-3 w-3 text-zinc-400" />
                                </div>
                            @endif
                            <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $row->show->title }}</span>
                            <span class="shrink-0 text-xs font-semibold text-zinc-600 dark:text-zinc-400">{{ number_format($row->views) }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold">Top Presenters This Week</h3>
                <a href="{{ route('admin.presenters') }}" wire:navigate class="text-xs text-indigo-600 hover:underline dark:text-indigo-400">View all</a>
            </div>
            @if($topPresenters->isEmpty() || $topPresenters->sum('views') === 0)
                <p class="py-8 text-center text-xs text-zinc-500">No presenter views this week.</p>
            @else
                <ol class="mt-3 space-y-2">
                    @foreach($topPresenters as $i => $p)
                        <li class="flex items-center gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-400">
                                {{ $i + 1 }}
                            </span>
                            <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ $p['name'] }}</span>
                            <span class="shrink-0 text-xs font-semibold text-zinc-600 dark:text-zinc-400">{{ number_format($p['views']) }}</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </div>

    {{-- ── Today's schedule + Recent activity ── --}}
    <div class="grid gap-4 lg:grid-cols-2">

        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold">Today's Schedule</h3>
                <a href="{{ route('admin.schedules') }}" wire:navigate class="text-xs text-indigo-600 hover:underline dark:text-indigo-400">Manage</a>
            </div>
            @if($todaySchedule->isEmpty())
                <p class="py-8 text-center text-xs text-zinc-500">Nothing scheduled for today.</p>
            @else
                <ol class="mt-3 max-h-80 space-y-2 overflow-y-auto">
                    @foreach($todaySchedule as $slot)
                        @php $isLive = $slot->is_live_now; @endphp
                        <li wire:key="sched-{{ $slot->id }}"
                            @class([
                                'flex items-center gap-3 rounded-md border px-2 py-1.5',
                                'border-emerald-300 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30' => $isLive,
                                'border-transparent' => ! $isLive,
                            ])>
                            <span class="shrink-0 font-mono text-xs text-zinc-600 dark:text-zinc-400">
                                {{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('H:i') }}
                            </span>
                            <span class="min-w-0 flex-1 truncate text-sm {{ $isLive ? 'font-semibold' : '' }}">
                                {{ $slot->show->title }}
                            </span>
                            @if($slot->presenters->isNotEmpty())
                                <span class="shrink-0 truncate text-xs text-zinc-500">{{ $slot->presenters->pluck('name')->join(', ') }}</span>
                            @endif
                            @if($isLive)
                                <span class="shrink-0 rounded-full bg-red-500 px-1.5 py-0.5 text-[9px] font-bold uppercase text-white">Live</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <h3 class="text-sm font-semibold">Recent Activity</h3>

            <div class="mt-3 space-y-4">
                <div>
                    <p class="mb-1.5 text-xs font-medium uppercase tracking-wider text-zinc-500">Latest comments</p>
                    @if($recentComments->isEmpty())
                        <p class="text-xs text-zinc-500 italic">No comments yet.</p>
                    @else
                        <ul class="space-y-1.5">
                            @foreach($recentComments as $c)
                                <li @class([
                                    'flex items-start gap-2 text-xs',
                                    'opacity-60 line-through' => $c->has_banned_word,
                                ])>
                                    <span class="shrink-0 font-semibold text-zinc-700 dark:text-zinc-300">{{ $c->name }}:</span>
                                    <span class="min-w-0 flex-1 truncate text-zinc-600 dark:text-zinc-400">{{ $c->body }}</span>
                                    <span class="shrink-0 text-zinc-400">{{ $c->created_at->diffForHumans(short: true) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="border-t border-zinc-200 pt-3 dark:border-zinc-700">
                    <p class="mb-1.5 text-xs font-medium uppercase tracking-wider text-zinc-500">Latest contact forms</p>
                    @if($recentContacts->isEmpty())
                        <p class="text-xs text-zinc-500 italic">No contact submissions.</p>
                    @else
                        <ul class="space-y-1.5">
                            @foreach($recentContacts as $c)
                                <li class="flex items-start gap-2 text-xs">
                                    <span class="shrink-0 font-semibold text-zinc-700 dark:text-zinc-300">{{ $c->name }}:</span>
                                    <span class="min-w-0 flex-1 truncate text-zinc-600 dark:text-zinc-400">{{ $c->subject ?: $c->message }}</span>
                                    <span class="shrink-0 text-zinc-400">{{ $c->created_at->diffForHumans(short: true) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
