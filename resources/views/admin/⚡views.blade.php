<?php

use App\Models\Presenter;
use App\Models\Show;
use App\Models\Viewlog;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Url(as: 'from', history: true)]
    public string $from = '';

    #[Url(as: 'to', history: true)]
    public string $to = '';

    #[Url(as: 'show', history: true)]
    public string $showFilter = '';

    #[Url(as: 'presenter', history: true)]
    public string $presenterFilter = '';

    public array $hourlyData   = [];
    public array $daypartData  = [];
    public array $byShowData   = [];

    public int    $totalViews     = 0;
    public int    $uniqueVisitors = 0;
    public string $peakLabel      = '—';
    public int    $peakCount      = 0;
    public float  $avgPerHour     = 0;

    public array $topPresenters   = [];
    public array $recentLogs      = [];
    public array $showsList       = [];
    public array $presentersList  = [];

    public function mount(): void
    {
        if (! $this->from) $this->from = now()->subDays(6)->toDateString();
        if (! $this->to)   $this->to   = now()->toDateString();

        $this->showsList      = Show::orderBy('title')->get(['id', 'title'])->toArray();
        $this->presentersList = Presenter::active()->orderBy('name')->get(['id', 'name'])->toArray();

        $this->refreshStats();
    }

    public function updatedFrom(): void              { $this->refreshStats(); $this->emitChartUpdate(); }
    public function updatedTo(): void                { $this->refreshStats(); $this->emitChartUpdate(); }
    public function updatedShowFilter(): void        { $this->refreshStats(); $this->emitChartUpdate(); }
    public function updatedPresenterFilter(): void   { $this->refreshStats(); $this->emitChartUpdate(); }

    public function resetFilters(): void
    {
        $this->from            = now()->subDays(6)->toDateString();
        $this->to              = now()->toDateString();
        $this->showFilter      = '';
        $this->presenterFilter = '';
        $this->refreshStats();
        $this->emitChartUpdate();
    }

    private function emitChartUpdate(): void
    {
        $this->dispatch('refresh-charts',
            hourly:  $this->hourlyData,
            daypart: $this->daypartData,
            shows:   $this->byShowData,
        );
    }

    private function refreshStats(): void
    {
        $start = Carbon::parse($this->from)->startOfDay();
        $end   = Carbon::parse($this->to)->endOfDay();

        $logs = Viewlog::query()
            ->whereBetween('created_at', [$start, $end])
            ->when($this->showFilter, fn ($q) => $q->where('show_id', (int) $this->showFilter))
            ->when($this->presenterFilter, fn ($q) =>
                $q->whereHas('schedule.presenters',
                    fn ($q) => $q->where('presenters.id', (int) $this->presenterFilter))
            )
            ->with([
                'show:id,title',
                'schedule:id,show_id,start_time,end_time',
                'schedule.presenters:id,name',
            ])
            ->get();

        $this->totalViews     = $logs->count();
        $this->uniqueVisitors = $logs->pluck('ip_address')->filter()->unique()->count();

        // Hourly buckets
        $hourly = collect(range(0, 23))->mapWithKeys(fn ($h) => [
            $h => $logs->filter(fn ($l) => $l->created_at->hour === $h)->count(),
        ]);
        $this->hourlyData = $hourly->toArray();
        $this->peakCount  = (int) $hourly->max();
        $peakHour         = $hourly->sortDesc()->keys()->first();
        $this->peakLabel  = $this->peakCount > 0 ? $this->formatHour((int) $peakHour) : '—';

        $days             = max(1, $start->diffInDays($end) + 1);
        $this->avgPerHour = round($this->totalViews / ($days * 24), 1);

        // Daypart
        $buckets = [
            'Morning (5–11)'   => 0,
            'Afternoon (12–16)' => 0,
            'Evening (17–21)'  => 0,
            'Night (22–4)'     => 0,
        ];
        foreach ($logs as $log) {
            $h = $log->created_at->hour;
            if ($h >= 5  && $h <= 11) $buckets['Morning (5–11)']++;
            elseif ($h >= 12 && $h <= 16) $buckets['Afternoon (12–16)']++;
            elseif ($h >= 17 && $h <= 21) $buckets['Evening (17–21)']++;
            else $buckets['Night (22–4)']++;
        }
        $this->daypartData = $buckets;

        // By show
        $this->byShowData = $logs->filter(fn ($l) => $l->show)
            ->groupBy('show_id')
            ->map(fn ($grp) => [
                'label' => $grp->first()->show->title ?? '—',
                'count' => $grp->count(),
            ])
            ->sortByDesc('count')
            ->take(8)
            ->values()
            ->toArray();

        // Top presenters
        $this->topPresenters = Presenter::active()
            ->get(['id', 'name'])
            ->map(function ($p) use ($logs) {
                $count = $logs->filter(fn ($l) =>
                    $l->schedule && $l->schedule->presenters->contains('id', $p->id)
                )->count();
                return ['id' => $p->id, 'name' => $p->name, 'views' => $count];
            })
            ->sortByDesc('views')
            ->take(10)
            ->values()
            ->toArray();

        // Recent logs
        $this->recentLogs = $logs->sortByDesc('created_at')->take(20)->map(fn ($l) => [
            'when'     => $l->created_at->format('M j, H:i'),
            'show'     => $l->show?->title ?? '—',
            'schedule' => $l->schedule
                ? Carbon::parse($l->schedule->start_time)->format('H:i')
                  . '–'
                  . Carbon::parse($l->schedule->end_time)->format('H:i')
                : '—',
            'ip' => $l->ip_address ?? '—',
        ])->values()->toArray();
    }

    private function formatHour(int $h): string
    {
        $suffix  = $h < 12 ? 'AM' : 'PM';
        $display = $h % 12 ?: 12;
        return "{$display} {$suffix}";
    }
};

?>

<div class="space-y-6">
    {{-- Header --}}
    <div>
        <flux:heading size="xl">Views & Analytics</flux:heading>
        <flux:subheading>Traffic insights from your view logs.</flux:subheading>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap items-end gap-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:input wire:model.live="from" type="date" label="From" />
        <flux:input wire:model.live="to"   type="date" label="To" />

        <flux:select wire:model.live="showFilter" label="Show" class="min-w-[180px]">
            <flux:select.option value="">All shows</flux:select.option>
            @foreach($showsList as $show)
                <flux:select.option value="{{ $show['id'] }}">{{ $show['title'] }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="presenterFilter" label="Presenter" class="min-w-[180px]">
            <flux:select.option value="">All presenters</flux:select.option>
            @foreach($presentersList as $p)
                <flux:select.option value="{{ $p['id'] }}">{{ $p['name'] }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:button variant="ghost" icon="arrow-path" wire:click="resetFilters">Reset</flux:button>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Total Views</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format($totalViews) }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Unique Visitors</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format($uniqueVisitors) }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Peak Hour</p>
            <p class="mt-1 text-2xl font-semibold">{{ $peakLabel }}</p>
            <p class="text-xs text-zinc-500">{{ number_format($peakCount) }} views</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Avg / Hour</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format($avgPerHour, 1) }}</p>
        </div>
    </div>

    {{-- Charts (frozen from Livewire, updated via event) --}}
    <div wire:ignore>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>

        <div
            x-data="{
                hourly: null, daypart: null, shows: null,
                build(data) {
                    this.destroy();

                    const isDark    = document.documentElement.classList.contains('dark');
                    const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)';
                    const textColor = isDark ? '#a1a1aa' : '#52525b';

                    this.hourly = new Chart(this.$refs.hourlyCanvas, {
                        type: 'bar',
                        data: {
                            labels: Array.from({length: 24}, (_, i) => String(i).padStart(2, '0') + ':00'),
                            datasets: [{
                                label: 'Views',
                                data: Object.values(data.hourly),
                                backgroundColor: 'rgba(99, 102, 241, 0.75)',
                                borderRadius: 4,
                            }],
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { ticks: { color: textColor, maxRotation: 0, autoSkip: true }, grid: { display: false } },
                                y: { ticks: { color: textColor, precision: 0 }, grid: { color: gridColor }, beginAtZero: true },
                            },
                        },
                    });

                    this.daypart = new Chart(this.$refs.daypartCanvas, {
                        type: 'doughnut',
                        data: {
                            labels: Object.keys(data.daypart),
                            datasets: [{
                                data: Object.values(data.daypart),
                                backgroundColor: ['#f59e0b', '#10b981', '#6366f1', '#3b82f6'],
                                borderWidth: 0,
                            }],
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom', labels: { color: textColor, boxWidth: 12, padding: 12 } } },
                        },
                    });

                    this.shows = new Chart(this.$refs.showsCanvas, {
                        type: 'pie',
                        data: {
                            labels: data.shows.map(s => s.label),
                            datasets: [{
                                data: data.shows.map(s => s.count),
                                backgroundColor: ['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981','#06b6d4','#f43f5e','#84cc16'],
                                borderWidth: 0,
                            }],
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom', labels: { color: textColor, boxWidth: 12, padding: 12 } } },
                        },
                    });
                },
                destroy() {
                    this.hourly?.destroy();
                    this.daypart?.destroy();
                    this.shows?.destroy();
                },
            }"
            x-init="build({ hourly: @js($hourlyData), daypart: @js($daypartData), shows: @js($byShowData) })"
            x-on:refresh-charts.window="
                build({
                    hourly:  $event.detail.hourly,
                    daypart: $event.detail.daypart,
                    shows:   $event.detail.shows,
                })
            "
        >
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900 lg:col-span-2">
                    <h3 class="mb-3 text-sm font-medium">Views by Hour</h3>
                    <div class="h-64"><canvas x-ref="hourlyCanvas"></canvas></div>
                </div>
                <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-3 text-sm font-medium">Daypart Breakdown</h3>
                    <div class="h-64"><canvas x-ref="daypartCanvas"></canvas></div>
                </div>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-3 text-sm font-medium">Top Shows</h3>
                    @if(count($byShowData) === 0)
                        <p class="py-20 text-center text-sm text-zinc-500">No data for this range.</p>
                    @else
                        <div class="h-64"><canvas x-ref="showsCanvas"></canvas></div>
                    @endif
                </div>
                <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <h3 class="mb-3 text-sm font-medium">Top Presenters</h3>
                    @if(count($topPresenters) === 0 || collect($topPresenters)->sum('views') === 0)
                        <p class="py-20 text-center text-sm text-zinc-500">No data for this range.</p>
                    @else
                        <ol class="space-y-2">
                            @foreach($topPresenters as $i => $p)
                                <li class="flex items-center gap-3">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                        {{ $i + 1 }}
                                    </span>
                                    <span class="flex-1 truncate text-sm">{{ $p['name'] }}</span>
                                    <span class="text-sm font-medium">{{ number_format($p['views']) }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Recent logs --}}
    <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
            <h3 class="text-sm font-medium">Recent Views (last 20)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-zinc-50 text-left text-xs uppercase text-zinc-500 dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-4 py-2">When</th>
                        <th class="px-4 py-2">Show</th>
                        <th class="px-4 py-2">Slot</th>
                        <th class="px-4 py-2">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($recentLogs as $log)
                        <tr>
                            <td class="px-4 py-2 text-zinc-600 dark:text-zinc-400">{{ $log['when'] }}</td>
                            <td class="px-4 py-2">{{ $log['show'] }}</td>
                            <td class="px-4 py-2 font-mono text-xs text-zinc-500">{{ $log['schedule'] }}</td>
                            <td class="px-4 py-2 font-mono text-xs text-zinc-500">{{ $log['ip'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-zinc-500">No logs in this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
