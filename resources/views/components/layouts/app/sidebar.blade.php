<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="mr-5 flex items-center space-x-2" wire:navigate>
                <x-app-logo class="size-8" href="#"></x-app-logo>
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.group heading="Overview" class="grid">
                    @php
                        $dashboardRoute = match(true) {
                            auth()->user()->hasRole('admin') => 'admin.dashboard',
                            auth()->user()->hasRole('dean') => 'dean.dashboard',
                            auth()->user()->hasRole('program head') => 'program-head.dashboard',
                            auth()->user()->hasRole('faculty') => 'faculty.dashboard',
                            auth()->user()->hasRole('student') => 'student.dashboard',
                            auth()->user()->hasRole('staff') => 'staff.dashboard',
                            default => 'dashboard',
                        };
                    @endphp
                    <flux:navlist.item icon="home" :href="route($dashboardRoute)" :current="request()->routeIs($dashboardRoute)" wire:navigate>Dashboard</flux:navlist.item>
                </flux:navlist.group>

                @hasrole('admin')
                <flux:navlist.group heading="Management" class="grid">
                    <flux:navlist.item icon="user" :href="route('admin.users')" :current="request()->routeIs('admin.users')" wire:navigate>Manage Users</flux:navlist.item>
                    <flux:navlist.item icon="cog-6-tooth" :href="route('admin.evaluation-settings')" :current="request()->routeIs('admin.evaluation-settings')" wire:navigate>Evaluation Settings</flux:navlist.item>
                </flux:navlist.group>
                @endhasrole

                @hasanyrole('admin|dean|program head|faculty')
                <flux:navlist.group heading="Evaluations" class="grid">
                    @hasanyrole('admin|dean|program head')
                        <flux:navlist.item icon="clipboard-document-check" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>Manage Evaluations</flux:navlist.item>
                    @endhasanyrole
                    @hasrole('admin')
                        <flux:navlist.item icon="clipboard-document-list" :href="route('admin.questions')" :current="request()->routeIs('admin.questions')" wire:navigate>Manage Questions</flux:navlist.item>
                    @endhasrole
                    <flux:navlist.item icon="check-badge" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>Evaluation Results</flux:navlist.item>
                </flux:navlist.group>
                @endhasanyrole

                @hasrole('student')
                <flux:navlist.group heading="Evaluations" class="grid">
                    <flux:navlist.item icon="pencil-square" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>Professor Evaluation</flux:navlist.item>
                </flux:navlist.group>
                @endhasrole


                @hasanyrole('admin|dean|program head')
                <flux:navlist.group heading="Analytics & Reports" class="grid">
                        <flux:navlist.item icon="chart-bar" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>Analytics</flux:navlist.item>
                    
                    <flux:navlist.item icon="document-chart-bar" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>Reports</flux:navlist.item>
                    
                </flux:navlist.group>
                @endhasanyrole

                <flux:navlist.group heading="System" class="grid">
                    <flux:navlist.item icon="bell" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>Notifications</flux:navlist.item>
                </flux:navlist.group>
            </flux:navlist>

            <flux:spacer />

            <!-- Desktop User Menu -->
            <flux:dropdown position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevrons-up-down"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item href="/settings/profile" icon="cog" wire:navigate>Settings</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item href="/settings/profile" icon="cog" wire:navigate>Settings</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
