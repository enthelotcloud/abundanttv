<?php

use App\Models\Contactform;
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

    #[Url(as: 'filter', history: true)]
    public string $filter = '';   // '' | unread | read

    public ?int $viewingId = null;
    public array $viewing   = [];

    public bool $showDeleteModal = false;
    public ?int $deletingId      = null;

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedFilter(): void { $this->resetPage(); }

    public function openMessage(int $id): void
    {
        $msg = Contactform::findOrFail($id);

        $this->viewing = [
            'id'      => $msg->id,
            'name'    => $msg->name,
            'email'   => $msg->email,
            'phone'   => $msg->phone,
            'subject' => $msg->subject,
            'message' => $msg->message,
            'ip'      => $msg->ip_address,
            'when'    => $msg->created_at->format('M j, Y \a\t H:i'),
        ];
        $this->viewingId = $msg->id;

        if (! $msg->is_read) {
            $msg->update(['is_read' => true]);
        }
    }

    public function closeMessage(): void
    {
        $this->viewingId = null;
        $this->viewing   = [];
    }

    public function toggleRead(int $id): void
    {
        $msg = Contactform::findOrFail($id);
        $msg->update(['is_read' => ! $msg->is_read]);
    }

    public function markAllRead(): void
    {
        Contactform::where('is_read', false)->update(['is_read' => true]);
        Flux::toast('All messages marked as read.', variant: 'success');
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId      = $id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if (! $this->deletingId) return;

        Contactform::find($this->deletingId)?->delete();

        if ($this->viewingId === $this->deletingId) {
            $this->closeMessage();
        }

        $this->showDeleteModal = false;
        $this->deletingId      = null;
        Flux::toast('Message deleted.', variant: 'success');
    }

    public function with(): array
    {
        $messages = Contactform::query()
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name',    'like', "%{$this->search}%")
                  ->orWhere('email',   'like', "%{$this->search}%")
                  ->orWhere('subject', 'like', "%{$this->search}%")
                  ->orWhere('message', 'like', "%{$this->search}%");
            }))
            ->when($this->filter === 'unread', fn ($q) => $q->where('is_read', false))
            ->when($this->filter === 'read',   fn ($q) => $q->where('is_read', true))
            ->orderByDesc('created_at')
            ->paginate(20);

        return [
            'messages'      => $messages,
            'totalCount'    => Contactform::count(),
            'unreadCount'   => Contactform::where('is_read', false)->count(),
            'todayCount'    => Contactform::whereDate('created_at', today())->count(),
        ];
    }
};

?>

<div>
    <div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Contact Forms</flux:heading>
            <flux:subheading>Messages submitted through the public contact form.</flux:subheading>
        </div>
        @if($unreadCount > 0)
            <flux:button icon="check" wire:click="markAllRead">
                Mark all read
            </flux:button>
        @endif
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Total Messages</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format($totalCount) }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Unread</p>
            <p class="mt-1 text-2xl font-semibold {{ $unreadCount > 0 ? 'text-indigo-600 dark:text-indigo-400' : '' }}">
                {{ number_format($unreadCount) }}
            </p>
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
            placeholder="Search name, email, subject, message…"
            icon="magnifying-glass"
            clearable
            class="max-w-md flex-1"
        />
        <flux:select wire:model.live="filter" class="max-w-[160px]">
            <flux:select.option value="">All messages</flux:select.option>
            <flux:select.option value="unread">Unread</flux:select.option>
            <flux:select.option value="read">Read</flux:select.option>
        </flux:select>
    </div>

    {{-- List --}}
    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        @forelse($messages as $msg)
            <div
                wire:key="msg-{{ $msg->id }}"
                wire:click="openMessage({{ $msg->id }})"
                @class([
                    'flex cursor-pointer items-start gap-4 border-b border-zinc-200 px-4 py-3 transition last:border-b-0 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50',
                    'bg-indigo-50/40 dark:bg-indigo-950/20' => ! $msg->is_read,
                ])
            >
                {{-- Unread dot --}}
                <div class="mt-1.5 shrink-0">
                    @if(! $msg->is_read)
                        <span class="block h-2 w-2 rounded-full bg-indigo-500"></span>
                    @else
                        <span class="block h-2 w-2"></span>
                    @endif
                </div>

                {{-- Content --}}
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p @class([
                            'truncate text-sm',
                            'font-semibold' => ! $msg->is_read,
                            'font-medium'   => $msg->is_read,
                        ])>
                            {{ $msg->name }}
                        </p>
                        <span class="truncate text-xs text-zinc-500">{{ $msg->email }}</span>
                        @if($msg->phone)
                            <span class="text-xs text-zinc-500">· {{ $msg->phone }}</span>
                        @endif
                    </div>

                    @if($msg->subject)
                        <p class="mt-0.5 truncate text-sm text-zinc-700 dark:text-zinc-300">
                            {{ $msg->subject }}
                        </p>
                    @endif

                    <p class="mt-0.5 truncate text-xs text-zinc-500">
                        {{ Str::limit(strip_tags($msg->message), 120) }}
                    </p>
                </div>

                {{-- Time --}}
                <div class="shrink-0 text-right">
                    <p class="text-xs text-zinc-500 whitespace-nowrap">
                        {{ $msg->created_at->diffForHumans() }}
                    </p>
                </div>

                {{-- Actions --}}
                <div class="flex shrink-0 items-center gap-1" wire:click.stop>
                    <flux:button
                        size="sm"
                        variant="ghost"
                        :icon="$msg->is_read ? 'envelope' : 'envelope-open'"
                        wire:click="toggleRead({{ $msg->id }})"
                        :title="$msg->is_read ? 'Mark as unread' : 'Mark as read'"
                    />
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="trash"
                        wire:click="confirmDelete({{ $msg->id }})"
                        title="Delete"
                    />
                </div>
            </div>
        @empty
            <div class="p-12 text-center">
                <flux:icon name="envelope" class="mx-auto h-10 w-10 text-zinc-400" />
                <p class="mt-2 text-sm text-zinc-500">
                    {{ $search || $filter ? 'No messages match your filters.' : 'No messages yet.' }}
                </p>
            </div>
        @endforelse
    </div>

    <div>{{ $messages->links() }}</div>
</div>

{{-- Message detail modal --}}
<flux:modal wire:model="viewingId" class="md:w-[640px]">
    @if($viewing)
        <div class="space-y-4">
            <div class="flex items-start justify-between">
                <div>
                    <flux:heading size="lg">{{ $viewing['subject'] ?: '(No subject)' }}</flux:heading>
                    <flux:subheading>{{ $viewing['when'] }}</flux:subheading>
                </div>
            </div>

            <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                <div class="grid gap-2 text-sm sm:grid-cols-2">
                    <div>
                        <span class="text-xs font-medium text-zinc-500">From</span>
                        <p class="font-medium">{{ $viewing['name'] }}</p>
                    </div>
                    <div>
                        <span class="text-xs font-medium text-zinc-500">Email</span>
                        <p class="break-all">
                            <a href="mailto:{{ $viewing['email'] }}"
                               class="text-indigo-600 hover:underline dark:text-indigo-400">
                                {{ $viewing['email'] }}
                            </a>
                        </p>
                    </div>
                    @if($viewing['phone'])
                        <div>
                            <span class="text-xs font-medium text-zinc-500">Phone</span>
                            <p>
                                <a href="tel:{{ preg_replace('/\s+/', '', $viewing['phone']) }}"
                                   class="text-indigo-600 hover:underline dark:text-indigo-400">
                                    {{ $viewing['phone'] }}
                                </a>
                            </p>
                        </div>
                    @endif
                    @if($viewing['ip'])
                        <div>
                            <span class="text-xs font-medium text-zinc-500">IP</span>
                            <p class="font-mono text-xs">{{ $viewing['ip'] }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <div>
                <span class="text-xs font-medium text-zinc-500">Message</span>
                <div class="mt-1 whitespace-pre-line rounded-lg border border-zinc-200 bg-white p-3 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                    {{ $viewing['message'] }}
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                <flux:button variant="ghost" wire:click="closeMessage">Close</flux:button>
                <flux:button
                    variant="danger"
                    icon="trash"
                    wire:click="confirmDelete({{ $viewing['id'] }})"
                >
                    Delete
                </flux:button>
                <flux:button
                    variant="primary"
                    icon="envelope"
                    :href="'mailto:' . $viewing['email'] . '?subject=Re: ' . rawurlencode($viewing['subject'] ?: 'Your message')"
                >
                    Reply via email
                </flux:button>
            </div>
        </div>
    @endif
</flux:modal>

{{-- Delete confirm --}}
<flux:modal wire:model="showDeleteModal" class="md:w-96">
    <div class="space-y-4">
        <div>
            <flux:heading size="lg">Delete message?</flux:heading>
            <flux:subheading>This action cannot be undone.</flux:subheading>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
            <flux:button variant="danger" wire:click="delete">Delete</flux:button>
        </div>
    </div>
</flux:modal>

</div>
