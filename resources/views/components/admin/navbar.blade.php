@php
    $user = auth()->user();
    $roleRaw = $user ? ($user->getRoleNames()->first() ?? 'User') : 'User';
    $roleName = ucwords(str_replace(['_', '-'], ' ', $roleRaw));
@endphp

<flux:header class="border-b border-red-900/40 bg-[#9b0000] text-white shadow-md print:hidden px-3 sm:px-4">
    <!-- Left Side: Sidebar Toggle & Logged-in User Badge -->
    <div class="flex items-center gap-3 sm:gap-4 -ml-1 sm:-ml-1.5">
        <button 
            type="button" 
            @click="if (window.innerWidth >= 1024) { $dispatch('toggle-sidebar'); } else { $dispatch('flux-sidebar-toggle'); }" 
            data-flux-sidebar-toggle
            class="cursor-pointer p-1.5 sm:p-2 rounded-lg text-white hover:bg-white/10 transition-colors shrink-0"
            :title="sidebarCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar'"
            aria-label="Toggle Sidebar"
        >
            <!-- Desktop: Lucide Sidebar Close (when expanded) -->
            <svg x-show="!sidebarCollapsed" class="size-5 hidden lg:block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect width="18" height="18" x="3" y="3" rx="2"/>
                <path d="M9 3v18"/>
                <path d="m16 15-3-3 3-3"/>
            </svg>

            <!-- Desktop: Lucide Sidebar Open (when collapsed) -->
            <svg x-show="sidebarCollapsed" x-cloak class="size-5 hidden lg:block" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect width="18" height="18" x="3" y="3" rx="2"/>
                <path d="M9 3v18"/>
                <path d="m14 9 3 3-3 3"/>
            </svg>

            <!-- Mobile: Classic Hamburger Menu Icon -->
            <flux:icon icon="bars-2" class="size-5 block lg:hidden" />
        </button>

        <div class="hidden sm:flex items-center text-xs font-medium">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/15 text-white border border-white/20 text-xs font-semibold shadow-xs">
                Logged as {{ $roleName }}
            </span>
        </div>
    </div>

    <flux:spacer />

    <!-- Right Side: Actions (Notifications, Theme Toggle, Profile) -->
    <div class="flex items-center gap-2 sm:gap-3">

        <!-- Dark / Light Mode Switcher -->
        <button 
            type="button" 
            x-data
            @click="$flux.appearance = $flux.appearance === 'dark' ? 'light' : 'dark'" 
            class="relative p-2 rounded-lg text-white hover:bg-white/10 transition-colors cursor-pointer"
            :title="$flux.appearance === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
            aria-label="Toggle Dark / Light Theme"
        >
            <!-- Sun Icon for Dark Mode -->
            <span x-show="$flux.appearance === 'dark'">
                <flux:icon icon="sun" class="size-5 text-amber-300" />
            </span>
            <!-- Half Moon Icon for Light Mode -->
            <span x-show="$flux.appearance !== 'dark'">
                <flux:icon icon="moon" class="size-5 text-white" />
            </span>
        </button>

        <!-- Interactive Notification Dropdown with Read All Action -->
        <livewire:notification-dropdown />

        <!-- Admin Profile Quick Dropdown -->
        <flux:dropdown position="bottom" align="end">
            <button type="button" aria-label="User Account Menu" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-white/10 transition-colors cursor-pointer">
                <span class="flex h-7 w-7 items-center justify-center rounded-md bg-white/20 text-white text-xs font-bold shadow-sm border border-white/30">
                    {{ auth()->user()->initials() }}
                </span>
                <span class="hidden md:inline-block text-xs font-semibold text-white max-w-[120px] truncate">
                    {{ auth()->user()->name }}
                </span>
                <flux:icon icon="chevron-down" class="size-3 text-red-200" />
            </button>

            <flux:menu class="w-56">
                <div class="px-3 py-2 border-b border-zinc-100 dark:border-zinc-800">
                    <p class="text-xs font-semibold text-zinc-900 dark:text-zinc-100 truncate">{{ auth()->user()->name }}</p>
                    <p class="text-[11px] text-zinc-500 dark:text-zinc-400 truncate">{{ auth()->user()->email }}</p>
                </div>
                <flux:menu.item href="/settings/profile" icon="cog" wire:navigate class="text-xs">Account Settings</flux:menu.item>
                <flux:menu.separator />
                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full text-xs text-red-600 dark:text-red-400">
                        Log Out
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </div>
</flux:header>
