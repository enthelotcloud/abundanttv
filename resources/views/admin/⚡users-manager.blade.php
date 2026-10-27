<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'role', history: true)]
    public string $roleFilter = '';

    // Create form
    public bool $showCreateModal = false;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role = 'user';

    // Inline edit
    public ?int $editingUserId = null;
    public string $editingRole = '';

    // Delete
    public bool $showDeleteModal = false;
    public ?int $deletingUserId = null;

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedRoleFilter(): void { $this->resetPage(); }

    // ── Create ─────────────────────────────────────────────
    public function openCreateModal(): void
    {
        $this->reset(['name', 'email', 'password', 'role']);
        $this->role = 'user';
        $this->resetValidation();
        $this->showCreateModal = true;
    }

    public function createUser(): void
    {
        $validated = $this->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role'     => ['required', Rule::in(['admin', 'user'])],
        ]);

        User::create([
            'name'              => $validated['name'],
            'email'             => $validated['email'],
            'password'          => Hash::make($validated['password']),
            'role'              => $validated['role'],
            'email_verified_at' => now(),   // admin-created → auto-verified
        ]);

        $this->showCreateModal = false;
        $this->reset(['name', 'email', 'password', 'role']);
        Flux::toast('User created.', variant: 'success');
    }

    // ── Edit role ──────────────────────────────────────────
    public function startEditing(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->editingUserId = $user->id;
        $this->editingRole   = $user->role;
    }

    public function cancelEditing(): void
    {
        $this->editingUserId = null;
        $this->editingRole   = '';
    }

    public function saveRole(): void
    {
        $this->validate(['editingRole' => ['required', Rule::in(['admin', 'user'])]]);

        $user = User::findOrFail($this->editingUserId);

        if ($user->id === auth()->id() && $this->editingRole !== 'admin') {
            Flux::toast('You cannot demote yourself.', variant: 'danger');
            return;
        }

        if ($user->role === 'admin'
            && $this->editingRole !== 'admin'
            && User::where('role', 'admin')->count() <= 1) {
            Flux::toast('Cannot demote the last admin.', variant: 'danger');
            return;
        }

        $user->update(['role' => $this->editingRole]);
        $this->cancelEditing();
        Flux::toast('Role updated.', variant: 'success');
    }

    // ── Delete ─────────────────────────────────────────────
    public function confirmDelete(int $userId): void
    {
        if ($userId === auth()->id()) {
            Flux::toast('You cannot delete yourself.', variant: 'danger');
            return;
        }
        $this->deletingUserId  = $userId;
        $this->showDeleteModal = true;
    }

    public function deleteUser(): void
    {
        if (! $this->deletingUserId) return;

        $user = User::find($this->deletingUserId);
        if (! $user) { $this->showDeleteModal = false; return; }

        if ($user->id === auth()->id()) {
            Flux::toast('You cannot delete yourself.', variant: 'danger');
            $this->showDeleteModal = false;
            return;
        }

        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            Flux::toast('Cannot delete the last admin.', variant: 'danger');
            $this->showDeleteModal = false;
            return;
        }

        $user->delete();
        $this->showDeleteModal = false;
        $this->deletingUserId  = null;
        Flux::toast('User deleted.', variant: 'success');
    }

    // ── Data ───────────────────────────────────────────────
    public function with(): array
    {
        $users = User::query()
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name',  'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->when($this->roleFilter, fn ($q) => $q->where('role', $this->roleFilter))
            ->orderByDesc('created_at')
            ->paginate(15);

        return ['users' => $users];
    }
};

?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">Users</flux:heading>
            <flux:subheading>Manage accounts and roles.</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            New User
        </flux:button>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap gap-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            placeholder="Search name or email…"
            icon="magnifying-glass"
            clearable
            class="max-w-sm"
        />
        <flux:select wire:model.live="roleFilter" class="max-w-[180px]">
            <flux:select.option value="">All roles</flux:select.option>
            <flux:select.option value="admin">Admin</flux:select.option>
            <flux:select.option value="user">User</flux:select.option>
        </flux:select>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
        <table class="w-full text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-900">
                <tr class="text-left text-zinc-600 dark:text-zinc-400">
                    <th class="px-4 py-3 font-medium">Name</th>
                    <th class="px-4 py-3 font-medium">Email</th>
                    <th class="px-4 py-3 font-medium">Role</th>
                    <th class="px-4 py-3 font-medium">Joined</th>
                    <th class="px-4 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse($users as $user)
                    <tr wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <flux:avatar size="sm" :name="$user->name" :initials="$user->initials()" />
                                <span class="font-medium">{{ $user->name }}</span>
                                @if($user->id === auth()->id())
                                    <flux:badge size="sm" color="zinc">You</flux:badge>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            @if($editingUserId === $user->id)
                                <div class="flex items-center gap-2">
                                    <flux:select wire:model="editingRole" size="sm">
                                        <flux:select.option value="admin">Admin</flux:select.option>
                                        <flux:select.option value="user">User</flux:select.option>
                                    </flux:select>
                                    <flux:button size="sm" variant="primary" wire:click="saveRole">Save</flux:button>
                                    <flux:button size="sm" variant="ghost" wire:click="cancelEditing">Cancel</flux:button>
                                </div>
                            @else
                                <flux:badge :color="$user->role === 'admin' ? 'amber' : 'zinc'">
                                    {{ ucfirst($user->role) }}
                                </flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-zinc-500">
                            {{ $user->created_at->format('M j, Y') }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                <flux:button
                                    size="sm" variant="ghost" icon="pencil"
                                    wire:click="startEditing({{ $user->id }})"
                                    :disabled="$editingUserId === $user->id"
                                />
                                <flux:button
                                    size="sm" variant="ghost" icon="trash"
                                    wire:click="confirmDelete({{ $user->id }})"
                                    :disabled="$user->id === auth()->id()"
                                />
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-zinc-500">
                            No users found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $users->links() }}</div>

    {{-- Create modal --}}
    <flux:modal wire:model="showCreateModal" class="md:w-96">
        <form wire:submit="createUser" class="space-y-4">
            <div>
                <flux:heading size="lg">Create User</flux:heading>
                <flux:subheading>Add a new account.</flux:subheading>
            </div>

            <flux:input wire:model="name" label="Name" placeholder="Jane Doe" />
            <flux:input wire:model="email" type="email" label="Email" placeholder="jane@example.com" />
            <flux:input wire:model="password" type="password" label="Password" placeholder="Min 8 characters" />
            <flux:select wire:model="role" label="Role">
                <flux:select.option value="user">User</flux:select.option>
                <flux:select.option value="admin">Admin</flux:select.option>
            </flux:select>

            <div class="flex justify-end gap-2 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCreateModal', false)">
                    Cancel
                </flux:button>
                <flux:button type="submit" variant="primary">Create</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete confirm modal --}}
    <flux:modal wire:model="showDeleteModal" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Delete user?</flux:heading>
                <flux:subheading>This action cannot be undone.</flux:subheading>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
                <flux:button variant="danger" wire:click="deleteUser">Delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
