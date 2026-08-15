<?php

use Livewire\Volt\Component;

new class extends Component {
    public function markAllAsRead()
    {
        if (auth()->check()) {
            $user = auth()->user();
            $user->update(['notifications_last_viewed_at' => now()]);
            $user->refresh();

            \Flux::toast(
                heading: 'Notifications Read',
                text: 'All notifications marked as read.',
                variant: 'success'
            );
        }
    }

    public function dismiss(string $id)
    {
        if (auth()->check()) {
            $user = auth()->user();
            $user->dismissNotification($id);
            $user->refresh();

            \Flux::toast(
                heading: 'Notification Removed',
                text: 'Notification has been dismissed.',
                variant: 'success'
            );
        }
    }

    public function clearAll()
    {
        if (auth()->check()) {
            $user = auth()->user();
            $user->clearAllNotifications();
            $user->refresh();

            \Flux::toast(
                heading: 'Notifications Cleared',
                text: 'All current notifications have been cleared.',
                variant: 'success'
            );
        }
    }

    public function getNotificationsProperty()
    {
        return auth()->check() ? auth()->user()->getNotifications() : [];
    }

    public function getUnreadCountProperty()
    {
        if (! auth()->check()) {
            return 0;
        }

        $user = auth()->user();
        $notifications = $this->notifications;
        $lastViewed = $user->notifications_last_viewed_at;

        $count = 0;
        foreach ($notifications as $notif) {
            if (! $lastViewed || (isset($notif->created_at) && $notif->created_at->gt($lastViewed))) {
                $count++;
            }
        }

        return $count;
    }
}; ?>

<div>
    <flux:dropdown position="bottom" align="end">
        <button type="button" class="relative inline-flex items-center justify-center p-2 rounded-lg text-white hover:bg-white/10 transition-colors cursor-pointer" title="Notifications">
            <flux:icon icon="bell" class="size-5" />
            @if($this->unreadCount > 0)
                <span class="absolute top-0 right-0 -mt-0.5 -mr-0.5 z-10 flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-400 text-[10px] font-bold text-zinc-950 px-1 border border-red-900 shadow-sm animate-pulse">
                    {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
                </span>
            @endif
        </button>

        <flux:menu class="w-80 sm:w-96 p-0 divide-y divide-zinc-100 dark:divide-zinc-800 overflow-hidden shadow-xl rounded-xl">
            <!-- Dropdown Header -->
            <div class="flex items-center justify-between px-4 py-3 bg-zinc-50 dark:bg-zinc-900 border-b border-zinc-200 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-bold text-zinc-900 dark:text-white">Notifications</span>
                    @if($this->unreadCount > 0)
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-[#f89696] border border-red-200 dark:border-red-900/40">
                            {{ $this->unreadCount }} new
                        </span>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    @if($this->unreadCount > 0)
                        <button 
                            type="button" 
                            wire:click="markAllAsRead" 
                            class="text-xs font-semibold text-amber-600 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300 flex items-center gap-1 hover:underline cursor-pointer transition-colors"
                            title="Mark all notifications as read"
                        >
                            <flux:icon icon="check" class="size-3.5" />
                            <span>Read all</span>
                        </button>
                    @endif

                    @if(count($this->notifications) > 0)
                        <button 
                            type="button" 
                            wire:click="clearAll" 
                            class="text-xs font-semibold text-zinc-500 hover:text-red-600 dark:text-zinc-400 dark:hover:text-[#f89696] flex items-center gap-1 hover:underline cursor-pointer transition-colors"
                            title="Clear all notifications"
                        >
                            <flux:icon icon="trash" class="size-3.5" />
                            <span>Clear all</span>
                        </button>
                    @endif
                </div>
            </div>

            <!-- Notifications List -->
            <div class="max-h-96 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800/60">
                @php
                    $lastViewed = auth()->user()?->notifications_last_viewed_at;
                @endphp
                @forelse($this->notifications as $notification)
                    @php
                        $isUnread = ! $lastViewed || (isset($notification->created_at) && $notification->created_at->gt($lastViewed));
                    @endphp
                    <div class="group relative p-3.5 transition-colors {{ $isUnread ? 'bg-amber-50/40 dark:bg-amber-950/20 hover:bg-amber-50/70 dark:hover:bg-amber-950/30' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}">
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
                            <div class="flex-1 min-w-0 pr-6">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-bold text-zinc-900 dark:text-zinc-100 truncate flex items-center gap-1.5">
                                        @if($isUnread)
                                            <span class="size-1.5 rounded-full bg-[#9b0000] dark:bg-[#f89696] shrink-0"></span>
                                        @endif
                                        {{ $notification->title ?? 'Notification' }}
                                    </p>
                                    <span class="text-[10px] font-medium text-zinc-400 dark:text-zinc-500 whitespace-nowrap">
                                        {{ isset($notification->created_at) && method_exists($notification->created_at, 'diffForHumans') ? $notification->created_at->diffForHumans() : '' }}
                                    </span>
                                </div>
                                <p class="text-xs text-zinc-600 dark:text-zinc-300 mt-1 leading-relaxed">
                                    {{ $notification->description ?? '' }}
                                </p>
                            </div>
                        </div>

                        <!-- Individual Remove/Dismiss Button -->
                        <button
                            type="button"
                            wire:click.stop="dismiss('{{ $notification->id }}')"
                            class="absolute top-3 right-3 p-1 rounded-md text-zinc-400 hover:text-red-600 hover:bg-zinc-100 dark:hover:text-red-400 dark:hover:bg-zinc-800 transition-colors opacity-0 group-hover:opacity-100 focus:opacity-100 cursor-pointer"
                            title="Remove notification"
                        >
                            <flux:icon icon="x-mark" class="size-3.5" />
                        </button>
                    </div>
                @empty
                    <div class="py-10 px-4 text-center text-zinc-400 dark:text-zinc-500 flex flex-col items-center gap-2">
                        <flux:icon icon="bell-slash" class="size-8 text-zinc-300 dark:text-zinc-600" />
                        <p class="text-xs font-medium">No notifications available.</p>
                    </div>
                @endforelse
            </div>

            <!-- Dropdown Footer -->
            <div class="px-4 py-2.5 bg-zinc-50 dark:bg-zinc-900 text-center flex items-center justify-between text-[11px] text-zinc-500 dark:text-zinc-400">
                <span>{{ count($this->notifications) }} active notification{{ count($this->notifications) === 1 ? '' : 's' }}</span>
                <div class="flex items-center gap-3">
                    @if($this->unreadCount > 0)
                        <button 
                            type="button" 
                            wire:click="markAllAsRead" 
                            class="font-semibold text-[#9b0000] dark:text-[#f89696] hover:underline cursor-pointer"
                        >
                            Mark all as read
                        </button>
                    @endif
                    @if(count($this->notifications) > 0)
                        <button 
                            type="button" 
                            wire:click="clearAll" 
                            class="font-semibold text-zinc-500 dark:text-zinc-400 hover:text-red-600 dark:hover:text-red-400 hover:underline cursor-pointer"
                        >
                            Clear all
                        </button>
                    @endif
                </div>
            </div>
        </flux:menu>
    </flux:dropdown>
</div>
