<?php

use App\Models\Presenter;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    // ── Create / Edit ─────────────────────────────────────────
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name          = '';
    public string $role          = '';
    public string $phone         = '';
    public string $email         = '';
    public string $bio           = '';
    public bool   $phone_visible = false;
    public bool   $email_visible = false;
    public bool   $is_active     = true;

    public $photo = null;
    public ?string $existingPhoto = null;

    // ── Delete ────────────────────────────────────────────────
    public bool    $showDeleteModal   = false;
    public ?int    $deletingId        = null;
    public ?string $deletingName      = '';
    public ?string $deleteUnlockAt    = null;
    public string  $deleteConfirmText = '';

    public function openCreate(): void
    {
        $this->reset(['editingId', 'name', 'role', 'phone', 'email', 'bio', 'photo', 'existingPhoto']);
        $this->phone_visible = false;
        $this->email_visible = false;
        $this->is_active     = true;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $p = Presenter::findOrFail($id);

        $this->editingId     = $p->id;
        $this->name          = $p->name;
        $this->role          = $p->role ?? '';
        $this->phone         = $p->phone ?? '';
        $this->email         = $p->email ?? '';
        $this->bio           = $p->bio ?? '';
        $this->phone_visible = (bool) $p->phone_visible;
        $this->email_visible = (bool) $p->email_visible;
        $this->is_active     = (bool) $p->is_active;
        $this->existingPhoto = $p->photo;
        $this->photo         = null;

        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'name'          => ['required', 'string', 'max:100'],
            'role'          => ['nullable', 'string', 'max:50'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'email'         => ['nullable', 'email', 'max:255'],
            'bio'           => ['nullable', 'string', 'max:1000'],
            'phone_visible' => ['boolean'],
            'email_visible' => ['boolean'],
            'is_active'     => ['boolean'],
            'photo'         => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $data = [
            'name'          => $this->name,
            'role'          => $this->role ?: null,
            'phone'         => $this->phone ?: null,
            'email'         => $this->email ?: null,
            'bio'           => $this->bio ?: null,
            'phone_visible' => $this->phone_visible,
            'email_visible' => $this->email_visible,
            'is_active'     => $this->is_active,
        ];

        if ($this->photo) {
            if ($this->existingPhoto) {
                Storage::disk('public')->delete($this->existingPhoto);
            }
            $data['photo'] = $this->photo->store('presenters', 'public');
        }

        if ($this->editingId) {
            Presenter::findOrFail($this->editingId)->update($data);
            Flux::toast('Presenter updated.', variant: 'success');
        } else {
            Presenter::create($data);
            Flux::toast('Presenter created.', variant: 'success');
        }

        $this->showModal = false;
    }

    public function toggleActive(int $id): void
    {
        $p = Presenter::findOrFail($id);
        $p->update(['is_active' => ! $p->is_active]);
    }

    public function confirmDelete(int $id): void
    {
        $p = Presenter::findOrFail($id);

        $this->deletingId        = $p->id;
        $this->deletingName      = $p->name;
        $this->deleteUnlockAt    = now()->addSeconds(10)->toIso8601String();
        $this->deleteConfirmText = '';
        $this->showDeleteModal   = true;

        $this->dispatch('delete-modal-opened', name: $p->name);
    }

    public function delete(): void
    {
        if (! $this->deletingId) return;

        $timeExpired = $this->deleteUnlockAt && now()->gte(Carbon::parse($this->deleteUnlockAt));
        $nameMatches = $this->deleteConfirmText === $this->deletingName;

        if (! $timeExpired && ! $nameMatches) {
            $this->addError('deleteConfirmText', 'Wait for the timer or type the exact name.');
            return;
        }

        $p = Presenter::find($this->deletingId);
        if (! $p) { $this->showDeleteModal = false; return; }

        if ($p->photo) {
            Storage::disk('public')->delete($p->photo);
        }
        $p->delete();

        $this->showDeleteModal = false;
        $this->deletingId      = null;
        Flux::toast('Presenter deleted.', variant: 'success');
    }

    public function with(): array
    {
        $presenters = Presenter::withCount('schedules')->orderBy('name')->get();

        // Shows count per presenter (unique shows across their slots)
        $showCounts = DB::table('schedule_presenter')
            ->join('schedules', 'schedules.id', '=', 'schedule_presenter.schedule_id')
            ->select('schedule_presenter.presenter_id', DB::raw('count(distinct schedules.show_id) as shows_count'))
            ->groupBy('schedule_presenter.presenter_id')
            ->pluck('shows_count', 'presenter_id');

        foreach ($presenters as $p) {
            $p->shows_count = (int) ($showCounts[$p->id] ?? 0);
        }

        return ['presenters' => $presenters];
    }
};

?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Presenters</flux:heading>
            <flux:subheading>People who host your shows.</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">
            Add Presenter
        </flux:button>
    </div>

    {{-- List --}}
    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        @forelse($presenters as $p)
            @php
                $initials = collect(explode(' ', trim($p->name)))
                    ->map(fn ($w) => mb_substr($w, 0, 1))
                    ->take(2)
                    ->join('');
            @endphp
            <div wire:key="pr-{{ $p->id }}"
                 class="flex items-start gap-4 border-b border-zinc-200 px-4 py-3 last:border-b-0 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50">

                {{-- Photo --}}
                <div class="shrink-0">
                    @if($p->photo)
                        <img src="{{ asset('storage/'.$p->photo) }}"
                             alt="{{ $p->name }}"
                             class="h-12 w-12 rounded-full object-cover" />
                    @else
                        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-zinc-200 text-sm font-semibold text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">
                            {{ strtoupper($initials) }}
                        </div>
                    @endif
                </div>

                {{-- Info --}}
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p @class([
                            'truncate text-sm',
                            'font-semibold' => $p->is_active,
                            'font-medium text-zinc-500 line-through' => ! $p->is_active,
                        ])>{{ $p->name }}</p>

                        @if($p->role)
                            <flux:badge size="sm" color="zinc">{{ $p->role }}</flux:badge>
                        @endif
                        @if(! $p->is_active)
                            <flux:badge size="sm" color="amber">Inactive</flux:badge>
                        @endif
                    </div>

                    <div class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-zinc-500">
                        @if($p->phone && $p->phone_visible)
                            <span>{{ $p->phone }}</span>
                        @endif
                        @if($p->email && $p->email_visible)
                            <span class="truncate">{{ $p->email }}</span>
                        @endif
                        <span>{{ $p->schedules_count }} slot{{ $p->schedules_count === 1 ? '' : 's' }}</span>
                        @if($p->shows_count > 0)
                            <span>· {{ $p->shows_count }} show{{ $p->shows_count === 1 ? '' : 's' }}</span>
                        @endif
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex shrink-0 items-center gap-1">
                    <flux:button
                        size="sm" variant="ghost"
                        :icon="$p->is_active ? 'eye-slash' : 'eye'"
                        wire:click="toggleActive({{ $p->id }})"
                        :title="$p->is_active ? 'Deactivate' : 'Activate'"
                    />
                    <flux:button size="sm" variant="ghost" icon="pencil"
                                 wire:click="openEdit({{ $p->id }})" title="Edit" />
                    <flux:button size="sm" variant="ghost" icon="trash"
                                 wire:click="confirmDelete({{ $p->id }})" title="Delete" />
                </div>
            </div>
        @empty
            <div class="p-12 text-center">
                <flux:icon name="users" class="mx-auto h-10 w-10 text-zinc-400" />
                <p class="mt-2 text-sm text-zinc-500">No presenters yet.</p>
                <flux:button class="mt-4" icon="plus" wire:click="openCreate">Add your first presenter</flux:button>
            </div>
        @endforelse
    </div>

    {{-- Create / Edit modal --}}
    <flux:modal wire:model="showModal" class="md:w-[640px]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit Presenter' : 'New Presenter' }}</flux:heading>
                <flux:subheading>Photo, contact details, and public visibility.</flux:subheading>
            </div>

            {{-- Photo --}}
            <div class="flex items-center gap-4">
                <div class="shrink-0">
                    @if($photo)
                        <img src="{{ $photo->temporaryUrl() }}" class="h-20 w-20 rounded-full object-cover" />
                    @elseif($existingPhoto)
                        <img src="{{ asset('storage/'.$existingPhoto) }}" class="h-20 w-20 rounded-full object-cover" />
                    @else
                        <div class="flex h-20 w-20 items-center justify-center rounded-full bg-zinc-200 dark:bg-zinc-700">
                            <flux:icon name="user" class="h-8 w-8 text-zinc-400" />
                        </div>
                    @endif
                </div>
                <div class="flex-1">
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-zinc-300 bg-white px-3 py-1.5 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                        <flux:icon name="arrow-up-tray" class="h-4 w-4" />
                        {{ $photo ? 'Change photo' : ($existingPhoto ? 'Replace photo' : 'Choose photo') }}
                        <input type="file" wire:model="photo" accept=".jpg,.jpeg,.png,.webp" class="hidden" />
                    </label>
                    <p class="mt-1 text-xs text-zinc-500">JPG, PNG, or WebP. Max 2 MB.</p>
                    @error('photo') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    <div wire:loading wire:target="photo" class="mt-1 text-xs text-zinc-500">Uploading…</div>
                </div>
            </div>

            {{-- Name + Role --}}
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model="name" label="Name" placeholder="Bishop Gitaari" required />
                <flux:input wire:model="role" label="Role (optional)" placeholder="Minister, DJ, Host" />
            </div>

            {{-- Phone + Email with visibility --}}
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="space-y-1">
                    <flux:input wire:model="phone" label="Phone" placeholder="+254…" />
                    <label class="flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400">
                        <flux:switch wire:model="phone_visible" />
                        Show phone on public site
                    </label>
                </div>
                <div class="space-y-1">
                    <flux:input wire:model="email" type="email" label="Email" placeholder="name@example.com" />
                    <label class="flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400">
                        <flux:switch wire:model="email_visible" />
                        Show email on public site
                    </label>
                </div>
            </div>

            {{-- Bio --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    Bio (optional)
                </label>
                <textarea wire:model="bio" rows="4" placeholder="Short background…"
                          class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800"></textarea>
                @error('bio') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </div>

            {{-- Active --}}
            <div class="flex items-center gap-2">
                <flux:switch wire:model.live="is_active" />
                <span class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $is_active ? 'Active — visible on public pages' : 'Inactive — hidden from public pages' }}
                </span>
            </div>

            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $editingId ? 'Save Changes' : 'Create Presenter' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete modal (countdown + name confirm) --}}
    <flux:modal wire:model="showDeleteModal" class="md:w-[460px]">
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
                <flux:heading size="lg">Delete presenter?</flux:heading>
                <flux:subheading>
                    Removing <strong>{{ $deletingName }}</strong> also unlinks them from any schedules.
                    This cannot be undone.
                </flux:subheading>
            </div>

            {{-- Countdown banner --}}
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950/30">
                <p x-show="remaining > 0" x-cloak>
                    Please wait <strong><span x-text="remaining"></span>s</strong> before you can delete — the button unlocks automatically.
                </p>
                <p x-show="remaining <= 0" x-cloak class="font-medium text-emerald-700 dark:text-emerald-400">
                    Timer expired — you may delete now.
                </p>
            </div>

            {{-- Name confirmation --}}
            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    Or type <strong>{{ $deletingName }}</strong> to confirm immediately
                </label>
                <input
                    type="text"
                    wire:model.live="deleteConfirmText"
                    x-on:input="typedName = $event.target.value"
                    placeholder="{{ $deletingName }}"
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
