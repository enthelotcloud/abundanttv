<?php

use App\Models\Bannedword;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    public bool   $showModal    = false;
    public string $newWords     = '';
    public bool   $bulkActive   = true;

    public bool $showDeleteModal = false;
    public ?int $deletingId      = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset('newWords');
        $this->bulkActive = true;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate(['newWords' => ['required', 'string']]);

        $words = collect(preg_split('/[,\r\n]+/', $this->newWords))
            ->map(fn ($w) => trim(strtolower($w)))
            ->filter()
            ->unique()
            ->values();

        if ($words->isEmpty()) {
            $this->addError('newWords', 'Enter at least one word.');
            return;
        }

        $created = 0;
        $skipped = 0;

        foreach ($words as $word) {
            if (Bannedword::where('word', $word)->exists()) {
                $skipped++;
                continue;
            }
            Bannedword::create(['word' => $word, 'is_active' => $this->bulkActive]);
            $created++;
        }

        $this->showModal = false;

        $msg = "{$created} word" . ($created === 1 ? '' : 's') . ' added';
        if ($skipped > 0) $msg .= ", {$skipped} already existed";
        Flux::toast($msg, variant: 'success');
    }

    public function toggleActive(int $id): void
    {
        $w = Bannedword::findOrFail($id);
        $w->update(['is_active' => ! $w->is_active]);
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId      = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if (! $this->deletingId) return;

        Bannedword::find($this->deletingId)?->delete();
        $this->showDeleteModal = false;
        $this->deletingId      = null;
        Flux::toast('Word removed.', variant: 'success');
    }

    public function with(): array
    {
        $words = Bannedword::query()
            ->when($this->search, fn ($q) => $q->where('word', 'like', "%{$this->search}%"))
            ->orderBy('word')
            ->paginate(30);

        return [
            'words'       => $words,
            'totalCount'  => Bannedword::count(),
            'activeCount' => Bannedword::where('is_active', true)->count(),
        ];
    }
};

?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Banned Words</flux:heading>
            <flux:subheading>
                Comments containing these words get flagged and greyed out on the live feed.
            </flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">
            Add Words
        </flux:button>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Total</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format($totalCount) }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Active</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-600 dark:text-emerald-400">
                {{ number_format($activeCount) }}
            </p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Inactive</p>
            <p class="mt-1 text-2xl font-semibold text-zinc-400">
                {{ number_format($totalCount - $activeCount) }}
            </p>
        </div>
    </div>

    {{-- Search --}}
    <flux:input
        wire:model.live.debounce.300ms="search"
        placeholder="Search banned words…"
        icon="magnifying-glass"
        clearable
        class="max-w-md"
    />

    {{-- Word chips --}}
    @if($words->isEmpty())
        <div class="rounded-lg border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-700">
            <flux:icon name="no-symbol" class="mx-auto h-10 w-10 text-zinc-400" />
            <p class="mt-2 text-sm text-zinc-500">
                {{ $search ? 'No words match your search.' : 'No banned words yet.' }}
            </p>
            <flux:button class="mt-4" icon="plus" wire:click="openCreate">Add your first words</flux:button>
        </div>
    @else
        <div class="flex flex-wrap gap-2 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            @foreach($words as $w)
                <div
                    wire:key="bw-{{ $w->id }}"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs transition',
                        'border-zinc-300 bg-white text-zinc-700 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200' => $w->is_active,
                        'border-zinc-200 bg-zinc-50 text-zinc-400 line-through dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-600' => ! $w->is_active,
                    ])
                >
                    <button
                        type="button"
                        wire:click="toggleActive({{ $w->id }})"
                        class="font-medium hover:underline"
                        title="{{ $w->is_active ? 'Click to deactivate' : 'Click to activate' }}"
                    >
                        {{ $w->word }}
                    </button>
                    <button
                        type="button"
                        wire:click="confirmDelete({{ $w->id }})"
                        class="text-zinc-400 hover:text-red-500"
                        title="Remove"
                    >
                        <flux:icon name="x-mark" class="h-3 w-3" />
                    </button>
                </div>
            @endforeach
        </div>

        <div>{{ $words->links() }}</div>
    @endif

    {{-- Add words modal --}}
    <flux:modal wire:model="showModal" class="md:w-[520px]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">Add Banned Words</flux:heading>
                <flux:subheading>
                    One per line, or comma-separated. Duplicates are skipped automatically.
                </flux:subheading>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                    Words
                </label>
                <textarea
                    wire:model="newWords"
                    rows="6"
                    placeholder="wantam&#10;stupid&#10;kasongo, tutam, maandamano"
                    class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 font-mono text-sm text-zinc-900 placeholder-zinc-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-500"
                    required
                ></textarea>
                @error('newWords')
                    <span class="text-xs text-red-600">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex items-center gap-2">
                <flux:switch wire:model.live="bulkActive" />
                <span class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $bulkActive ? 'Active immediately — flags comments right away' : 'Inactive — add as disabled' }}
                </span>
            </div>

            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">
                    Cancel
                </flux:button>
                <flux:button type="submit" variant="primary" icon="plus">
                    Add Words
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete confirm modal --}}
    <flux:modal wire:model="showDeleteModal" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Remove word?</flux:heading>
                <flux:subheading>Comments containing it will no longer be flagged.</flux:subheading>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete">Remove</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
