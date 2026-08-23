<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <style>
            /* Active Sidebar Item Dark Red (#9b0000) Styling */
            [data-flux-sidebar] [data-flux-navlist-item][data-current] {
                background-color: #9b0000 !important;
                color: #ffffff !important;
                border-color: #9b0000 !important;
            }
            [data-flux-sidebar] [data-flux-navlist-item][data-current] svg,
            [data-flux-sidebar] [data-flux-navlist-item][data-current] span,
            [data-flux-sidebar] [data-flux-navlist-item][data-current] div {
                color: #ffffff !important;
            }
            [data-flux-sidebar] [data-flux-navlist-item][data-current]:hover {
                background-color: #7a0000 !important;
                color: #ffffff !important;
            }

            /* Sidebar Group Headings High-Contrast Accessibility */
            [data-flux-sidebar] [data-flux-navlist-group-heading],
            [data-flux-sidebar] [data-flux-navlist-group-heading] div {
                color: #52525b !important;
                font-weight: 700 !important;
            }
            .dark [data-flux-sidebar] [data-flux-navlist-group-heading],
            .dark [data-flux-sidebar] [data-flux-navlist-group-heading] div {
                color: #d4d4d8 !important;
                font-weight: 700 !important;
            }

            /* Mini Icon-Only Collapsed Sidebar Styles (Desktop Only) */
            @media (min-width: 1024px) {
                [data-flux-sidebar] {
                    overflow-x: hidden !important;
                }

                [data-flux-sidebar] [data-flux-navlist-item] {
                    white-space: nowrap !important;
                }

                /* Smooth transitions only when actively clicking toggle */
                body.sidebar-animating [data-flux-sidebar],
                body.sidebar-animating [data-flux-sidebar] [data-flux-navlist-item],
                body.sidebar-animating [data-flux-sidebar] [data-flux-navlist-group-heading],
                body.sidebar-animating [data-flux-sidebar] [data-content],
                body.sidebar-animating [data-flux-sidebar] .sidebar-text,
                body.sidebar-animating [data-flux-sidebar] [data-flux-badge] {
                    transition: width 200ms cubic-bezier(0.4, 0, 0.2, 1),
                                min-width 200ms cubic-bezier(0.4, 0, 0.2, 1),
                                padding 200ms ease,
                                margin 200ms ease,
                                opacity 150ms ease !important;
                }

                html.sidebar-is-collapsed [data-flux-sidebar],
                body.sidebar-is-collapsed [data-flux-sidebar] {
                    width: 4.25rem !important;
                    min-width: 4.25rem !important;
                    padding-left: 0.375rem !important;
                    padding-right: 0.375rem !important;
                    align-items: center !important;
                }

                html.sidebar-is-collapsed [data-flux-sidebar] [data-flux-navlist-group-heading],
                html.sidebar-is-collapsed [data-flux-sidebar] .text-zinc-400,
                html.sidebar-is-collapsed [data-flux-sidebar] [data-content],
                html.sidebar-is-collapsed [data-flux-sidebar] .sidebar-text,
                html.sidebar-is-collapsed [data-flux-sidebar] svg.transition-transform,
                html.sidebar-is-collapsed [data-flux-sidebar] [data-flux-badge],
                html.sidebar-is-collapsed [data-flux-sidebar] .sidebar-sublist,
                html.sidebar-is-collapsed [data-flux-sidebar] .sidebar-active-term,
                html.sidebar-is-collapsed [data-flux-sidebar] .sidebar-big-logo,
                body.sidebar-is-collapsed [data-flux-sidebar] [data-flux-navlist-group-heading],
                body.sidebar-is-collapsed [data-flux-sidebar] .text-zinc-400,
                body.sidebar-is-collapsed [data-flux-sidebar] [data-content],
                body.sidebar-is-collapsed [data-flux-sidebar] .sidebar-text,
                body.sidebar-is-collapsed [data-flux-sidebar] svg.transition-transform,
                body.sidebar-is-collapsed [data-flux-sidebar] [data-flux-badge],
                body.sidebar-is-collapsed [data-flux-sidebar] .sidebar-sublist,
                body.sidebar-is-collapsed [data-flux-sidebar] .sidebar-active-term,
                body.sidebar-is-collapsed [data-flux-sidebar] .sidebar-big-logo {
                    opacity: 0 !important;
                    display: none !important;
                    pointer-events: none !important;
                }

                html.sidebar-is-collapsed [data-flux-sidebar] .sidebar-small-logo,
                body.sidebar-is-collapsed [data-flux-sidebar] .sidebar-small-logo {
                    display: flex !important;
                }

                html.sidebar-is-collapsed [data-flux-sidebar] [data-flux-navlist-item],
                body.sidebar-is-collapsed [data-flux-sidebar] [data-flux-navlist-item] {
                    justify-content: center !important;
                    padding-left: 0 !important;
                    padding-right: 0 !important;
                    width: 2.75rem !important;
                    height: 2.5rem !important;
                    margin-left: auto !important;
                    margin-right: auto !important;
                }

                /* Suppress floating tooltip popups ONLY (without disabling pointer-events on the buttons/links) */
                body:not(.sidebar-is-collapsed) ui-tooltip > [popover],
                html:not(.sidebar-is-collapsed) body:not(.sidebar-is-collapsed) [data-flux-tooltip-popup],
                html:not(.sidebar-is-collapsed) body:not(.sidebar-is-collapsed) ui-tooltip-popup {
                    display: none !important;
                    visibility: hidden !important;
                    opacity: 0 !important;
                }
            }
        </style>
        <script>
            (function() {
                if (window.innerWidth >= 1024 && localStorage.getItem('admin_sidebar_collapsed') === 'true') {
                    document.documentElement.classList.add('sidebar-is-collapsed');
                }
            })();
            document.addEventListener('livewire:navigated', function () {
                if (window.innerWidth >= 1024 && localStorage.getItem('admin_sidebar_collapsed') === 'true') {
                    document.documentElement.classList.add('sidebar-is-collapsed');
                } else {
                    document.documentElement.classList.remove('sidebar-is-collapsed');
                }
            });
        </script>
    </head>
    <body 
        x-data="{ 
            sidebarCollapsed: window.innerWidth >= 1024 && localStorage.getItem('admin_sidebar_collapsed') === 'true',
            toggle() {
                if (window.innerWidth >= 1024) {
                    document.body.classList.add('sidebar-animating');
                    this.sidebarCollapsed = !this.sidebarCollapsed;
                    localStorage.setItem('admin_sidebar_collapsed', this.sidebarCollapsed);
                    if (this.sidebarCollapsed) {
                        document.documentElement.classList.add('sidebar-is-collapsed');
                    } else {
                        document.documentElement.classList.remove('sidebar-is-collapsed');
                    }
                    setTimeout(() => {
                        document.body.classList.remove('sidebar-animating');
                    }, 250);
                }
            }
        }" 
        @toggle-sidebar.window="toggle()" 
        :class="sidebarCollapsed ? 'sidebar-is-collapsed' : ''"
        class="min-h-screen bg-[#fafafa] dark:bg-[#252525]"
    >
        <div class="flex min-h-screen w-full">
            <flux:sidebar 
                sticky 
                stashable 
                class="border-r border-zinc-200 dark:border-zinc-800 bg-white dark:bg-[#171717] shrink-0 print:hidden"
            >
                <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

                <!-- Logo Section: Full logo on mobile and desktop-expanded; Small Icon ONLY when collapsed on desktop (lg:) -->
                <a href="{{ route('dashboard') }}" class="flex items-center justify-center w-full px-1 py-2 mb-2 shrink-0" aria-label="Dashboard Home" wire:navigate>
                    <!-- Big Logo: Shown by default, hidden when html.sidebar-is-collapsed -->
                    <div class="sidebar-big-logo w-full flex items-center justify-center">
                        <x-app-logo class="w-full"></x-app-logo>
                    </div>
                    <!-- Small Logo Icon: Desktop only, shown when html.sidebar-is-collapsed -->
                    <div class="sidebar-small-logo hidden items-center justify-center p-1.5 rounded-xl bg-red-950/10 dark:bg-red-950/30 border border-red-900/20 text-[#9b0000] dark:text-[#f89696]">
                        <x-app-logo-icon class="size-7 text-[#9b0000] dark:text-[#f89696] fill-current"></x-app-logo-icon>
                    </div>
                </a>

                @php
                    $activeSemester = \App\Models\Semester::getActive();
                    $shortSemName = $activeSemester ? str_replace(['Semester', 'semester'], ['Sem', 'Sem'], $activeSemester->name) : '';
                @endphp

                <!-- Active Term Indicator -->
                <div class="sidebar-active-term px-2 mb-3">
                    <div class="flex flex-col items-center justify-center px-2.5 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-800 dark:text-amber-300 text-xs font-medium tracking-wide text-center">
                        <span class="text-[10px] uppercase font-bold text-amber-800 dark:text-amber-300 leading-none">Active Term</span>
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
                            $user->hasRole('department head') => 'department-head.dashboard',
                            $user->hasRole('program head') => 'program-head.dashboard',
                            $user->hasRole('faculty') => 'faculty.dashboard',
                            $user->hasRole('student') => 'student.dashboard',
                            $user->hasRole('staff') => 'staff.dashboard',
                            default => 'dashboard',
                        };
                    @endphp

                    <!-- Dashboard (Admin only) -->
                    @if($user->hasRole('admin'))
                        <flux:navlist.group heading="Overview" class="grid">
                            <flux:tooltip content="Dashboard" position="right">
                                <flux:navlist.item icon="home" :href="route($dashboardRoute)" :current="request()->routeIs($dashboardRoute)" aria-label="Dashboard" wire:navigate>Dashboard</flux:navlist.item>
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
                                        @click.prevent="if (sidebarCollapsed) { toggle(); open = true; } else { open = !open; }" 
                                        :current="request()->routeIs('admin.employees', 'admin.students')"
                                        aria-label="Manage Users"
                                        class="cursor-pointer w-full text-left"
                                    >
                                        <div class="flex justify-between items-center w-full">
                                            <span>Manage Users</span>
                                            <flux:icon icon="chevron-down" class="size-4 shrink-0 transition-transform duration-200" ::class="open ? 'rotate-180' : ''" />
                                        </div>
                                    </flux:navlist.item>
                                </flux:tooltip>

                                <div x-show="open" class="sidebar-sublist pl-6 flex flex-col gap-1 border-l border-zinc-200 dark:border-zinc-700 ml-3.5 mt-1 mb-2">
                                    <flux:navlist.item :href="route('admin.employees')" :current="request()->routeIs('admin.employees')" aria-label="Employees" wire:navigate class="text-xs">Employees</flux:navlist.item>
                                    <flux:navlist.item :href="route('admin.students')" :current="request()->routeIs('admin.students')" aria-label="Students" wire:navigate class="text-xs">Students</flux:navlist.item>
                                </div>
                            </div>
                            <flux:tooltip content="Subjects" position="right">
                                <flux:navlist.item icon="book-open" :href="route('admin.subjects')" :current="request()->routeIs('admin.subjects')" aria-label="Subjects" wire:navigate>Subjects</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Classes" position="right">
                                <flux:navlist.item icon="academic-cap" :href="route('admin.classes')" :current="request()->routeIs('admin.classes')" aria-label="Classes" wire:navigate>Classes</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Departments" position="right">
                                <flux:navlist.item icon="building-office-2" :href="route('admin.departments')" :current="request()->routeIs('admin.departments')" aria-label="Departments" wire:navigate>Departments</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Programs" position="right">
                                <flux:navlist.item icon="academic-cap" :href="route('admin.programs')" :current="request()->routeIs('admin.programs')" aria-label="Programs" wire:navigate>Programs</flux:navlist.item>
                            </flux:tooltip>
                            <flux:tooltip content="Evaluation Settings" position="right">
                                <flux:navlist.item icon="cog-6-tooth" :href="route('admin.evaluation-settings')" :current="request()->routeIs('admin.evaluation-settings')" aria-label="Evaluation Settings" wire:navigate>Evaluation Settings</flux:navlist.item>
                            </flux:tooltip>
                        </flux:navlist.group>
                    @endif

                    <!-- Evaluations (All roles) -->
                    <flux:navlist.group heading="Evaluations" class="grid">
                        @if($user->hasAnyRole(['admin', 'dean', 'program head']))
                            <flux:tooltip content="Completion Tracking" position="right">
                                <flux:navlist.item icon="clipboard-document-check" :href="route('manage-evaluations')" :current="request()->routeIs('manage-evaluations')" aria-label="Completion Tracking" wire:navigate>Completion Tracking</flux:navlist.item>
                            </flux:tooltip>
                        @endif

                        @if(!$user->hasRole('admin'))
                            <div x-data="{ open: {{ request()->routeIs('student.dashboard', 'faculty.dashboard', 'staff.dashboard', 'dean.dashboard', 'department-head.dashboard', 'program-head.dashboard') ? 'true' : 'false' }} }" class="w-full">
                                <flux:tooltip content="My Evaluations" position="right">
                                    <flux:navlist.item 
                                        icon="clipboard-document-check" 
                                        as="button"
                                        @click.prevent="if (sidebarCollapsed) { toggle(); open = true; } else { open = !open; }" 
                                        :current="request()->routeIs('student.dashboard', 'faculty.dashboard', 'staff.dashboard', 'dean.dashboard', 'department-head.dashboard', 'program-head.dashboard')"
                                        aria-label="My Evaluations"
                                        class="cursor-pointer w-full text-left"
                                    >
                                        <div class="flex justify-between items-center w-full">
                                            <span>My Evaluations</span>
                                            <flux:icon icon="chevron-down" class="size-4 shrink-0 transition-transform duration-200" ::class="open ? 'rotate-180' : ''" />
                                        </div>
                                    </flux:navlist.item>
                                </flux:tooltip>

                                <div x-show="open" class="sidebar-sublist pl-6 flex flex-col gap-1 border-l border-zinc-200 dark:border-zinc-700 ml-3.5 mt-1 mb-2">
                                    @if($user->hasRole('dean'))
                                        <flux:navlist.item :href="route('dean.dashboard', ['tab' => 'self'])" :current="request()->routeIs('dean.dashboard') && (request('tab') === 'self' || !request('tab'))" aria-label="Self Evaluation" wire:navigate class="text-xs">Self Evaluation</flux:navlist.item>
                                        <flux:navlist.item :href="route('dean.dashboard', ['tab' => 'faculty'])" :current="request()->routeIs('dean.dashboard') && request('tab') === 'faculty'" aria-label="Faculty Evaluations" wire:navigate class="text-xs">Faculty Evaluations</flux:navlist.item>
                                        <flux:navlist.item :href="route('dean.dashboard', ['tab' => 'program-heads'])" :current="request()->routeIs('dean.dashboard') && request('tab') === 'program-heads'" aria-label="Program Head Evaluations" wire:navigate class="text-xs">Program Head Evaluations</flux:navlist.item>
                                    @endif

                                    @if($user->hasRole('department head'))
                                        <flux:navlist.item :href="route('department-head.dashboard', ['tab' => 'self'])" :current="request()->routeIs('department-head.dashboard') && request('tab') === 'self'" aria-label="Self Evaluation" wire:navigate class="text-xs">Self Evaluation</flux:navlist.item>
                                        <flux:navlist.item :href="route('department-head.dashboard', ['tab' => 'staff'])" :current="request()->routeIs('department-head.dashboard') && request('tab') === 'staff'" aria-label="Staff Evaluation" wire:navigate class="text-xs">Staff Evaluation</flux:navlist.item>
                                        <flux:navlist.item :href="route('department-head.dashboard', ['tab' => 'dean'])" :current="request()->routeIs('department-head.dashboard') && request('tab') === 'dean'" aria-label="Dean Evaluation" wire:navigate class="text-xs">Dean Evaluation</flux:navlist.item>
                                    @endif

                                    @if($user->hasRole('program head'))
                                        <flux:navlist.item :href="route('program-head.dashboard', ['tab' => 'self'])" :current="request()->routeIs('program-head.dashboard') && request('tab') === 'self'" aria-label="Self Evaluation" wire:navigate class="text-xs">Self Evaluation</flux:navlist.item>
                                        <flux:navlist.item :href="route('program-head.dashboard', ['tab' => 'supervisor'])" :current="request()->routeIs('program-head.dashboard') && request('tab') === 'supervisor'" aria-label="Supervisor Evaluation" wire:navigate class="text-xs">Supervisor Evaluation</flux:navlist.item>
                                        <flux:navlist.item :href="route('program-head.dashboard', ['tab' => 'faculty'])" :current="request()->routeIs('program-head.dashboard') && request('tab') === 'faculty'" aria-label="Faculty Evaluations" wire:navigate class="text-xs">Faculty Evaluations</flux:navlist.item>
                                    @endif

                                    @if($user->hasRole('student'))
                                        <flux:navlist.item :href="route('student.dashboard')" :current="request()->routeIs('student.dashboard')" aria-label="Evaluate Professors" wire:navigate class="text-xs">Evaluate Professors</flux:navlist.item>
                                    @endif

                                    @if($user->hasRole('faculty'))
                                        <flux:navlist.item :href="route('faculty.dashboard', ['tab' => 'self'])" :current="request()->routeIs('faculty.dashboard') && request('tab') === 'self'" aria-label="Self Evaluation" wire:navigate class="text-xs">Self Evaluation</flux:navlist.item>
                                        <flux:navlist.item :href="route('faculty.dashboard', ['tab' => 'peer'])" :current="request()->routeIs('faculty.dashboard') && request('tab') === 'peer'" aria-label="Peer Evaluation" wire:navigate class="text-xs">Peer Evaluation</flux:navlist.item>
                                        <flux:navlist.item :href="route('faculty.dashboard', ['tab' => 'supervisor'])" :current="request()->routeIs('faculty.dashboard') && request('tab') === 'supervisor'" aria-label="Supervisor Evaluation" wire:navigate class="text-xs">Supervisor Evaluation</flux:navlist.item>
                                    @endif

                                    @if($user->hasRole('staff'))
                                        <flux:navlist.item :href="route('staff.dashboard', ['tab' => 'self'])" :current="request()->routeIs('staff.dashboard') && (request('tab') === 'self' || !request('tab'))" aria-label="Self Evaluation" wire:navigate class="text-xs">Self Evaluation</flux:navlist.item>
                                        <flux:navlist.item :href="route('staff.dashboard', ['tab' => 'peer'])" :current="request()->routeIs('staff.dashboard') && request('tab') === 'peer'" aria-label="Peer Evaluation" wire:navigate class="text-xs">Peer Evaluation</flux:navlist.item>
                                        <flux:navlist.item :href="route('staff.dashboard', ['tab' => 'supervisor'])" :current="request()->routeIs('staff.dashboard') && request('tab') === 'supervisor'" aria-label="Supervisor Evaluation" wire:navigate class="text-xs">Supervisor Evaluation</flux:navlist.item>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if($user->hasRole('admin'))
                            <flux:tooltip content="Evaluation Questions" position="right">
                                <flux:navlist.item icon="clipboard-document-list" :href="route('admin.questions')" :current="request()->routeIs('admin.questions')" aria-label="Evaluation Questions" wire:navigate>Evaluation Questions</flux:navlist.item>
                            </flux:tooltip>
                        @endif

                        @if($user->hasAnyRole(['admin', 'dean']))
                            <flux:tooltip content="Results" position="right">
                                <flux:navlist.item icon="check-badge" :href="route('evaluation-results')" :current="request()->routeIs('evaluation-results')" aria-label="Results" wire:navigate>Results</flux:navlist.item>
                            </flux:tooltip>
                        @endif

                        @if($user->hasAnyRole(['admin', 'dean', 'program head']))
                            <flux:tooltip content="Rankings" position="right">
                                <flux:navlist.item icon="trophy" :href="route('rankings')" :current="request()->routeIs('rankings')" aria-label="Rankings" wire:navigate>Rankings</flux:navlist.item>
                            </flux:tooltip>
                        @endif
                    </flux:navlist.group>

                    <!-- Analytics & Reports (Admin, Dean, Program Head) -->
                    @if($user->hasAnyRole(['admin', 'dean', 'program head']))
                        <flux:navlist.group heading="Reports & Tools" class="grid">
                            @if($user->hasRole('admin') && ($user->show_ai_pipeline ?? true))
                                <flux:tooltip content="AI Pipeline" position="right">
                                    <flux:navlist.item icon="beaker" :href="route('admin.ai')" :current="request()->routeIs('admin.ai')" aria-label="AI Pipeline" wire:navigate>AI Pipeline</flux:navlist.item>
                                </flux:tooltip>
                            @endif
                            
                            <flux:tooltip content="Reports" position="right">
                                <flux:navlist.item icon="document-chart-bar" :href="route('reports')" :current="request()->routeIs('reports')" aria-label="Reports" wire:navigate>Reports</flux:navlist.item>
                            </flux:tooltip>
                        </flux:navlist.group>
                    @endif
                </flux:navlist>
            </flux:sidebar>

            <!-- Main Content Container with Navbar, Page Slot, and Full-Width Footer -->
            <div class="flex-1 flex flex-col min-h-screen min-w-0">
                @if(auth()->check())
                    <x-admin.navbar />
                @endif

                <main id="main-content" class="flex-1">
                    {{ $slot }}
                </main>

                @if(auth()->check())
                    <x-admin.footer />
                @endif
            </div>
        </div>

        <livewire:default-password-modal />
        <x-terms-modal />
        <flux:toast />
        @fluxScripts
    </body>
</html>
