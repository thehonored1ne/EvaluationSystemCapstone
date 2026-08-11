<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <style>
            /* Active Sidebar Item Dark Red (#800000) Styling */
            [data-flux-sidebar] [data-flux-navlist-item][data-current] {
                background-color: #800000 !important;
                color: #ffffff !important;
                border-color: #800000 !important;
            }
            [data-flux-sidebar] [data-flux-navlist-item][data-current] svg,
            [data-flux-sidebar] [data-flux-navlist-item][data-current] span,
            [data-flux-sidebar] [data-flux-navlist-item][data-current] div {
                color: #ffffff !important;
            }
            [data-flux-sidebar] [data-flux-navlist-item][data-current]:hover {
                background-color: #990000 !important;
                color: #ffffff !important;
            }

            /* Mini Icon-Only Collapsed Sidebar Styles */
            body.sidebar-is-collapsed [data-flux-sidebar] {
                width: 4.25rem !important;
                min-width: 4.25rem !important;
                padding-left: 0.375rem !important;
                padding-right: 0.375rem !important;
                align-items: center !important;
            }
            body.sidebar-is-collapsed [data-flux-sidebar] [data-flux-navlist-group-heading],
            body.sidebar-is-collapsed [data-flux-sidebar] .px-1.py-2,
            body.sidebar-is-collapsed [data-flux-sidebar] .px-3.py-2,
            body.sidebar-is-collapsed [data-flux-sidebar] .text-zinc-400,
            body.sidebar-is-collapsed [data-flux-sidebar] [data-content],
            body.sidebar-is-collapsed [data-flux-sidebar] .sidebar-text,
            body.sidebar-is-collapsed [data-flux-sidebar] svg.transition-transform,
            body.sidebar-is-collapsed [data-flux-sidebar] [data-flux-badge],
            body.sidebar-is-collapsed [data-flux-sidebar] .pl-6 {
                display: none !important;
            }
            body.sidebar-is-collapsed [data-flux-sidebar] [data-flux-navlist-item] {
                justify-content: center !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
                width: 2.75rem !important;
                height: 2.5rem !important;
                margin-left: auto !important;
                margin-right: auto !important;
            }
            body.sidebar-is-collapsed [data-flux-sidebar] [data-flux-profile] > span,
            body.sidebar-is-collapsed [data-flux-sidebar] [data-flux-profile] > div:nth-child(2),
            body.sidebar-is-collapsed [data-flux-sidebar] [data-flux-profile] > div.ms-auto,
            body.sidebar-is-collapsed [data-flux-sidebar] [data-flux-profile] svg {
                display: none !important;
            }
            body.sidebar-is-collapsed [data-flux-sidebar] [data-flux-profile] {
                justify-content: center !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
                width: 2.75rem !important;
                margin-left: auto !important;
                margin-right: auto !important;
            }
        </style>
    </head>
    <body 
        x-data="{ 
            sidebarCollapsed: localStorage.getItem('admin_sidebar_collapsed') === 'true',
            toggle() {
                this.sidebarCollapsed = !this.sidebarCollapsed;
                localStorage.setItem('admin_sidebar_collapsed', this.sidebarCollapsed);
            }
        }" 
        @toggle-sidebar.window="toggle()" 
        @flux-sidebar-toggle.window="toggle()"
        :class="sidebarCollapsed ? 'sidebar-is-collapsed' : ''"
        class="min-h-screen bg-white dark:bg-zinc-800"
    >
        <div class="flex min-h-screen w-full">
            <flux:sidebar 
                sticky 
                stashable 
                class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 transition-all duration-200 shrink-0"
            >
                <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

                <!-- Logo Section: Full logo when expanded, Dark Red GRC Favicon Icon when collapsed -->
                <a href="{{ route('dashboard') }}" class="flex items-center justify-center px-2 py-1 mb-2 shrink-0" wire:navigate>
                    <div x-show="!sidebarCollapsed">
                        <x-app-logo></x-app-logo>
                    </div>
                    <div x-show="sidebarCollapsed" x-cloak class="flex items-center justify-center p-1.5 rounded-xl bg-red-950/10 dark:bg-red-950/30 border border-red-900/20 text-[#800000] dark:text-red-400">
                        <x-app-logo-icon class="size-7 text-[#800000] dark:text-red-400 fill-current"></x-app-logo-icon>
                    </div>
                </a>

                @php
                    $activeSemester = \App\Models\Semester::where('is_active', true)->with('academicYear')->first();
                    $shortSemName = $activeSemester ? str_replace(['Semester', 'semester'], ['Sem', 'Sem'], $activeSemester->name) : '';
                @endphp

                <!-- Active Term Indicator -->
                <div x-show="!sidebarCollapsed" class="px-2 mb-3">
                    <div class="flex flex-col items-center justify-center px-2.5 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-800 dark:text-amber-300 text-xs font-medium tracking-wide text-center">
                        <span class="text-[10px] uppercase font-bold text-amber-600 dark:text-amber-400 leading-none">Active Term</span>
                        <span class="truncate font-semibold text-xs mt-0.5">
                            {{ $activeSemester ? $activeSemester->academicYear?->name . ' • ' . $shortSemName : 'No Active Term' }}
                        </span>
                    </div>
                </div>

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
                                    if (!$lastViewed || (isset($notif->created_at) && $notif->created_at->gt($lastViewed))) {
                                        $unreadNotificationsCount++;
                                    }
                                }
                            }
                        }
                    @endphp

                    <!-- Dashboard (Admin only) -->
                    @if($user->hasRole('admin'))
                        <flux:navlist.group heading="Overview" class="grid">
                            <flux:tooltip content="Dashboard" position="right">
                                <flux:navlist.item icon="home" :href="route($dashboardRoute)" :current="request()->routeIs($dashboardRoute)" wire:navigate title="Dashboard">Dashboard</flux:navlist.item>
                            </flux:tooltip>
                        </flux:navlist.group>
                    @endif

                    <!-- Management (Admin only) -->
                    @if($user->hasRole('admin'))
                        <flux:navlist.group heading="Management" class="grid">
                            <div x-data="{ open: {{ request()->routeIs('admin.employees', 'admin.students') ? 'true' : 'false' }} }" class="w-full">
                                <flux:tooltip content="Manage Users" position="right">
                                    <flux:navlist.item 
                                        icon="users" 
                                        as="button"
                                        @click.prevent="open = !open" 
                                        :current="request()->routeIs('admin.employees', 'admin.students')"
                                        class="cursor-pointer w-full text-left"
                                        title="Manage Users"
                                    >
                                        <div class="flex justify-between items-center w-full">
                                            <span>Manage Users</span>
                                            <flux:icon icon="chevron-down" class="size-4 shrink-0 transition-transform duration-200" ::class="open ? 'rotate-180' : ''" />
                                        </div>
                                    </flux:navlist.item>
                                </flux:tooltip>

                                <div x-show="open && !sidebarCollapsed" class="pl-6 flex flex-col gap-1 border-l border-zinc-200 dark:border-zinc-700 ml-3.5 mt-1 mb-2">
                                    <flux:tooltip content="Employees" position="right">
                                        <flux:navlist.item :href="route('admin.employees')" :current="request()->routeIs('admin.employees')" wire:navigate class="text-xs" title="Employees">Employees</flux:navlist.item>
                                    </flux:tooltip>
                                    <flux:tooltip content="Students" position="right">
                                        <flux:navlist.item :href="route('admin.students')" :current="request()->routeIs('admin.students')" wire:navigate class="text-xs" title="Students">Students</flux:navlist.item>
                                    </flux:tooltip>
                                </div>
                            </div>
                            <flux:tooltip content="Subjects" position="right">
                                <flux:navlist.item icon="book-open" :href="route('admin.subjects')" :current="request()->routeIs('admin.subjects')" wire:navigate title="Subjects">Subjects</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Classes" position="right">
                                <flux:navlist.item icon="academic-cap" :href="route('admin.classes')" :current="request()->routeIs('admin.classes')" wire:navigate title="Classes">Classes</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Departments" position="right">
                                <flux:navlist.item icon="building-office-2" :href="route('admin.departments')" :current="request()->routeIs('admin.departments')" wire:navigate title="Departments">Departments</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Programs" position="right">
                                <flux:navlist.item icon="academic-cap" :href="route('admin.programs')" :current="request()->routeIs('admin.programs')" wire:navigate title="Programs">Programs</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Evaluation Settings" position="right">
                                <flux:navlist.item icon="cog-6-tooth" :href="route('admin.evaluation-settings')" :current="request()->routeIs('admin.evaluation-settings')" wire:navigate title="Evaluation Settings">Evaluation Settings</flux:navlist.item>
                            </flux:tooltip>
                        </flux:navlist.group>
                    @endif

                    <!-- Evaluations (All roles) -->
                    <flux:navlist.group heading="Evaluations" class="grid">
                        @if($user->hasAnyRole(['admin', 'dean', 'program head']))
                            <flux:tooltip content="Completion Tracking" position="right">
                                <flux:navlist.item icon="clipboard-document-check" :href="route('manage-evaluations')" :current="request()->routeIs('manage-evaluations')" wire:navigate title="Completion Tracking">Completion Tracking</flux:navlist.item>
                            </flux:tooltip>
                        @endif

                        @if(!$user->hasRole('admin'))
                            <div x-data="{ open: {{ request()->routeIs('student.dashboard', 'faculty.dashboard', 'staff.dashboard', 'dean.dashboard', 'program-head.dashboard') ? 'true' : 'false' }} }" class="w-full">
                                <flux:tooltip content="My Evaluations" position="right">
                                    <flux:navlist.item 
                                        icon="clipboard-document-check" 
                                        as="button"
                                        @click.prevent="open = !open" 
                                        :current="request()->routeIs('student.dashboard', 'faculty.dashboard', 'staff.dashboard', 'dean.dashboard', 'program-head.dashboard')"
                                        class="cursor-pointer w-full text-left"
                                        title="My Evaluations"
                                    >
                                        <div class="flex justify-between items-center w-full">
                                            <span>My Evaluations</span>
                                            <flux:icon icon="chevron-down" class="size-4 shrink-0 transition-transform duration-200" ::class="open ? 'rotate-180' : ''" />
                                        </div>
                                    </flux:navlist.item>
                                </flux:tooltip>

                                <div x-show="open && !sidebarCollapsed" class="pl-6 flex flex-col gap-1 border-l border-zinc-200 dark:border-zinc-700 ml-3.5 mt-1 mb-2">
                                    @if($user->hasRole('dean'))
                                        <flux:tooltip content="Self Evaluation" position="right">
                                            <flux:navlist.item :href="route('dean.dashboard', ['tab' => 'self'])" :current="request()->routeIs('dean.dashboard') && request('tab') === 'self'" wire:navigate class="text-xs" title="Self Evaluation">Self Evaluation</flux:navlist.item>
                                        </flux:tooltip>
                                        <flux:tooltip content="Program Head Evaluations" position="right">
                                            <flux:navlist.item :href="route('dean.dashboard', ['tab' => 'program-heads'])" :current="request()->routeIs('dean.dashboard') && request('tab') === 'program-heads'" wire:navigate class="text-xs" title="Program Head Evaluations">Program Head Evaluations</flux:navlist.item>
                                        </flux:tooltip>
                                    @endif

                                    @if($user->hasRole('program head'))
                                        <flux:tooltip content="Self Evaluation" position="right">
                                            <flux:navlist.item :href="route('program-head.dashboard', ['tab' => 'self'])" :current="request()->routeIs('program-head.dashboard') && request('tab') === 'self'" wire:navigate class="text-xs" title="Self Evaluation">Self Evaluation</flux:navlist.item>
                                        </flux:tooltip>
                                        <flux:tooltip content="Supervisor Evaluation" position="right">
                                            <flux:navlist.item :href="route('program-head.dashboard', ['tab' => 'supervisor'])" :current="request()->routeIs('program-head.dashboard') && request('tab') === 'supervisor'" wire:navigate class="text-xs" title="Supervisor Evaluation">Supervisor Evaluation</flux:navlist.item>
                                        </flux:tooltip>
                                        <flux:tooltip content="Faculty Evaluations" position="right">
                                            <flux:navlist.item :href="route('program-head.dashboard', ['tab' => 'faculty'])" :current="request()->routeIs('program-head.dashboard') && request('tab') === 'faculty'" wire:navigate class="text-xs" title="Faculty Evaluations">Faculty Evaluations</flux:navlist.item>
                                        </flux:tooltip>
                                    @endif

                                    @if($user->hasRole('student'))
                                        <flux:tooltip content="Evaluate Professors" position="right">
                                            <flux:navlist.item :href="route('student.dashboard')" :current="request()->routeIs('student.dashboard')" wire:navigate class="text-xs" title="Evaluate Professors">Evaluate Professors</flux:navlist.item>
                                        </flux:tooltip>
                                    @endif

                                    @if($user->hasRole('faculty'))
                                        <flux:tooltip content="Self Evaluation" position="right">
                                            <flux:navlist.item :href="route('faculty.dashboard', ['tab' => 'self'])" :current="request()->routeIs('faculty.dashboard') && request('tab') === 'self'" wire:navigate class="text-xs" title="Self Evaluation">Self Evaluation</flux:navlist.item>
                                        </flux:tooltip>
                                        <flux:tooltip content="Peer Evaluation" position="right">
                                            <flux:navlist.item :href="route('faculty.dashboard', ['tab' => 'peer'])" :current="request()->routeIs('faculty.dashboard') && request('tab') === 'peer'" wire:navigate class="text-xs" title="Peer Evaluation">Peer Evaluation</flux:navlist.item>
                                        </flux:tooltip>
                                        <flux:tooltip content="Supervisor Evaluation" position="right">
                                            <flux:navlist.item :href="route('faculty.dashboard', ['tab' => 'supervisor'])" :current="request()->routeIs('faculty.dashboard') && request('tab') === 'supervisor'" wire:navigate class="text-xs" title="Supervisor Evaluation">Supervisor Evaluation</flux:navlist.item>
                                        </flux:tooltip>
                                    @endif

                                    @if($user->hasRole('staff'))
                                        <flux:tooltip content="Self Evaluation" position="right">
                                            <flux:navlist.item :href="route('staff.dashboard', ['tab' => 'self'])" :current="request()->routeIs('staff.dashboard') && request('tab') === 'self'" wire:navigate class="text-xs" title="Self Evaluation">Self Evaluation</flux:navlist.item>
                                        </flux:tooltip>
                                        <flux:tooltip content="Supervisor Evaluation" position="right">
                                            <flux:navlist.item :href="route('staff.dashboard', ['tab' => 'supervisor'])" :current="request()->routeIs('staff.dashboard') && request('tab') === 'supervisor'" wire:navigate class="text-xs" title="Supervisor Evaluation">Supervisor Evaluation</flux:navlist.item>
                                        </flux:tooltip>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if($user->hasRole('admin'))
                            <flux:tooltip content="Evaluation Questions" position="right">
                                <flux:navlist.item icon="clipboard-document-list" :href="route('admin.questions')" :current="request()->routeIs('admin.questions')" wire:navigate title="Evaluation Questions">Evaluation Questions</flux:navlist.item>
                            </flux:tooltip>
                        @endif

                        @if($user->hasAnyRole(['admin', 'dean']))
                            <flux:tooltip content="Results" position="right">
                                <flux:navlist.item icon="check-badge" :href="route('evaluation-results')" :current="request()->routeIs('evaluation-results')" wire:navigate title="Results">Results</flux:navlist.item>
                            </flux:tooltip>
                        @endif

                        @if($user->hasAnyRole(['admin', 'dean', 'program head', 'faculty']))
                            <flux:tooltip content="Rankings" position="right">
                                <flux:navlist.item icon="trophy" :href="route('rankings')" :current="request()->routeIs('rankings')" wire:navigate title="Rankings">Rankings</flux:navlist.item>
                            </flux:tooltip>
                        @endif
                    </flux:navlist.group>

                    <!-- Analytics & Reports (Admin, Dean, Program Head) -->
                    @if($user->hasAnyRole(['admin', 'dean', 'program head']))
                        <flux:navlist.group heading="Analytics & Reports" class="grid">
                            @if($user->hasRole('admin'))
                                <flux:tooltip content="Analytics" position="right">
                                    <flux:navlist.item icon="chart-bar" :href="route('analytics')" :current="request()->routeIs('analytics')" wire:navigate title="Analytics">Analytics</flux:navlist.item>
                                </flux:tooltip>
                                <flux:tooltip content="AI Pipeline" position="right">
                                    <flux:navlist.item icon="beaker" :href="route('admin.ai')" :current="request()->routeIs('admin.ai')" wire:navigate title="AI Pipeline">AI Pipeline</flux:navlist.item>
                                </flux:tooltip>
                            @endif
                            
                            <flux:tooltip content="Reports" position="right">
                                <flux:navlist.item icon="document-chart-bar" :href="route('reports')" :current="request()->routeIs('reports')" wire:navigate title="Reports">Reports</flux:navlist.item>
                            </flux:tooltip>
                        </flux:navlist.group>
                    @endif

                    <!-- System (All roles) -->
                    <flux:navlist.group heading="System" class="grid">
                        <flux:tooltip content="Notifications" position="right">
                            <flux:navlist.item icon="bell" :href="route('notifications')" :current="request()->routeIs('notifications')" wire:navigate title="Notifications">
                                <span class="sidebar-text">Notifications</span>
                                @if(isset($unreadNotificationsCount) && $unreadNotificationsCount > 0)
                                    <flux:badge size="sm" color="amber" class="ml-auto flex items-center justify-center font-bold">{{ $unreadNotificationsCount }}</flux:badge>
                                @endif
                            </flux:navlist.item>
                        </flux:tooltip>
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

            <!-- Main Content Container with Navbar, Page Slot, and Full-Width Footer -->
            <div class="flex-1 flex flex-col min-h-screen min-w-0">
                @if(auth()->check())
                    <x-admin.navbar />
                @endif

                <div class="flex-1">
                    {{ $slot }}
                </div>

                @if(auth()->check())
                    <x-admin.footer />
                @endif
            </div>
        </div>

        <flux:toast />
        @fluxScripts
    </body>
</html>
