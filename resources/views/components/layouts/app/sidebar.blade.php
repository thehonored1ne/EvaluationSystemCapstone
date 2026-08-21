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
                body.sidebar-is-collapsed [data-flux-sidebar] {
                    width: 4.25rem !important;
                    min-width: 4.25rem !important;
                    padding-left: 0.375rem !important;
                    padding-right: 0.375rem !important;
                    align-items: center !important;
                }
                body.sidebar-is-collapsed [data-flux-sidebar] [data-flux-navlist-group-heading],
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
            }
        </style>
    </head>
    <body 
        x-data="{ 
            sidebarCollapsed: window.innerWidth >= 1024 && localStorage.getItem('admin_sidebar_collapsed') === 'true',
            toggle() {
                if (window.innerWidth >= 1024) {
                    this.sidebarCollapsed = !this.sidebarCollapsed;
                    localStorage.setItem('admin_sidebar_collapsed', this.sidebarCollapsed);
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
                class="border-r border-zinc-200 dark:border-zinc-800 bg-white dark:bg-[#171717] transition-all duration-200 shrink-0 print:hidden"
            >
                <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

                <!-- Logo Section: Full logo on mobile and desktop-expanded; Small Icon ONLY when collapsed on desktop (lg:) -->
                <a href="{{ route('dashboard') }}" class="flex items-center justify-center w-full px-1 py-2 mb-2 shrink-0" wire:navigate>
                    <!-- Big Logo: Always on Mobile (max-lg), and on Desktop when not collapsed -->
                    <div class="w-full flex items-center justify-center" :class="{ 'lg:hidden': sidebarCollapsed }">
                        <x-app-logo class="w-full"></x-app-logo>
                    </div>
                    <!-- Small Logo Icon: Desktop only, when collapsed -->
                    <div x-cloak class="hidden items-center justify-center p-1.5 rounded-xl bg-red-950/10 dark:bg-red-950/30 border border-red-900/20 text-[#9b0000] dark:text-[#f89696]" :class="{ 'lg:flex': sidebarCollapsed }">
                        <x-app-logo-icon class="size-7 text-[#9b0000] dark:text-[#f89696] fill-current"></x-app-logo-icon>
                    </div>
                </a>

                @php
                    $activeSemester = \App\Models\Semester::where('is_active', true)->with('academicYear')->first();
                    $shortSemName = $activeSemester ? str_replace(['Semester', 'semester'], ['Sem', 'Sem'], $activeSemester->name) : '';
                @endphp

                <!-- Active Term Indicator: Always on Mobile, and on Desktop when not collapsed -->
                <div class="px-2 mb-3" :class="{ 'lg:hidden': sidebarCollapsed }">
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

                                <div x-show="open" :class="{ 'lg:hidden': sidebarCollapsed }" class="pl-6 flex flex-col gap-1 border-l border-zinc-200 dark:border-zinc-700 ml-3.5 mt-1 mb-2">
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
                            <div x-data="{ open: {{ request()->routeIs('student.dashboard', 'faculty.dashboard', 'staff.dashboard', 'dean.dashboard', 'department-head.dashboard', 'program-head.dashboard') ? 'true' : 'false' }} }" class="w-full">
                                <flux:tooltip content="My Evaluations" position="right">
                                    <flux:navlist.item 
                                        icon="clipboard-document-check" 
                                        as="button"
                                        @click.prevent="open = !open" 
                                        :current="request()->routeIs('student.dashboard', 'faculty.dashboard', 'staff.dashboard', 'dean.dashboard', 'department-head.dashboard', 'program-head.dashboard')"
                                        class="cursor-pointer w-full text-left"
                                        title="My Evaluations"
                                    >
                                        <div class="flex justify-between items-center w-full">
                                            <span>My Evaluations</span>
                                            <flux:icon icon="chevron-down" class="size-4 shrink-0 transition-transform duration-200" ::class="open ? 'rotate-180' : ''" />
                                        </div>
                                    </flux:navlist.item>
                                </flux:tooltip>

                                <div x-show="open" :class="{ 'lg:hidden': sidebarCollapsed }" class="pl-6 flex flex-col gap-1 border-l border-zinc-200 dark:border-zinc-700 ml-3.5 mt-1 mb-2">
                                    @if($user->hasRole('dean'))
                                        <flux:tooltip content="Self Evaluation" position="right">
                                            <flux:navlist.item :href="route('dean.dashboard', ['tab' => 'self'])" :current="request()->routeIs('dean.dashboard') && (request('tab') === 'self' || !request('tab'))" wire:navigate class="text-xs" title="Self Evaluation">Self Evaluation</flux:navlist.item>
                                        </flux:tooltip>
                                        <flux:tooltip content="Faculty Evaluations" position="right">
                                            <flux:navlist.item :href="route('dean.dashboard', ['tab' => 'faculty'])" :current="request()->routeIs('dean.dashboard') && request('tab') === 'faculty'" wire:navigate class="text-xs" title="Faculty Evaluations">Faculty Evaluations</flux:navlist.item>
                                        </flux:tooltip>
                                        <flux:tooltip content="Program Head Evaluations" position="right">
                                            <flux:navlist.item :href="route('dean.dashboard', ['tab' => 'program-heads'])" :current="request()->routeIs('dean.dashboard') && request('tab') === 'program-heads'" wire:navigate class="text-xs" title="Program Head Evaluations">Program Head Evaluations</flux:navlist.item>
                                        </flux:tooltip>
                                    @endif

                                    @if($user->hasRole('department head'))
                                        <flux:tooltip content="Self Evaluation" position="right">
                                            <flux:navlist.item :href="route('department-head.dashboard', ['tab' => 'self'])" :current="request()->routeIs('department-head.dashboard') && request('tab') === 'self'" wire:navigate class="text-xs" title="Self Evaluation">Self Evaluation</flux:navlist.item>
                                        </flux:tooltip>
                                        <flux:tooltip content="Staff Evaluation" position="right">
                                            <flux:navlist.item :href="route('department-head.dashboard', ['tab' => 'staff'])" :current="request()->routeIs('department-head.dashboard') && request('tab') === 'staff'" wire:navigate class="text-xs" title="Staff Evaluation">Staff Evaluation</flux:navlist.item>
                                        </flux:tooltip>
                                        <flux:tooltip content="Dean Evaluation" position="right">
                                            <flux:navlist.item :href="route('department-head.dashboard', ['tab' => 'dean'])" :current="request()->routeIs('department-head.dashboard') && request('tab') === 'dean'" wire:navigate class="text-xs" title="Dean Evaluation">Dean Evaluation</flux:navlist.item>
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
                                            <flux:navlist.item :href="route('staff.dashboard', ['tab' => 'self'])" :current="request()->routeIs('staff.dashboard') && (request('tab') === 'self' || !request('tab'))" wire:navigate class="text-xs" title="Self Evaluation">Self Evaluation</flux:navlist.item>
                                        </flux:tooltip>
                                        <flux:tooltip content="Peer Evaluation" position="right">
                                            <flux:navlist.item :href="route('staff.dashboard', ['tab' => 'peer'])" :current="request()->routeIs('staff.dashboard') && request('tab') === 'peer'" wire:navigate class="text-xs" title="Peer Evaluation">Peer Evaluation</flux:navlist.item>
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

                        @if($user->hasAnyRole(['admin', 'dean', 'program head']))
                            <flux:tooltip content="Rankings" position="right">
                                <flux:navlist.item icon="trophy" :href="route('rankings')" :current="request()->routeIs('rankings')" wire:navigate title="Rankings">Rankings</flux:navlist.item>
                            </flux:tooltip>
                        @endif
                    </flux:navlist.group>

                    <!-- Analytics & Reports (Admin, Dean, Program Head) -->
                    @if($user->hasAnyRole(['admin', 'dean', 'program head']))
                        <flux:navlist.group heading="Reports & Tools" class="grid">
                            @if($user->hasRole('admin') && ($user->show_ai_pipeline ?? true))
                                <flux:tooltip content="AI Pipeline" position="right">
                                    <flux:navlist.item icon="beaker" :href="route('admin.ai')" :current="request()->routeIs('admin.ai')" wire:navigate title="AI Pipeline">AI Pipeline</flux:navlist.item>
                                </flux:tooltip>
                            @endif
                            
                            <flux:tooltip content="Reports" position="right">
                                <flux:navlist.item icon="document-chart-bar" :href="route('reports')" :current="request()->routeIs('reports')" wire:navigate title="Reports">Reports</flux:navlist.item>
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
