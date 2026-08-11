@php
    $user = auth()->user();
    $unreadNotificationsCount = 0;
    $notifications = [];
    if ($user) {
        $notifications = $user->getNotifications();
        if (request()->routeIs('notifications')) {
            $unreadNotificationsCount = 0;
        } else {
            $lastViewed = $user->notifications_last_viewed_at;
            foreach ($notifications as $notif) {
                if (!$lastViewed || (isset($notif->created_at) && $notif->created_at->gt($lastViewed))) {
                    $unreadNotificationsCount++;
                }
            }
        }
    }

    $roleRaw = $user ? ($user->getRoleNames()->first() ?? 'User') : 'User';
    $roleName = ucwords(str_replace(['_', '-'], ' ', $roleRaw));
@endphp


<flux:header class="border-b border-red-900/40 bg-[#800000] text-white shadow-md">
    <!-- Left Side: Sidebar Toggle & Logged-in User Badge -->
    <div class="flex items-center gap-2">
        <button 
            type="button" 
            x-data
            @click.prevent.stop="$dispatch('toggle-sidebar')" 
            class="cursor-pointer p-2 rounded-lg text-white hover:bg-white/10 transition-colors shrink-0"
            title="Toggle Sidebar"
        >
            <flux:icon icon="bars-2" class="size-5" />
        </button>

        <div class="hidden sm:flex items-center gap-2 ml-1 text-xs font-medium">
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

        <!-- Notification Icon Dropdown -->
        <flux:dropdown position="bottom" align="end">
            <button type="button" class="relative inline-flex items-center justify-center p-2 rounded-lg text-white hover:bg-white/10 transition-colors cursor-pointer" title="Notifications">
                <flux:icon icon="bell" class="size-5" />
                @if($unreadNotificationsCount > 0)
                    <span class="absolute top-0 right-0 -mt-0.5 -mr-0.5 z-10 flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-400 text-[10px] font-bold text-zinc-950 px-1 border border-red-900 shadow-sm animate-pulse">
                        {{ $unreadNotificationsCount > 9 ? '9+' : $unreadNotificationsCount }}
                    </span>
                @endif
            </button>

            <flux:menu class="w-80 sm:w-96 p-0 divide-y divide-zinc-100 dark:divide-zinc-800 overflow-hidden shadow-xl rounded-xl">
                <!-- Dropdown Header -->
                <div class="flex items-center justify-between px-4 py-3 bg-zinc-50 dark:bg-zinc-900">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-zinc-900 dark:text-white">Notifications</span>
                        @if($unreadNotificationsCount > 0)
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400">
                                {{ $unreadNotificationsCount }} new
                            </span>
                        @endif
                    </div>
                    <a href="{{ route('notifications') }}" class="text-xs font-medium text-amber-600 hover:text-amber-700 dark:text-amber-400 hover:underline" wire:navigate>
                        View All
                    </a>
                </div>

                <!-- Dropdown Content / List -->
                <div class="max-h-80 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800/60">
                    @forelse(array_slice($notifications, 0, 5) as $notification)
                        <div class="p-3.5 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <div class="flex items-start gap-3">
                                <div class="p-2 rounded-lg shrink-0 {{ ($notification->type ?? '') === 'warning' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : (($notification->type ?? '') === 'success' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400') }}">
                                    @if(($notification->type ?? '') === 'warning')
                                        <flux:icon icon="exclamation-triangle" class="size-4" />
                                    @elseif(($notification->type ?? '') === 'success')
                                        <flux:icon icon="check-circle" class="size-4" />
                                    @else
                                        <flux:icon icon="information-circle" class="size-4" />
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-xs font-medium text-zinc-900 dark:text-zinc-100 truncate">
                                            {{ $notification->title ?? 'Notification' }}
                                        </p>
                                        <span class="text-[10px] text-zinc-400 dark:text-zinc-500 whitespace-nowrap">
                                            {{ isset($notification->created_at) && method_exists($notification->created_at, 'diffForHumans') ? $notification->created_at->diffForHumans() : '' }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5 line-clamp-2">
                                        {{ $notification->description ?? '' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-zinc-500 dark:text-zinc-400 text-xs">
                            No notifications available.
                        </div>
                    @endforelse
                </div>

                <!-- Dropdown Footer -->
                <div class="p-2 bg-zinc-50 dark:bg-zinc-900 text-center">
                    <a href="{{ route('notifications') }}" class="block w-full py-1.5 text-xs font-medium text-zinc-700 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white hover:bg-zinc-200/50 dark:hover:bg-zinc-800 rounded-md transition-colors" wire:navigate>
                        Go to Notification Center →
                    </a>
                </div>
            </flux:menu>
        </flux:dropdown>

        <!-- Admin Profile Quick Dropdown -->
        <flux:dropdown position="bottom" align="end">
            <button type="button" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-white/10 transition-colors cursor-pointer">
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
