<?php

use App\Models\Presenter;
use App\Models\Schedule;
use App\Models\Show;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[Url(as: 'show', history: true)]
    public string $showFilter = '';

    #[Url(as: 'presenter', history: true)]
    public string $presenterFilter = '';

    public const ALL_DAYS = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];

    // ── Form ──────────────────────────────────────────────────
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $show_id    = '';
    public string $start_time = '09:00';
    public string $end_time   = '10:00';
    public string $label      = '';

    /** @var array<int, array{presenter_id: string, role: string}> */
    public array $slotPresenters = [];

    // ── Delete ────────────────────────────────────────────────
    public bool    $showDeleteModal = false;
    public ?int    $deletingId      = null;
    public ?string $deletingLabel   = '';
    public ?string $deleteUnlockAt  = null;
    public string  $deleteConfirmText = '';

    // ── Reference data ────────────────────────────────────────
    public array $availableShows      = [];
    public array $availablePresenters = [];

    public function mount(): void
    {
        $this->loadReferenceData();
    }

    public function loadReferenceData(): void
    {
        $this->availableShows = Show::active()
            ->orderBy('title')
            ->get(['id', 'title', 'duration_minutes', 'airing_days'])
            ->toArray();

        $this->availablePresenters = Presenter::active()
            ->orderBy('name')
            ->get(['id', 'name', 'role'])
            ->toArray();
    }

    public function updatedShowFilter(): void      { $this->resetPage(); }
    public function updatedPresenterFilter(): void { $this->resetPage(); }

    // ── Auto-fill end_time ───────────────────────────────────
    public function updatedShowId(): void    { $this->recomputeEndTime(); }
    public function updatedStartTime(): void { $this->recomputeEndTime(); }

    private function recomputeEndTime(): void
    {
        if (! $this->show_id || ! $this->start_time) return;

        $show = Show::find($this->show_id);
        if (! $show) return;

        try {
            $start = Carbon::parse($this->start_time);
            $this->end_time = $start->copy()
                ->addMinutes($show->duration_minutes)
                ->format('H:i');
        } catch (\Throwable) {
            // leave end_time as-is
        }
    }

    // ── Form open ─────────────────────────────────────────────
    public function openCreate(): void
    {
        if (empty($this->availableShows)) {
            Flux::toast('Create at least one active show first.', variant: 'danger');
            return;
        }

        $this->reset(['editingId', 'label', 'slotPresenters']);
        $this->show_id    = (string) $this->availableShows[0]['id'];
        $this->start_time = '09:00';
        $this->recomputeEndTime();
        $this->slotPresenters = [];
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $s = Schedule::with('presenters')->findOrFail($id);

        $this->editingId  = $s->id;
        $this->show_id    = (string) $s->show_id;
        $this->start_time = substr($s->start_time, 0, 5);   // "13:00:00" → "13:00"
        $this->end_time   = substr($s->end_time,   0, 5);
        $this->label      = $s->label ?? '';

        $this->slotPresenters = $s->presenters->map(fn ($p) => [
            'presenter_id' => (string) $p->id,
            'role'         => $p->pivot->role ?? '',
        ])->values()->toArray();

        $this->resetValidation();
        $this->showModal = true;
    }

    // ── Presenter repeater ────────────────────────────────────
    public function addPresenterRow(): void
    {
        $this->slotPresenters[] = ['presenter_id' => '', 'role' => ''];
    }

    public function removePresenterRow(int $index): void
    {
        unset($this->slotPresenters[$index]);
        $this->slotPresenters = array_values($this->slotPresenters);
    }

    // ── Save ──────────────────────────────────────────────────
    public function save(): void
    {
        $this->validate([
            'show_id'    => ['required', 'exists:shows,id'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time'   => ['required', 'date_format:H:i', 'after:start_time'],
            'label'      => ['nullable', 'string', 'max:100'],
        ], [
            'end_time.after' => 'End time must be after start time.',
        ]);

        // Overlap check
        $conflicts = $this->findConflicts();
        if ($conflicts->isNotEmpty()) {
            $this->addError('start_time',
                'Overlaps with: ' . $conflicts->map(fn ($c) =>
                    "{$c->show->title} ({$c->time_label})"
                )->join(', '));
            return;
        }

        $data = [
            'show_id'    => (int) $this->show_id,
            'start_time' => $this->start_time . ':00',
            'end_time'   => $this->end_time   . ':00',
            'label'      => $this->label ?: null,
        ];

        $slot = $this->editingId
            ? tap(Schedule::findOrFail($this->editingId))->update($data)
            : Schedule::create($data);

        // Sync presenters with role + order
        $sync = [];
        foreach ($this->slotPresenters as $i => $row) {
            if (empty($row['presenter_id'])) continue;
            $sync[(int) $row['presenter_id']] = [
                'role'       => $row['role'] ?: null,
                'sort_order' => $i,
            ];
        }
        $slot->presenters()->sync($sync);

        $this->showModal = false;
        Flux::toast(
            $this->editingId ? 'Slot updated.' : 'Slot created.',
            variant: 'success'
        );
    }

    private function findConflicts()
    {
        $newShow = Show::find($this->show_id);
        if (! $newShow) return collect();

        $newStart = $this->start_time . ':00';
        $newEnd   = $this->end_time   . ':00';

        return Schedule::with('show')
            ->where('id', '!=', $this->editingId ?? 0)
            ->get()
            ->filter(function (Schedule $s) use ($newShow, $newStart, $newEnd) {
                $sharedDays = array_intersect(
                    array_map('strtolower', $newShow->airing_days ?? []),
                    array_map('strtolower', $s->show->airing_days ?? [])
                );
                if (empty($sharedDays)) return false;

                return $s->start_time < $newEnd && $s->end_time > $newStart;
            });
    }

    // ── Delete ────────────────────────────────────────────────
    public function confirmDelete(int $id): void
    {
        $s = Schedule::with('show')->findOrFail($id);

        $this->deletingId        = $s->id;
        $this->deletingLabel     = $s->show->title . ' — ' . $s->time_label;
        $this->deleteUnlockAt    = now()->addSeconds(10)->toIso8601String();
        $this->deleteConfirmText = '';
        $this->showDeleteModal   = true;

        $this->dispatch('delete-modal-opened', name: $this->deletingLabel);
    }

    public function delete(): void
    {
        if (! $this->deletingId) return;

        $timeExpired = $this->deleteUnlockAt && now()->gte(Carbon::parse($this->deleteUnlockAt));
        $nameMatches = $this->deleteConfirmText === $this->deletingLabel;

        if (! $timeExpired && ! $nameMatches) {
            $this->addError('deleteConfirmText', 'Wait for the timer or type the exact label.');
            return;
        }

        Schedule::find($this->deletingId)?->delete();

        $this->showDeleteModal = false;
        $this->deletingId      = null;
        Flux::toast('Slot deleted.', variant: 'success');
    }

    // ── Helpers ───────────────────────────────────────────────
    private function daysLabel(array $days): string
    {
        $days    = array_map('strtolower', $days);
        $ordered = array_values(array_intersect(self::ALL_DAYS, $days));

        if (count($ordered) === 0) return '—';
        if (count($ordered) === 7) return 'Daily';
        if ($ordered === ['monday','tuesday','wednesday','thursday','friday']) return 'Mon–Fri';
        if ($ordered === ['saturday','sunday']) return 'Sat–Sun';

        return implode(', ', array_map(fn ($d) => ucfirst(substr($d, 0, 3)), $ordered));
    }

    public function with(): array
    {
        $schedules = Schedule::with(['show:id,title,airing_days,category', 'presenters:id,name,role'])
            ->when($this->showFilter,      fn ($q) => $q->where('show_id', (int) $this->showFilter))
            ->when($this->presenterFilter, fn ($q) =>
                $q->whereHas('presenters',
                    fn ($q) => $q->where('presenters.id', (int) $this->presenterFilter))
            )
            ->orderBy('start_time')
            ->paginate(25)
            ->through(function (Schedule $s) {
                $s->days_label    = $this->daysLabel($s->show->airing_days ?? []);
                $s->is_live_now   = $s->is_live_now;
                return $s;
            });

        return [
            'schedules'   => $schedules,
            'totalCount'  => Schedule::count(),
            'liveCount'   => Schedule::query()
                ->where('start_time', '<=', now()->format('H:i:s'))
                ->where('end_time',   '>=', now()->format('H:i:s'))
                ->whereHas('show', fn ($q) => $q->where('is_active', true)
                    ->whereJsonContains('airing_days', strtolower(now()->englishDayOfWeek)))
                ->count(),
        ];
    }
};

?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Schedules</flux:heading>
            <flux:subheading>Weekly time slots across all shows.</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">
            Add Slot
        </flux:button>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Total Slots</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format($totalCount) }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Currently Live</p>
            <p @class([
                'mt-1 text-2xl font-semibold',
                'text-emerald-600 dark:text-emerald-400' => $liveCount > 0,
                'text-zinc-400' => $liveCount === 0,
            ])>{{ $liveCount }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Distinct Shows</p>
            <p class="mt-1 text-2xl font-semibold">{{ count($availableShows) }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-3">
        <flux:select wire:model.live="showFilter" class="max-w-[220px]">
            <flux:select.option value="">All shows</flux:select.option>
            @foreach($availableShows as $show)
                <flux:select.option value="{{ $show['id'] }}">{{ $show['title'] }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="presenterFilter" class="max-w-[220px]">
            <flux:select.option value="">All presenters</flux:select.option>
            @foreach($availablePresenters as $p)
                <flux:select.option value="{{ $p['id'] }}">{{ $p['name'] }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    {{-- List --}}
    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        @forelse($schedules as $slot)
            <div wire:key="slot-{{ $slot->id }}"
                 @class([
                     'flex items-start gap-4 border-b border-zinc-200 px-4 py-3 last:border-b-0 dark:border-zinc-700',
                     'bg-emerald-50/50 dark:bg-emerald-950/20' => $slot->is_live_now,
                     'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' => ! $slot->is_live_now,
                 ])>

                {{-- Time block --}}
                <div class="w-24 shrink-0">
                    <p class="text-sm font-semibold">
                        {{ \Illuminate\Support\Carbon::parse($slot->start_time)->format('H:i') }}
                    </p>
                    <p class="text-xs text-zinc-500">
                        → {{ \Illuminate\Support\Carbon::parse($slot->end_time)->format('H:i') }}
                    </p>
                    @if($slot->is_live_now)
                        <span class="mt-1 inline-flex items-center gap-1 text-[10px] font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-400">
                            <span class="relative flex h-1.5 w-1.5">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            </span>
                            Live now
                        </span>
                    @endif
                </div>

                {{-- Show info --}}
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="truncate text-sm font-semibold">{{ $slot->show->title }}</p>

                        @if($slot->show->category)
                            <flux:badge size="sm" color="zinc">{{ ucfirst($slot->show->category) }}</flux:badge>
                        @endif

                        @if($slot->label)
                            <flux:badge size="sm" color="indigo">{{ $slot->label }}</flux:badge>
                        @endif

                        <span class="text-xs text-zinc-400">·</span>
                        <span class="text-xs text-zinc-500 whitespace-nowrap">{{ $slot->days_label }}</span>
                    </div>

                    {{-- Presenters --}}
                    @if($slot->presenters->isNotEmpty())
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            @foreach($slot->presenters as $p)
                                <span class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2 py-0.5 text-xs dark:bg-zinc-800">
                                    <span class="font-medium">{{ $p->name }}</span>
                                    @if($p->pivot->role)
                                        <span class="text-zinc-500">· {{ $p->pivot->role }}</span>
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-1 text-xs text-zinc-400 italic">No presenters assigned</p>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="flex shrink-0 items-center gap-1">
                    <flux:button size="sm" variant="ghost" icon="pencil"
                                 wire:click="openEdit({{ $slot->id }})" title="Edit slot" />
                    <flux:button size="sm" variant="ghost" icon="trash"
                                 wire:click="confirmDelete({{ $slot->id }})" title="Delete slot" />
                </div>
            </div>
        @empty
            <div class="p-12 text-center">
                <flux:icon name="calendar" class="mx-auto h-10 w-10 text-zinc-400" />
                <p class="mt-2 text-sm text-zinc-500">
                    {{ $showFilter || $presenterFilter ? 'No slots match your filters.' : 'No slots yet.' }}
                </p>
                <flux:button class="mt-4" icon="plus" wire:click="openCreate">Add your first slot</flux:button>
            </div>
        @endforelse
    </div>

    <div>{{ $schedules->links() }}</div>

    {{-- Create / Edit modal --}}
    <flux:modal wire:model="showModal" class="md:w-[720px]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit Slot' : 'New Slot' }}</flux:heading>
                <flux:subheading>When does this show air, and who's hosting?</flux:subheading>
            </div>

            {{-- Show + Label --}}
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <flux:select wire:model.live="show_id" label="Show" required>
                        <flux:select.option value="">— pick a show —</flux:select.option>
                        @foreach($availableShows as $show)
                            <flux:select.option value="{{ $show['id'] }}">
                                {{ $show['title'] }} ({{ $show['duration_minutes'] }}m)
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('show_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <flux:input wire:model="label" label="Label (optional)" placeholder="Prime Time" />
            </div>

            {{-- Days note --}}
            @php
                $selectedShow = collect($availableShows)->firstWhere('id', (int) $show_id);
            @endphp
            @if($selectedShow)
                <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950/30 dark:text-indigo-300">
                    Airing days: <strong>{{ $this->daysLabel($selectedShow['airing_days'] ?? []) }}</strong>
                    — inherited from the show. Edit the show to change these.
                </div>
            @endif

            {{-- Times --}}
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <flux:input wire:model.live="start_time" type="time" label="Start Time" required />
                    @error('start_time') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <flux:input wire:model="end_time" type="time" label="End Time" required />
                    @error('end_time') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    <p class="mt-1 text-xs text-zinc-500">
                        Auto-computed from show duration. Edit to override.
                    </p>
                </div>
            </div>

            {{-- Presenters repeater --}}
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Presenters</label>
                    <flux:button type="button" size="sm" icon="plus" wire:click="addPresenterRow">
                        Add Presenter
                    </flux:button>
                </div>

                @if(empty($slotPresenters))
                    <div class="rounded-md border border-dashed border-zinc-300 px-3 py-4 text-center text-xs text-zinc-500 dark:border-zinc-700">
                        No presenters yet. Click <strong>Add Presenter</strong> above.
                    </div>
                @else
                    <div class="space-y-2">
                        @foreach($slotPresenters as $i => $row)
                            <div class="flex items-start gap-2" wire:key="pres-row-{{ $i }}">
                                <div class="flex-1">
                                    <select
                                        wire:model="slotPresenters.{{ $i }}.presenter_id"
                                        class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800"
                                    >
                                        <option value="">— pick a presenter —</option>
                                        @foreach($availablePresenters as $p)
                                            <option value="{{ $p['id'] }}">{{ $p['name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="w-40">
                                    <input
                                        type="text"
                                        wire:model="slotPresenters.{{ $i }}.role"
                                        placeholder="Role (DJ, Host…)"
                                        class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800"
                                    />
                                </div>
                                <flux:button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="removePresenterRow({{ $i }})"
                                    title="Remove"
                                />
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $editingId ? 'Save Changes' : 'Create Slot' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete modal --}}
    <flux:modal wire:model="showDeleteModal" class="md:w-[480px]">
        <div
            class="space-y-4"
            x-data="{
                opened: false,
                remaining: 10,
                expectedName: '',
                typedName: '',
                interval: null,
                start() {
                    this.opened = true;
                    this.remaining = 10;
                    this.typedName = '';
                    clearInterval(this.interval);
                    this.interval = setInterval(() => {
                        if (this.remaining > 0) this.remaining--;
                        else clearInterval(this.interval);
                    }, 1000);
                },
                get canDelete() {
                    return this.opened && (this.remaining === 0 || this.typedName === this.expectedName);
                }
            }"
            x-on:delete-modal-opened.window="
                expectedName = $event.detail.name;
                start();
            "
        >
            <div>
                <flux:heading size="lg">Delete slot?</flux:heading>
                <flux:subheading>
                    Removing <strong>{{ $deletingLabel }}</strong> cannot be undone.
                    Presenters will be unlinked; comments will lose their slot association.
                </flux:subheading>
            </div>

            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950/30">
                <p x-show="remaining > 0" x-cloak>
                    Please wait <strong><span x-text="remaining"></span>s</strong> before you can delete — the button unlocks automatically.
                </p>
                <p x-show="remaining <= 0" x-cloak class="font-medium text-emerald-700 dark:text-emerald-400">
                    Timer expired — you may delete now.
                </p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    Or type the label to confirm immediately
                </label>
                <input
                    type="text"
                    wire:model.live="deleteConfirmText"
                    x-on:input="typedName = $event.target.value"
                    placeholder="{{ $deletingLabel }}"
                    autocomplete="off"
                    class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 font-mono text-sm dark:border-zinc-600 dark:bg-zinc-800"
                />
                @error('deleteConfirmText')
                    <span class="text-xs text-red-600">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
                <flux:button
                    variant="danger"
                    wire:click="delete"
                    x-bind:disabled="!canDelete"
                    x-bind:class="!canDelete ? 'opacity-50 cursor-not-allowed' : ''"
                >
                    Delete
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
