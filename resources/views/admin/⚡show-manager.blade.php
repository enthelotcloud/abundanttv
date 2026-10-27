<?php

use App\Models\Show;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads, WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'cat', history: true)]
    public string $categoryFilter = '';

    #[Url(as: 'active', history: true)]
    public string $activeFilter = '';

    public const ALL_DAYS = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];

    // ── Create / Edit ─────────────────────────────────────────
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $title            = '';
    public string $category         = '';
    public string $description      = '';
    public int    $duration_minutes = 60;
    public array  $airing_days      = [];
    public bool   $is_active        = true;

    public $thumbnail = null;
    public ?string $existingThumbnail = null;

    public array $knownCategories = [];

    // ── Delete ────────────────────────────────────────────────
    public bool    $showDeleteModal        = false;
    public ?int    $deletingId             = null;
    public ?string $deletingTitle          = '';
    public int     $deletingSchedulesCount = 0;
    public ?string $deleteUnlockAt         = null;
    public string  $deleteConfirmText      = '';

    public function mount(): void
    {
        $this->loadCategories();
    }

    public function loadCategories(): void
    {
        $this->knownCategories = Show::whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->filter()
            ->values()
            ->toArray();
    }

    public function updatedSearch(): void         { $this->resetPage(); }
    public function updatedCategoryFilter(): void { $this->resetPage(); }
    public function updatedActiveFilter(): void   { $this->resetPage(); }

    // ── Open form ─────────────────────────────────────────────
    public function openCreate(): void
    {
        $this->reset(['editingId', 'title', 'category', 'description', 'thumbnail', 'existingThumbnail']);
        $this->duration_minutes = 60;
        $this->airing_days      = [];
        $this->is_active        = true;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $s = Show::findOrFail($id);

        $this->editingId         = $s->id;
        $this->title             = $s->title;
        $this->category          = $s->category ?? '';
        $this->description       = $s->description ?? '';
        $this->duration_minutes  = $s->duration_minutes;
        $this->airing_days       = $s->airing_days ?? [];
        $this->is_active         = (bool) $s->is_active;
        $this->existingThumbnail = $s->thumbnail;
        $this->thumbnail         = null;

        $this->resetValidation();
        $this->showModal = true;
    }

    // ── Shortcuts ────────────────────────────────────────────
    public function selectAllDays(): void   { $this->airing_days = self::ALL_DAYS; }
    public function selectWeekdays(): void  { $this->airing_days = ['monday','tuesday','wednesday','thursday','friday']; }
    public function selectWeekends(): void  { $this->airing_days = ['saturday','sunday']; }
    public function clearDays(): void       { $this->airing_days = []; }

    public function setDuration(int $minutes): void { $this->duration_minutes = $minutes; }

    // ── Save ──────────────────────────────────────────────────
    public function save(): void
    {
        $this->validate([
            'title'            => ['required', 'string', 'max:150'],
            'category'         => ['nullable', 'string', 'max:50'],
            'description'      => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:720'],
            'airing_days'      => ['required', 'array', 'min:1'],
            'airing_days.*'    => [Rule::in(self::ALL_DAYS)],
            'is_active'        => ['boolean'],
            'thumbnail'        => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'airing_days.required' => 'Pick at least one airing day.',
        ]);

        $data = [
            'title'            => $this->title,
            'category'         => $this->category ?: null,
            'description'      => $this->description ?: null,
            'duration_minutes' => $this->duration_minutes,
            'airing_days'      => array_values($this->airing_days),
            'is_active'        => $this->is_active,
        ];

        if ($this->thumbnail) {
            if ($this->existingThumbnail) {
                Storage::disk('public')->delete($this->existingThumbnail);
            }
            $data['thumbnail'] = $this->thumbnail->store('shows', 'public');
        }

        if ($this->editingId) {
            Show::findOrFail($this->editingId)->update($data);
            Flux::toast('Show updated.', variant: 'success');
        } else {
            Show::create($data);
            Flux::toast('Show created.', variant: 'success');
        }

        $this->showModal = false;
        $this->loadCategories();
    }

    public function toggleActive(int $id): void
    {
        $s = Show::findOrFail($id);
        $s->update(['is_active' => ! $s->is_active]);
    }

    // ── Delete ────────────────────────────────────────────────
    public function confirmDelete(int $id): void
    {
        $s = Show::withCount('schedules')->findOrFail($id);

        $this->deletingId             = $s->id;
        $this->deletingTitle          = $s->title;
        $this->deletingSchedulesCount = $s->schedules_count;
        $this->deleteUnlockAt         = now()->addSeconds(10)->toIso8601String();
        $this->deleteConfirmText      = '';
        $this->showDeleteModal        = true;

        $this->dispatch('delete-modal-opened', name: $s->title);
    }

    public function delete(): void
    {
        if (! $this->deletingId) return;

        $timeExpired = $this->deleteUnlockAt && now()->gte(Carbon::parse($this->deleteUnlockAt));
        $nameMatches = $this->deleteConfirmText === $this->deletingTitle;

        if (! $timeExpired && ! $nameMatches) {
            $this->addError('deleteConfirmText', 'Wait for the timer or type the exact title.');
            return;
        }

        $s = Show::find($this->deletingId);
        if (! $s) { $this->showDeleteModal = false; return; }

        if ($s->thumbnail) {
            Storage::disk('public')->delete($s->thumbnail);
        }
        $s->delete();

        $this->showDeleteModal = false;
        $this->deletingId      = null;
        Flux::toast('Show deleted.', variant: 'success');
    }

    // ── Helper ────────────────────────────────────────────────
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
        $shows = Show::query()
            ->withCount('schedules')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%");
            }))
            ->when($this->categoryFilter, fn ($q) => $q->where('category', $this->categoryFilter))
            ->when($this->activeFilter === 'active',   fn ($q) => $q->where('is_active', true))
            ->when($this->activeFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('title')
            ->paginate(20)
            ->through(function (Show $show) {
                $show->days_label = $this->daysLabel($show->airing_days ?? []);
                return $show;
            });

        return [
            'shows'       => $shows,
            'totalCount'  => Show::count(),
            'activeCount' => Show::where('is_active', true)->count(),
        ];
    }
};

?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Shows</flux:heading>
            <flux:subheading>Programs and their airing days.</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">
            Add Show
        </flux:button>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Total Shows</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format($totalCount) }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Active</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-600 dark:text-emerald-400">{{ number_format($activeCount) }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Inactive</p>
            <p class="mt-1 text-2xl font-semibold text-zinc-400">{{ number_format($totalCount - $activeCount) }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search title or description…"
            icon="magnifying-glass"
            clearable
            class="max-w-md flex-1"
        />
        <flux:select wire:model.live="categoryFilter" class="max-w-[180px]">
            <flux:select.option value="">All categories</flux:select.option>
            @foreach($knownCategories as $cat)
                <flux:select.option value="{{ $cat }}">{{ ucfirst($cat) }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="activeFilter" class="max-w-[160px]">
            <flux:select.option value="">All statuses</flux:select.option>
            <flux:select.option value="active">Active only</flux:select.option>
            <flux:select.option value="inactive">Inactive only</flux:select.option>
        </flux:select>
    </div>

    {{-- List --}}
    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        @forelse($shows as $show)
            <div wire:key="show-{{ $show->id }}"
                 class="flex items-start gap-4 border-b border-zinc-200 px-4 py-3 last:border-b-0 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50">

                {{-- Thumbnail --}}
                <div class="shrink-0">
                    @if($show->thumbnail)
                        <img src="{{ asset('storage/'.$show->thumbnail) }}"
                             alt="{{ $show->title }}"
                             class="h-12 w-20 rounded-md object-cover" />
                    @else
                        <div class="flex h-12 w-20 items-center justify-center rounded-md bg-zinc-100 dark:bg-zinc-800">
                            <flux:icon name="tv" class="h-5 w-5 text-zinc-400" />
                        </div>
                    @endif
                </div>

                {{-- Info --}}
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p @class([
                            'truncate text-sm',
                            'font-semibold' => $show->is_active,
                            'font-medium text-zinc-500 line-through' => ! $show->is_active,
                        ])>{{ $show->title }}</p>

                        @if($show->category)
                            <flux:badge size="sm" color="zinc">{{ ucfirst($show->category) }}</flux:badge>
                        @endif
                        @if(! $show->is_active)
                            <flux:badge size="sm" color="amber">Inactive</flux:badge>
                        @endif
                    </div>

                    <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-zinc-500">
                        <span>{{ $show->duration_label }}</span>
                        <span>·</span>
                        <span>{{ $show->days_label }}</span>
                        <span>·</span>
                        <span>{{ $show->schedules_count }} slot{{ $show->schedules_count === 1 ? '' : 's' }}</span>
                    </div>

                    @if($show->description)
                        <p class="mt-1 line-clamp-1 text-xs text-zinc-500">{{ $show->description }}</p>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="flex shrink-0 items-center gap-1">
                    <flux:button
                        size="sm" variant="ghost"
                        :icon="$show->is_active ? 'eye-slash' : 'eye'"
                        wire:click="toggleActive({{ $show->id }})"
                        :title="$show->is_active ? 'Deactivate' : 'Activate'"
                    />
                    <flux:button size="sm" variant="ghost" icon="pencil"
                                 wire:click="openEdit({{ $show->id }})" title="Edit" />
                    <flux:button size="sm" variant="ghost" icon="trash"
                                 wire:click="confirmDelete({{ $show->id }})" title="Delete" />
                </div>
            </div>
        @empty
            <div class="p-12 text-center">
                <flux:icon name="tv" class="mx-auto h-10 w-10 text-zinc-400" />
                <p class="mt-2 text-sm text-zinc-500">
                    {{ $search || $categoryFilter || $activeFilter ? 'No shows match your filters.' : 'No shows yet.' }}
                </p>
                <flux:button class="mt-4" icon="plus" wire:click="openCreate">Add your first show</flux:button>
            </div>
        @endforelse
    </div>

    <div>{{ $shows->links() }}</div>

    {{-- Category suggestions --}}
    <datalist id="show-categories">
        @foreach($knownCategories as $cat)
            <option value="{{ $cat }}"></option>
        @endforeach
    </datalist>

    {{-- Create / Edit modal --}}
    <flux:modal wire:model="showModal" class="md:w-[720px]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit Show' : 'New Show' }}</flux:heading>
                <flux:subheading>Program details, airing schedule, and thumbnail.</flux:subheading>
            </div>

            {{-- Thumbnail --}}
            <div class="flex items-start gap-4">
                <div class="shrink-0">
                    @if($thumbnail)
                        <img src="{{ $thumbnail->temporaryUrl() }}" class="h-20 w-32 rounded-md object-cover" />
                    @elseif($existingThumbnail)
                        <img src="{{ asset('storage/'.$existingThumbnail) }}" class="h-20 w-32 rounded-md object-cover" />
                    @else
                        <div class="flex h-20 w-32 items-center justify-center rounded-md bg-zinc-100 dark:bg-zinc-800">
                            <flux:icon name="photo" class="h-8 w-8 text-zinc-400" />
                        </div>
                    @endif
                </div>
                <div class="flex-1">
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-zinc-300 bg-white px-3 py-1.5 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                        <flux:icon name="arrow-up-tray" class="h-4 w-4" />
                        {{ $thumbnail ? 'Change image' : ($existingThumbnail ? 'Replace image' : 'Choose thumbnail') }}
                        <input type="file" wire:model="thumbnail" accept=".jpg,.jpeg,.png,.webp" class="hidden" />
                    </label>
                    <p class="mt-1 text-xs text-zinc-500">JPG, PNG, or WebP. Max 2 MB. Recommended 16:9.</p>
                    @error('thumbnail') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    <div wire:loading wire:target="thumbnail" class="mt-1 text-xs text-zinc-500">Uploading…</div>
                </div>
            </div>

            {{-- Title + Category --}}
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model="title" label="Title" placeholder="Sunday Service" required />
                <flux:input wire:model="category" label="Category (optional)" placeholder="news, music, worship…" list="show-categories" />
            </div>

            {{-- Duration --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Duration (minutes)</label>
                <div class="flex flex-wrap items-center gap-2">
                    <input
                        type="number"
                        wire:model="duration_minutes"
                        min="5"
                        max="720"
                        class="w-24 rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800"
                    />
                    <span class="text-sm text-zinc-500">min</span>
                    <span class="ml-2 text-xs text-zinc-400">Quick set:</span>
                    @foreach([15, 30, 45, 60, 90, 120] as $preset)
                        <button
                            type="button"
                            wire:click="setDuration({{ $preset }})"
                            @class([
                                'rounded-md border px-2.5 py-1 text-xs font-medium transition',
                                'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' => $duration_minutes === $preset,
                                'border-zinc-300 hover:bg-zinc-50 dark:border-zinc-600 dark:hover:bg-zinc-700' => $duration_minutes !== $preset,
                            ])
                        >
                            {{ $preset >= 60 ? intdiv($preset, 60) . 'h' . ($preset % 60 ? ' ' . $preset % 60 . 'm' : '') : $preset . 'm' }}
                        </button>
                    @endforeach
                </div>
                @error('duration_minutes') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>

            {{-- Airing days --}}
            <div>
                <div class="mb-1 flex items-center justify-between">
                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Airing days</label>
                    <div class="flex items-center gap-2 text-xs">
                        <button type="button" wire:click="selectAllDays"  class="text-indigo-600 hover:underline dark:text-indigo-400">All</button>
                        <span class="text-zinc-300">|</span>
                        <button type="button" wire:click="selectWeekdays" class="text-indigo-600 hover:underline dark:text-indigo-400">Weekdays</button>
                        <span class="text-zinc-300">|</span>
                        <button type="button" wire:click="selectWeekends" class="text-indigo-600 hover:underline dark:text-indigo-400">Weekends</button>
                        <span class="text-zinc-300">|</span>
                        <button type="button" wire:click="clearDays" class="text-zinc-500 hover:underline">Clear</button>
                    </div>
                </div>

                @php
                    $dayLabels = [
                        'monday' => 'Mon', 'tuesday' => 'Tue', 'wednesday' => 'Wed',
                        'thursday' => 'Thu', 'friday' => 'Fri', 'saturday' => 'Sat', 'sunday' => 'Sun',
                    ];
                @endphp

                <div class="flex flex-wrap gap-2">
                    @foreach($dayLabels as $key => $label)
                        <label @class([
                            'cursor-pointer select-none rounded-md border px-3 py-1.5 text-sm font-medium transition',
                            'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' => in_array($key, $airing_days),
                            'border-zinc-300 bg-white text-zinc-700 hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' => ! in_array($key, $airing_days),
                        ])>
                            <input type="checkbox" wire:model.live="airing_days" value="{{ $key }}" class="sr-only" />
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('airing_days') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>

            {{-- Description --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Description (optional)</label>
                <textarea wire:model="description" rows="3" placeholder="Short summary of the show…"
                          class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800"></textarea>
                @error('description') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>

            {{-- Active --}}
            <div class="flex items-center gap-2">
                <flux:switch wire:model.live="is_active" />
                <span class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $is_active ? 'Active — schedulable and visible on public pages' : 'Inactive — hidden, cannot be added to schedules' }}
                </span>
            </div>

            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $editingId ? 'Save Changes' : 'Create Show' }}
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
                <flux:heading size="lg">Delete show?</flux:heading>
                <flux:subheading>
                    Removing <strong>{{ $deletingTitle }}</strong> cannot be undone.
                    @if($deletingSchedulesCount > 0)
                        This will also delete <strong>{{ $deletingSchedulesCount }} schedule slot{{ $deletingSchedulesCount === 1 ? '' : 's' }}</strong>.
                        Comments will be preserved but will lose their show association.
                    @endif
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
                    Or type <strong>{{ $deletingTitle }}</strong> to confirm immediately
                </label>
                <input
                    type="text"
                    wire:model.live="deleteConfirmText"
                    x-on:input="typedName = $event.target.value"
                    placeholder="{{ $deletingTitle }}"
                    autocomplete="off"
                    class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800"
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
