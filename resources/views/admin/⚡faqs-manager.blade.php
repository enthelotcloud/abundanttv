<?php

use App\Models\Faq;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showModal = false;
    public ?int $editingId  = null;

    public string $question     = '';
    public string $answer       = '';
    public string $category     = '';
    public bool   $is_published = true;

    public bool $showDeleteModal = false;
    public ?int $deletingId      = null;

    public array $knownCategories = [];

    public function mount(): void
    {
        $this->loadCategories();
    }

    public function loadCategories(): void
    {
        $this->knownCategories = Faq::whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values()
            ->toArray();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'question', 'answer', 'category']);
        $this->is_published = true;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $faq = Faq::findOrFail($id);

        $this->editingId    = $faq->id;
        $this->question     = $faq->question;
        $this->answer       = $faq->answer;
        $this->category     = $faq->category ?? '';
        $this->is_published = $faq->is_published;

        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'question' => ['required', 'string', 'max:255'],
            'answer'   => ['required', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $data = [
            'question'     => $validated['question'],
            'answer'       => $validated['answer'],
            'category'     => $validated['category'] ?: null,
            'is_published' => $this->is_published,
        ];

        if ($this->editingId) {
            Faq::findOrFail($this->editingId)->update($data);
            Flux::toast('FAQ updated.', variant: 'success');
        } else {
            $data['sort_order'] = (Faq::max('sort_order') ?? 0) + 1;
            Faq::create($data);
            Flux::toast('FAQ created.', variant: 'success');
        }

        $this->showModal = false;
        $this->loadCategories();
    }

    public function togglePublished(int $id): void
    {
        $faq = Faq::findOrFail($id);
        $faq->update(['is_published' => ! $faq->is_published]);
    }

    public function moveUp(int $id): void
    {
        $this->swapOrder($id, 'up');
    }

    public function moveDown(int $id): void
    {
        $this->swapOrder($id, 'down');
    }

    private function swapOrder(int $id, string $direction): void
    {
        $current = Faq::findOrFail($id);

        $operator = $direction === 'up' ? '<' : '>';
        $order    = $direction === 'up' ? 'desc' : 'asc';

        $swap = Faq::where('sort_order', $operator, $current->sort_order)
            ->orderBy('sort_order', $order)
            ->first();

        if (! $swap) return;

        $currentOrder = $current->sort_order;
        $current->update(['sort_order' => $swap->sort_order]);
        $swap->update(['sort_order' => $currentOrder]);
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId      = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if (! $this->deletingId) return;

        Faq::find($this->deletingId)?->delete();
        $this->showDeleteModal = false;
        $this->deletingId      = null;
        Flux::toast('FAQ deleted.', variant: 'success');
    }

    public function with(): array
    {
        return [
            'faqs' => Faq::orderBy('sort_order')->get(),
        ];
    }
};

?>

<div>
    <div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">FAQs</flux:heading>
            <flux:subheading>Frequently asked questions on the public FAQ page.</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">
            Add FAQ
        </flux:button>
    </div>

    {{-- List --}}
    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        @forelse($faqs as $faq)
            <div
                wire:key="faq-{{ $faq->id }}"
                class="flex items-center gap-4 border-b border-zinc-200 px-4 py-3 last:border-b-0 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50"
            >
                {{-- Up/down --}}
                <div class="flex flex-col">
                    <button
                        type="button"
                        wire:click="moveUp({{ $faq->id }})"
                        @disabled($loop->first)
                        class="rounded p-0.5 text-zinc-400 hover:bg-zinc-200 hover:text-zinc-700 disabled:opacity-30 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                        title="Move up"
                    >
                        <flux:icon name="chevron-up" class="h-3 w-3" />
                    </button>
                    <button
                        type="button"
                        wire:click="moveDown({{ $faq->id }})"
                        @disabled($loop->last)
                        class="rounded p-0.5 text-zinc-400 hover:bg-zinc-200 hover:text-zinc-700 disabled:opacity-30 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                        title="Move down"
                    >
                        <flux:icon name="chevron-down" class="h-3 w-3" />
                    </button>
                </div>

                {{-- Content --}}
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="truncate text-sm font-medium">{{ $faq->question }}</p>

                        @if($faq->category)
                            <flux:badge size="sm" color="zinc">{{ $faq->category }}</flux:badge>
                        @endif

                        @if(! $faq->is_published)
                            <flux:badge size="sm" color="amber">Draft</flux:badge>
                        @endif
                    </div>
                    <p class="mt-0.5 truncate text-xs text-zinc-500">{{ Str::limit($faq->answer, 90) }}</p>
                </div>

                {{-- Actions --}}
                <div class="flex shrink-0 items-center gap-1">
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="eye"
                        wire:click="togglePublished({{ $faq->id }})"
                        :title="$faq->is_published ? 'Unpublish' : 'Publish'"
                        :class="! $faq->is_published ? 'text-amber-600' : ''"
                    />
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="pencil"
                        wire:click="openEdit({{ $faq->id }})"
                        title="Edit"
                    />
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="trash"
                        wire:click="confirmDelete({{ $faq->id }})"
                        title="Delete"
                    />
                </div>
            </div>
        @empty
            <div class="p-12 text-center">
                <flux:icon name="question-mark-circle" class="mx-auto h-10 w-10 text-zinc-400" />
                <p class="mt-2 text-sm text-zinc-500">No FAQs yet.</p>
                <flux:button class="mt-4" icon="plus" wire:click="openCreate">Add your first FAQ</flux:button>
            </div>
        @endforelse
    </div>
</div>

{{-- Category suggestions (shared) --}}
<datalist id="faq-categories">
    @foreach($knownCategories as $cat)
        <option value="{{ $cat }}"></option>
    @endforeach
</datalist>

{{-- Create / Edit modal --}}
<flux:modal wire:model="showModal" class="md:w-[600px]">
    <form wire:submit="save" class="space-y-4">
        <div>
            <flux:heading size="lg">
                {{ $editingId ? 'Edit FAQ' : 'New FAQ' }}
            </flux:heading>
            <flux:subheading>
                {{ $editingId ? 'Update the question, answer, or category.' : 'Add a new question to the public FAQ page.' }}
            </flux:subheading>
        </div>

        <flux:input
            wire:model="question"
            label="Question"
            placeholder="How do I watch live?"
            required
        />

        <flux:input
            wire:model="category"
            label="Category (optional)"
            placeholder="e.g. Streaming, Account, Payments"
            list="faq-categories"
        />

        <div>
            <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                Answer
            </label>
            <textarea
                wire:model="answer"
                rows="6"
                placeholder="Write a clear, helpful answer..."
                class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-500"
                required
            ></textarea>
            @error('answer')
                <span class="text-xs text-red-600">{{ $message }}</span>
            @enderror
        </div>

        <div class="flex items-center gap-2">
            <flux:switch wire:model.live="is_published" />
            <span class="text-sm text-zinc-600 dark:text-zinc-400">
                {{ $is_published ? 'Published — visible on public page' : 'Draft — hidden from public page' }}
            </span>
        </div>

        <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">
                Cancel
            </flux:button>
            <flux:button type="submit" variant="primary">
                {{ $editingId ? 'Save Changes' : 'Create FAQ' }}
            </flux:button>
        </div>
    </form>
</flux:modal>

{{-- Delete confirm modal --}}
<flux:modal wire:model="showDeleteModal" class="md:w-96">
    <div class="space-y-4">
        <div>
            <flux:heading size="lg">Delete FAQ?</flux:heading>
            <flux:subheading>This action cannot be undone.</flux:subheading>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">
                Cancel
            </flux:button>
            <flux:button variant="danger" wire:click="delete">
                Delete
            </flux:button>
        </div>
    </div>
</flux:modal>
</div>
