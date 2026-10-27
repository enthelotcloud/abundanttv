<?php

use App\Models\Contact;
use Flux\Flux;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $label     = '';
    public string $type      = 'whatsapp';
    public string $value     = '';
    public bool   $is_active = true;

    public $icon = null;
    public ?string $existingIcon = null;

    public bool    $showDeleteModal = false;
    public ?int    $deletingId      = null;
    public ?string $deletingLabel   = '';

    public function openCreate(): void
    {
        $this->reset(['editingId', 'label', 'value', 'icon', 'existingIcon']);
        $this->type      = 'whatsapp';
        $this->is_active = true;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $c = Contact::findOrFail($id);

        $this->editingId    = $c->id;
        $this->label        = $c->label;
        $this->type         = $c->type;
        $this->value        = $c->value;
        $this->is_active    = (bool) $c->is_active;
        $this->existingIcon = $c->icon_path;
        $this->icon         = null;

        $this->resetValidation();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate([
            'label' => ['required', 'string', 'max:100'],
            'type'  => ['required', Rule::in([
                'whatsapp','phone','email','facebook','instagram',
                'tiktok','youtube','x','custom',
            ])],
            'value' => ['required', 'string', 'max:255'],
            'icon'  => ['nullable', 'file', 'mimes:svg,png', 'max:512'],
        ]);

        if (! $this->editingId && ! $this->icon) {
            $this->addError('icon', 'Icon is required for a new contact.');
            return;
        }

        $iconPath = $this->existingIcon;

        if ($this->icon) {
            if ($this->existingIcon) {
                Storage::disk('public')->delete($this->existingIcon);
            }
            $iconPath = $this->icon->store('contacts', 'public');
        }

        $data = [
            'label'     => $this->label,
            'type'      => $this->type,
            'value'     => $this->value,
            'icon_path' => $iconPath,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            Contact::findOrFail($this->editingId)->update($data);
            Flux::toast('Contact updated.', variant: 'success');
        } else {
            $data['sort_order'] = (Contact::max('sort_order') ?? 0) + 1;
            Contact::create($data);
            Flux::toast('Contact created.', variant: 'success');
        }

        $this->showModal = false;
    }

    public function toggleActive(int $id): void
    {
        $c = Contact::findOrFail($id);
        $c->update(['is_active' => ! $c->is_active]);
    }

    public function moveUp(int $id): void   { $this->swapOrder($id, 'up'); }
    public function moveDown(int $id): void { $this->swapOrder($id, 'down'); }

    private function swapOrder(int $id, string $direction): void
    {
        $current  = Contact::findOrFail($id);
        $operator = $direction === 'up' ? '<' : '>';
        $order    = $direction === 'up' ? 'desc' : 'asc';

        $swap = Contact::where('sort_order', $operator, $current->sort_order)
            ->orderBy('sort_order', $order)
            ->first();

        if (! $swap) return;

        $currentOrder = $current->sort_order;
        $current->update(['sort_order' => $swap->sort_order]);
        $swap->update(['sort_order' => $currentOrder]);
    }

    public function confirmDelete(int $id): void
    {
        $c = Contact::findOrFail($id);

        $this->deletingId      = $c->id;
        $this->deletingLabel   = $c->label;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        if (! $this->deletingId) return;

        $c = Contact::find($this->deletingId);
        if ($c) {
            if ($c->icon_path) {
                Storage::disk('public')->delete($c->icon_path);
            }
            $c->delete();
        }

        $this->showDeleteModal = false;
        $this->deletingId      = null;
        Flux::toast('Contact deleted.', variant: 'success');
    }

    public function with(): array
    {
        $contacts = Contact::orderBy('sort_order')->orderBy('id')->get();

        return [
            'contacts'    => $contacts,
            'totalCount'  => $contacts->count(),
            'activeCount' => $contacts->where('is_active', true)->count(),
        ];
    }
};

?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Contacts</flux:heading>
            <flux:subheading>Social &amp; contact links shown in the footer.</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">
            Add Contact
        </flux:button>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-4">
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Total</p>
            <p class="mt-1 text-2xl font-semibold">{{ number_format($totalCount) }}</p>
        </div>
        <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <p class="text-xs font-medium text-zinc-500">Active</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-600 dark:text-emerald-400">{{ number_format($activeCount) }}</p>
        </div>
    </div>

    {{-- List --}}
    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        @forelse($contacts as $contact)
            <div wire:key="contact-{{ $contact->id }}"
                 class="flex items-center gap-3 border-b border-zinc-200 px-4 py-3 last:border-b-0 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800/50">

                {{-- Up/down --}}
                <div class="flex shrink-0 flex-col">
                    <button type="button"
                            wire:click="moveUp({{ $contact->id }})"
                            @disabled($loop->first)
                            class="rounded p-0.5 text-zinc-400 hover:bg-zinc-200 hover:text-zinc-700 disabled:opacity-30 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                            title="Move up">
                        <flux:icon name="chevron-up" class="h-3 w-3" />
                    </button>
                    <button type="button"
                            wire:click="moveDown({{ $contact->id }})"
                            @disabled($loop->last)
                            class="rounded p-0.5 text-zinc-400 hover:bg-zinc-200 hover:text-zinc-700 disabled:opacity-30 dark:hover:bg-zinc-700 dark:hover:text-zinc-200"
                            title="Move down">
                        <flux:icon name="chevron-down" class="h-3 w-3" />
                    </button>
                </div>

                {{-- Icon --}}
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-zinc-200 bg-white p-1.5 dark:border-zinc-700 dark:bg-zinc-800">
                    @if($contact->icon_path)
                        <img src="{{ asset('storage/'.$contact->icon_path) }}"
                             alt="{{ $contact->label }}"
                             class="h-full w-full object-contain" />
                    @else
                        <flux:icon name="photo" class="h-4 w-4 text-zinc-400" />
                    @endif
                </div>

                {{-- Info --}}
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p @class([
                            'truncate text-sm',
                            'font-semibold' => $contact->is_active,
                            'font-medium text-zinc-500 line-through' => ! $contact->is_active,
                        ])>{{ $contact->label }}</p>

                        <flux:badge size="sm" color="zinc">{{ ucfirst($contact->type) }}</flux:badge>

                        @if(! $contact->is_active)
                            <flux:badge size="sm" color="amber">Inactive</flux:badge>
                        @endif
                    </div>
                    <p class="mt-0.5 truncate font-mono text-xs text-zinc-500">{{ $contact->value }}</p>
                </div>

                {{-- Actions --}}
                <div class="flex shrink-0 items-center gap-1">
                    <flux:button
                        size="sm" variant="ghost"
                        :icon="$contact->is_active ? 'eye-slash' : 'eye'"
                        wire:click="toggleActive({{ $contact->id }})"
                        :title="$contact->is_active ? 'Deactivate' : 'Activate'"
                    />
                    <flux:button size="sm" variant="ghost" icon="pencil"
                                 wire:click="openEdit({{ $contact->id }})" title="Edit" />
                    <flux:button size="sm" variant="ghost" icon="trash"
                                 wire:click="confirmDelete({{ $contact->id }})" title="Delete" />
                </div>
            </div>
        @empty
            <div class="p-12 text-center">
                <flux:icon name="link" class="mx-auto h-10 w-10 text-zinc-400" />
                <p class="mt-2 text-sm text-zinc-500">No contacts yet.</p>
                <flux:button class="mt-4" icon="plus" wire:click="openCreate">Add your first contact</flux:button>
            </div>
        @endforelse
    </div>

    {{-- Create / Edit modal --}}
    <flux:modal wire:model="showModal" class="md:w-[540px]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit Contact' : 'New Contact' }}</flux:heading>
                <flux:subheading>Social and contact link shown in the footer.</flux:subheading>
            </div>

            {{-- Icon --}}
            <div class="flex items-start gap-4">
                <div class="shrink-0">
                    <div class="flex h-16 w-16 items-center justify-center rounded-lg border border-dashed border-zinc-300 bg-white p-2 dark:border-zinc-600 dark:bg-zinc-800">
                        @if($icon)
                            <img src="{{ $icon->temporaryUrl() }}" class="h-full w-full object-contain" />
                        @elseif($existingIcon)
                            <img src="{{ asset('storage/'.$existingIcon) }}" class="h-full w-full object-contain" />
                        @else
                            <flux:icon name="photo" class="h-6 w-6 text-zinc-400" />
                        @endif
                    </div>
                </div>
                <div class="flex-1">
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-zinc-300 bg-white px-3 py-1.5 text-sm font-medium hover:bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800 dark:hover:bg-zinc-700">
                        <flux:icon name="arrow-up-tray" class="h-4 w-4" />
                        {{ $icon ? 'Change icon' : ($existingIcon ? 'Replace icon' : 'Choose icon') }}
                        <input type="file" wire:model="icon" accept=".svg,.png" class="hidden" />
                    </label>
                    <p class="mt-1 text-xs text-zinc-500">SVG or PNG. Max 512 KB. Square works best.</p>
                    @error('icon') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                    <div wire:loading wire:target="icon" class="mt-1 text-xs text-zinc-500">Uploading…</div>
                </div>
            </div>

            {{-- Label + Type --}}
            <div class="grid gap-3 sm:grid-cols-2">
                <flux:input wire:model="label" label="Label" placeholder="WhatsApp" required />
                <flux:select wire:model="type" label="Type">
                    <flux:select.option value="whatsapp">WhatsApp</flux:select.option>
                    <flux:select.option value="phone">Phone</flux:select.option>
                    <flux:select.option value="email">Email</flux:select.option>
                    <flux:select.option value="facebook">Facebook</flux:select.option>
                    <flux:select.option value="instagram">Instagram</flux:select.option>
                    <flux:select.option value="tiktok">TikTok</flux:select.option>
                    <flux:select.option value="youtube">YouTube</flux:select.option>
                    <flux:select.option value="x">X / Twitter</flux:select.option>
                    <flux:select.option value="custom">Custom URL</flux:select.option>
                </flux:select>
            </div>

            {{-- Value --}}
            <flux:input wire:model="value" label="Value" placeholder="+254712345678 or https://…" />

            {{-- Active --}}
            <div class="flex items-center gap-2">
                <flux:switch wire:model.live="is_active" />
                <span class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ $is_active ? 'Active — shows in footer' : 'Inactive — hidden from footer' }}
                </span>
            </div>

            <div class="flex justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">
                    {{ $editingId ? 'Save Changes' : 'Create Contact' }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete modal --}}
    <flux:modal wire:model="showDeleteModal" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Delete contact?</flux:heading>
                <flux:subheading>
                    Removing <strong>{{ $deletingLabel }}</strong> removes it from the footer.
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete">Delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
