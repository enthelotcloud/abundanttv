<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="/" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>

                {{-- ── ADMIN ONLY ─────────────────────────────────── --}}
                @if(auth()->user()->hasRole('admin'))

                    <flux:sidebar.group :heading="__('Broadcasting')" class="grid">
                        <flux:sidebar.item icon="home" :href="route('admin.dashboard')" :current="request()->routeIs('admin.dashboard')" wire:navigate>
                            {{ __('Dashboard') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="tv" :href="route('admin.shows')" :current="request()->routeIs('admin.shows')" wire:navigate>
                            {{ __('Shows') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="calendar" :href="route('admin.schedules')" :current="request()->routeIs('admin.schedules')" wire:navigate>
                            {{ __('Schedules') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="users" :href="route('admin.presenters')" :current="request()->routeIs('admin.presenters')" wire:navigate>
                            {{ __('Presenters') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('Engagement')" class="grid">
                        <flux:sidebar.item icon="chat-bubble-bottom-center-text" :href="route('admin.comments')" :current="request()->routeIs('admin.comments')" wire:navigate>
                            {{ __('Comments & Moderation') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="no-symbol" :href="route('admin.bannedwords')" :current="request()->routeIs('admin.bannedwords')" wire:navigate>
                            {{ __('Banned Words') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="envelope" :href="route('admin.contactforms')" :current="request()->routeIs('admin.contactforms')" wire:navigate>
                            {{ __('Contact Forms') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="question-mark-circle" :href="route('admin.faqs')" :current="request()->routeIs('admin.faqs')" wire:navigate>
                            {{ __('FAQs') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('Configuration')" class="grid">
                        <flux:sidebar.item icon="phone" :href="route('admin.contacts')" :current="request()->routeIs('admin.contacts')" wire:navigate>
                            {{ __('Contacts') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="user-circle" :href="route('admin.users')" :current="request()->routeIs('admin.users')" wire:navigate>
                            {{ __('Users') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="chart-bar" :href="route('admin.viewlogs')" :current="request()->routeIs('admin.viewlogs')" wire:navigate>
                            {{ __('View Logs') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                {{-- ── NON-ADMIN (regular users) ──────────────────── --}}
                @else
                    <flux:sidebar.group :heading="__('AbundantTV')" class="grid">
                        <flux:sidebar.item icon="play" :href="route('home')" wire:navigate>
                            {{ __('Watch Live') }}
                        </flux:sidebar.item>
                        ...
                    </flux:sidebar.group>
                @endif

            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        {{-- Mobile header --}}
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />
                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
