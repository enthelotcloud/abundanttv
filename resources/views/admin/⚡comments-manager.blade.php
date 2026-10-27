<?php

use App\Models\Bannedword;
use App\Models\Comment;
use App\Models\Show;
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

    #[Url(as: 'show', history: true)]
    public string $showFilter = '';

    #[Url(as: 'status', history: true)]
    public string $statusFilter = '';   // '' | flagged | clean

    public array $showsList = [];

    public bool $showDeleteModal     = false;
    public ?int $deletingId          = null;

    public bool $showBulkDeleteModal = false;

    public function mount(): void
    {
        $this->showsList = Show::orderBy('title')->get(['id', 'title'])->toArray();
    }

    public function updatedSearch(): void       { $this->resetPage(); }
    public function updatedShowFilter(): void   { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }

    /** Re-run banned-word check across every comment. Useful after adding new words. */
    public function rescan(): void
    {
        $updated = 0;

        Comment::chunkById(200, function ($comments) use (&$updated) {
            foreach ($comments as $c) {
                $flagged = Bannedword::matches($c->body);
                if ($c->has_banned_word !== $flagged) {
                    $c->update(['has_banned_word' => $flagged]);
                    $updated++;
                }
            }
        });

        Flux::toast(
            $updated === 0
                ? 'All comments already up to date.'
                : "Re-scanned — {$updated} comment" . ($updated === 1 ? '' : 's') . ' updated.',
            variant: 'success'
        );
    }

    public function clearFlag(int $id): void
    {
        Comment::findOrFail($id)->update(['has_banned_word' => false]);
        Flux::toast('Flag cleared — comment now shows normally.', variant: 'success');
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId      = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if (! $this->deletingId) return;

        Comment::find($this->deletingId)?->delete();
        $this->showDeleteModal = false;
        $this->deletingId      = null;
        Flux::toast('Comment deleted.', variant: 'success');
    }

    public function confirmBulkDelete(): void
    {
        $this->showBulkDeleteModal = true;
    }

    public function deleteFlagged(): void
    {
        $count = Comment::where('has_banned_word', true)->delete();
        $this->showBulkDeleteModal = false;
        Flux::toast(
            "{$count} flagged comment" . ($count === 1 ? '' : 's') . ' deleted.',
            variant: 'success'
        );
    }

    public function with(): array
    {
        $comments = Comment::query()
            ->with(['show:id,title', 'schedule:id,start_time,end_time'])
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name',  'like', "%{$this->search}%")
                  ->orWhere('phone', 'like', "%{$this->search}%")
                  ->orWhere('body',  'like', "%{$this->search}%");
            }))
            ->when($this->showFilter, fn ($q) => $q->where('show_id', (int) $this->showFilter))
            ->when($this->statusFilter === 'flagged', fn ($q) => $q->where('has_banned_word', true))
            ->when($this->statusFilter === 'clean',   fn ($q) => $q->where('has_banned_word', false))
            ->orderByDesc('created_at')
            ->paginate(30);

        return [
            'comments'     => $comments,
            'totalCount'   => Comment::count(),
            'flaggedCount' => Comment::where('has_banned_word', true)->count(),
            'todayCount'   => Comment::whereDate('created_at', today())->count(),
        ];
    }
};

?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading size="xl">Comments & Moderation</flux:heading>
            <flux:subheading>Live viewer comments across all shows.</flux:subheading>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button icon="arrow-path" wire:click="rescan" title="Re-check every comment against current banned words">
                Rescan
            </flux:button>
            @if($flaggedCount > 0)
                <flux:button variant="danger" icon="trash" wire:click="confirmBulkDelete">
                    Delete flagged ({{ $flaggedCount }})
                </flux:button>
            @endif
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Total</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format($totalCount) }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Flagged</p>
            <p @class([
                'mt-1 text-2xl font-semibold',
                'text-red-600 dark:text-red-400' => $flaggedCount > 0,
                'text-zinc-400' => $flaggedCount === 0,
            ])>{{ number_format($flaggedCount) }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Today</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format($todayCount) }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap items-center gap-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search name, phone, body…"
            icon="magnifying-glass"
            clearable
            class="max-w-md flex-1"
        />
        <flux:select wire:model.live="showFilter" class="max-w-[200px]">
            <flux:select.option value="">All shows</flux:select.option>
            @foreach($showsList as $show)
                <flux:select.option value="{{ $show['id'] }}">{{ $show['title'] }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="statusFilter" class="max-w-[160px]">
            <flux:select.option value="">All comments</flux:select.option>
            <flux:select.option value="flagged">Flagged</flux:select.option>
            <flux:select.option value="clean">Clean</flux:select.option>
        </flux:select>
    </div>

    {{-- List --}}
    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        @forelse($comments as $comment)
            <div
                wire:key="cm-{{ $comment->id }}"
                @class([
                    'flex items-start gap-3 border-b border-zinc-200 px-4 py-3 transition last:border-b-0 dark:border-zinc-700',
                    'bg-red-50/60 dark:bg-red-950/20' => $comment->has_banned_word,
                    'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' => ! $comment->has_banned_word,
                ])
            >
                {{-- Flag dot --}}
                <div class="mt-1.5 shrink-0">
                    @if($comment->has_banned_word)
                        <span class="block h-2 w-2 rounded-full bg-red-500" title="Contains a banned word"></span>
                    @else
                        <span class="block h-2 w-2 rounded-full bg-emerald-400"></span>
                    @endif
                </div>

                {{-- Content --}}
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                        <span @class([
                            'truncate text-sm',
                            'font-semibold' => ! $comment->has_banned_word,
                            'font-semibold text-red-700 dark:text-red-400' => $comment->has_banned_word,
                        ])>{{ $comment->name }}</span>

                        <span class="font-mono text-xs text-zinc-500">{{ $comment->phone }}</span>

                        <span class="text-xs text-zinc-400">·</span>

                        <span class="text-xs text-zinc-500 whitespace-nowrap">
                            {{ $comment->created_at->diffForHumans() }}
                        </span>

                        @if($comment->show)
                            <span class="text-xs text-zinc-400">·</span>
                            <span class="text-xs font-medium text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                                {{ $comment->show->title }}
                                @if($comment->schedule)
                                    <span class="text-zinc-400">
                                        @ {{ \Illuminate\Support\Carbon::parse($comment->schedule->start_time)->format('H:i') }}
                                    </span>
                                @endif
                            </span>
                        @endif
                    </div>

                    <p @class([
                        'mt-1 line-clamp-2 text-sm',
                        'text-zinc-700 dark:text-zinc-300' => ! $comment->has_banned_word,
                        'text-red-700 line-through decoration-red-400/60 dark:text-red-400' => $comment->has_banned_word,
                    ])>{{ $comment->body }}</p>
                </div>

                {{-- Actions --}}
                <div class="flex shrink-0 items-center gap-1">
                    @if($comment->has_banned_word)
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="shield-check"
                            wire:click="clearFlag({{ $comment->id }})"
                            title="Clear false positive (keep comment, remove flag)"
                        />
                    @endif
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="trash"
                        wire:click="confirmDelete({{ $comment->id }})"
                        title="Delete comment"
                    />
                </div>
            </div>
        @empty
            <div class="p-12 text-center">
                <flux:icon name="chat-bubble-left-right" class="mx-auto h-10 w-10 text-zinc-400" />
                <p class="mt-2 text-sm text-zinc-500">
                    {{ $search || $showFilter || $statusFilter ? 'No comments match your filters.' : 'No comments yet.' }}
                </p>
            </div>
        @endforelse
    </div>

    <div>{{ $comments->links() }}</div>

    {{-- Delete single modal --}}
    <flux:modal wire:model="showDeleteModal" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Delete comment?</flux:heading>
                <flux:subheading>This action cannot be undone.</flux:subheading>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete">Delete</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Bulk delete flagged modal --}}
    <flux:modal wire:model="showBulkDeleteModal" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Delete all flagged comments?</flux:heading>
                <flux:subheading>
                    Removes {{ number_format($flaggedCount) }} comment{{ $flaggedCount === 1 ? '' : 's' }} permanently.
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showBulkDeleteModal', false)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="deleteFlagged">Delete All</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
