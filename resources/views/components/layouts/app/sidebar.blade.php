<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="flex items-center px-2 py-1 mb-2" wire:navigate>
                <x-app-logo></x-app-logo>
            </a>

            <flux:navlist variant="outline">
                @php
                    $user = auth()->user();
                    $dashboardRoute = match(true) {
                        $user->hasRole('admin') => 'admin.dashboard',
                        $user->hasRole('dean') => 'dean.dashboard',
                        $user->hasRole('program head') => 'program-head.dashboard',
                        $user->hasRole('faculty') => 'faculty.dashboard',
                        $user->hasRole('student') => 'student.dashboard',
                        $user->hasRole('staff') => 'staff.dashboard',
                        default => 'dashboard',
                    };

                    $unreadNotificationsCount = 0;
                    if ($user) {
                        if (request()->routeIs('notifications')) {
                            $unreadNotificationsCount = 0;
                        } else {
                            $notifications = $user->getNotifications();
                            $lastViewed = $user->notifications_last_viewed_at;
                            foreach ($notifications as $notif) {
                                if (!$lastViewed || $notif->created_at->gt($lastViewed)) {
                                    $unreadNotificationsCount++;
                                }
                            }
                        }
                    }
                @endphp

                <!-- Dashboard (Admin only) -->
                @if($user->hasRole('admin'))
                    <flux:navlist.group heading="Overview" class="grid">
                        <flux:navlist.item icon="home" :href="route($dashboardRoute)" :current="request()->routeIs($dashboardRoute)" wire:navigate>Dashboard</flux:navlist.item>
                    </flux:navlist.group>
                @endif

                <!-- Management (Admin only) -->
                @if($user->hasRole('admin'))
                    <flux:navlist.group heading="Management" class="grid">
                        <div x-data="{ open: {{ request()->routeIs('admin.employees', 'admin.students') ? 'true' : 'false' }} }" class="w-full">
                            <flux:navlist.item 
                                icon="users" 
                                as="button"
                                @click.prevent="open = !open" 
                                :current="request()->routeIs('admin.employees', 'admin.students')"
                                class="cursor-pointer w-full text-left"
                            >
                                <div class="flex justify-between items-center w-full">
                                    <span>Manage Users</span>
                                    <flux:icon icon="chevron-down" class="size-4 shrink-0 transition-transform duration-200" ::class="open ? 'rotate-180' : ''" />
                                </div>
                            </flux:navlist.item>

                            <div x-show="open" class="pl-6 flex flex-col gap-1 border-l border-zinc-200 dark:border-zinc-700 ml-3.5 mt-1 mb-2">
                                <flux:navlist.item :href="route('admin.employees')" :current="request()->routeIs('admin.employees')" wire:navigate class="text-xs">Employees</flux:navlist.item>
                                <flux:navlist.item :href="route('admin.students')" :current="request()->routeIs('admin.students')" wire:navigate class="text-xs">Students</flux:navlist.item>
                            </div>
                        </div>
                        <flux:navlist.item icon="book-open" :href="route('admin.subjects')" :current="request()->routeIs('admin.subjects')" wire:navigate>Subjects</flux:navlist.item>
                        <flux:navlist.item icon="academic-cap" :href="route('admin.classes')" :current="request()->routeIs('admin.classes')" wire:navigate>Classes</flux:navlist.item>
                        <flux:navlist.item icon="building-office-2" :href="route('admin.departments')" :current="request()->routeIs('admin.departments')" wire:navigate>Departments</flux:navlist.item>
                        <flux:navlist.item icon="academic-cap" :href="route('admin.programs')" :current="request()->routeIs('admin.programs')" wire:navigate>Programs</flux:navlist.item>
                        <flux:navlist.item icon="cog-6-tooth" :href="route('admin.evaluation-settings')" :current="request()->routeIs('admin.evaluation-settings')" wire:navigate>Evaluation Settings</flux:navlist.item>
                    </flux:navlist.group>
                @endif

                <!-- Evaluations (All roles) -->
                <flux:navlist.group heading="Evaluations" class="grid">
                    @if($user->hasAnyRole(['admin', 'dean', 'program head']))
                        <flux:navlist.item icon="clipboard-document-check" :href="route('manage-evaluations')" :current="request()->routeIs('manage-evaluations')" wire:navigate>Completion Tracking</flux:navlist.item>
                    @endif

                    @if(!$user->hasRole('admin'))
                        <div x-data="{ open: {{ request()->routeIs('student.dashboard', 'faculty.dashboard', 'staff.dashboard', 'dean.dashboard', 'program-head.dashboard') ? 'true' : 'false' }} }" class="w-full">
                            <flux:navlist.item 
                                icon="clipboard-document-check" 
                                as="button"
                                @click.prevent="open = !open" 
                                :current="request()->routeIs('student.dashboard', 'faculty.dashboard', 'staff.dashboard', 'dean.dashboard', 'program-head.dashboard')"
                                class="cursor-pointer w-full text-left"
                            >
                                <div class="flex justify-between items-center w-full">
                                    <span>My Evaluations</span>
                                    <flux:icon icon="chevron-down" class="size-4 shrink-0 transition-transform duration-200" ::class="open ? 'rotate-180' : ''" />
                                </div>
                            </flux:navlist.item>

                            <div x-show="open" class="pl-6 flex flex-col gap-1 border-l border-zinc-200 dark:border-zinc-700 ml-3.5 mt-1 mb-2">
                                @if($user->hasRole('dean'))
                                    <flux:navlist.item :href="route('dean.dashboard', ['tab' => 'self'])" :current="request()->routeIs('dean.dashboard') && request('tab') === 'self'" wire:navigate class="text-xs">Self Evaluation</flux:navlist.item>
                                    <flux:navlist.item :href="route('dean.dashboard', ['tab' => 'program-heads'])" :current="request()->routeIs('dean.dashboard') && request('tab') === 'program-heads'" wire:navigate class="text-xs">Program Head Evaluations</flux:navlist.item>
                                @endif

                                @if($user->hasRole('program head'))
                                    <flux:navlist.item :href="route('program-head.dashboard', ['tab' => 'self'])" :current="request()->routeIs('program-head.dashboard') && request('tab') === 'self'" wire:navigate class="text-xs">Self Evaluation</flux:navlist.item>
                                    <flux:navlist.item :href="route('program-head.dashboard', ['tab' => 'supervisor'])" :current="request()->routeIs('program-head.dashboard') && request('tab') === 'supervisor'" wire:navigate class="text-xs">Supervisor Evaluation</flux:navlist.item>
                                    <flux:navlist.item :href="route('program-head.dashboard', ['tab' => 'faculty'])" :current="request()->routeIs('program-head.dashboard') && request('tab') === 'faculty'" wire:navigate class="text-xs">Faculty Evaluations</flux:navlist.item>
                                @endif

                                @if($user->hasRole('student'))
                                    <flux:navlist.item :href="route('student.dashboard')" :current="request()->routeIs('student.dashboard')" wire:navigate class="text-xs">Evaluate Professors</flux:navlist.item>
                                @endif

                                @if($user->hasRole('faculty'))
                                    <flux:navlist.item :href="route('faculty.dashboard', ['tab' => 'self'])" :current="request()->routeIs('faculty.dashboard') && request('tab') === 'self'" wire:navigate class="text-xs">Self Evaluation</flux:navlist.item>
                                    <flux:navlist.item :href="route('faculty.dashboard', ['tab' => 'peer'])" :current="request()->routeIs('faculty.dashboard') && request('tab') === 'peer'" wire:navigate class="text-xs">Peer Evaluation</flux:navlist.item>
                                    <flux:navlist.item :href="route('faculty.dashboard', ['tab' => 'supervisor'])" :current="request()->routeIs('faculty.dashboard') && request('tab') === 'supervisor'" wire:navigate class="text-xs">Supervisor Evaluation</flux:navlist.item>
                                @endif

                                @if($user->hasRole('staff'))
                                    <flux:navlist.item :href="route('staff.dashboard', ['tab' => 'self'])" :current="request()->routeIs('staff.dashboard') && request('tab') === 'self'" wire:navigate class="text-xs">Self Evaluation</flux:navlist.item>
                                    <flux:navlist.item :href="route('staff.dashboard', ['tab' => 'supervisor'])" :current="request()->routeIs('staff.dashboard') && request('tab') === 'supervisor'" wire:navigate class="text-xs">Supervisor Evaluation</flux:navlist.item>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($user->hasRole('admin'))
                        <flux:navlist.item icon="clipboard-document-list" :href="route('admin.questions')" :current="request()->routeIs('admin.questions')" wire:navigate>Evaluation Questions</flux:navlist.item>
                    @endif

                    @if($user->hasAnyRole(['admin', 'dean']))
                        <flux:navlist.item icon="check-badge" :href="route('evaluation-results')" :current="request()->routeIs('evaluation-results')" wire:navigate>Results</flux:navlist.item>
                    @endif

                    @if($user->hasAnyRole(['admin', 'dean', 'program head', 'faculty']))
                        <flux:navlist.item icon="trophy" :href="route('rankings')" :current="request()->routeIs('rankings')" wire:navigate>Rankings</flux:navlist.item>
                    @endif
                </flux:navlist.group>

                <!-- Analytics & Reports (Admin, Dean, Program Head) -->
                @if($user->hasAnyRole(['admin', 'dean', 'program head']))
                    <flux:navlist.group heading="Analytics & Reports" class="grid">
                        @if($user->hasRole('admin'))
                            <flux:navlist.item icon="chart-bar" :href="route('analytics')" :current="request()->routeIs('analytics')" wire:navigate>Analytics</flux:navlist.item>
                            <flux:navlist.item icon="beaker" :href="route('admin.ai')" :current="request()->routeIs('admin.ai')" wire:navigate>AI Pipeline</flux:navlist.item>
                        @endif
                        
                        <flux:navlist.item icon="document-chart-bar" :href="route('reports')" :current="request()->routeIs('reports')" wire:navigate>Reports</flux:navlist.item>
                    </flux:navlist.group>
                @endif

                <!-- System (All roles) -->
                <flux:navlist.group heading="System" class="grid">
                    <flux:navlist.item icon="bell" :href="route('notifications')" :current="request()->routeIs('notifications')" wire:navigate>
                        Notifications
                        @if(isset($unreadNotificationsCount) && $unreadNotificationsCount > 0)
                            <flux:badge size="sm" color="amber" class="ml-auto flex items-center justify-center font-bold">{{ $unreadNotificationsCount }}</flux:badge>
                        @endif
                    </flux:navlist.item>
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

        <flux:toast />
        @fluxScripts
    </body>
</html>
