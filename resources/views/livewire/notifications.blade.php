<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.app')] class extends Component {
    public function mount()
    {
        if (auth()->check()) {
            $user = auth()->user();
            $user->update(['notifications_last_viewed_at' => now()]);
            $user->refresh();
        }
    }

    public function getNotificationsProperty()
    {
        return auth()->user() ? auth()->user()->getNotifications() : [];
    }
}; ?>

<div class="flex flex-col gap-8 w-full max-w-4xl mx-auto px-4 py-6">
    <!-- Header -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm">
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-50">Notifications & Alerts</h1>
        <p class="text-zinc-500 dark:text-zinc-400 text-sm mt-1">Stay up to date with active evaluation periods and pending tasks.</p>
    </div>

    <!-- Notifications List -->
    <div class="flex flex-col gap-4">
        @php $notifs = $this->notifications; @endphp
        @if(empty($notifs))
            <div class="text-center py-16 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl">
                <flux:icon icon="bell-slash" class="size-16 mx-auto text-zinc-300 mb-3" />
                <p class="font-medium text-zinc-500">No active notifications or alerts.</p>
            </div>
        @else
            @foreach($notifs as $notif)
                <div class="flex items-start gap-4 p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-sm transition-all duration-200 hover:shadow-md
                    @if($notif->type === 'reminder') border-l-4 border-l-amber-500 @elseif($notif->type === 'info') border-l-4 border-l-indigo-500 @else border-l-4 border-l-rose-500 @endif">
                    
                    <div class="shrink-0 mt-0.5">
                        @if($notif->type === 'reminder')
                            <flux:icon icon="clock" class="size-6 text-amber-500" />
                        @elseif($notif->type === 'info')
                            <flux:icon icon="information-circle" class="size-6 text-indigo-500" />
                        @else
                            <flux:icon icon="exclamation-circle" class="size-6 text-rose-500" />
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="text-base font-bold text-zinc-900 dark:text-zinc-50">{{ $notif->title }}</div>
                        <p class="text-sm text-zinc-650 dark:text-zinc-355 mt-1 leading-relaxed">{{ $notif->description }}</p>
                        <span class="text-xs text-zinc-400 mt-2 block font-medium">
                            {{ $notif->created_at->diffForHumans() }}
                        </span>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>
