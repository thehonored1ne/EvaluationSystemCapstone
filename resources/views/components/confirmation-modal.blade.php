@props([
    'title',
    'onConfirm',
    'onCancel',
    'confirmText' => 'Delete',
    'cancelText' => 'Cancel',
    'variant' => 'danger',
    'disabled' => false,
])

<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
     x-data="{ show: true }"
     x-show="show"
     x-transition>
    
    <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 w-full max-w-md overflow-hidden transform transition-all duration-300">
        
        <!-- Header & Description -->
        <div class="p-6 pb-4 flex items-start gap-4">
            <!-- Icon based on variant -->
            @if($variant === 'danger')
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-rose-50 dark:bg-rose-950/30 flex items-center justify-center text-rose-600 dark:text-rose-400">
                    <flux:icon icon="trash" variant="outline" class="size-5" />
                </div>
            @else
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-amber-50 dark:bg-amber-950/30 flex items-center justify-center text-amber-600 dark:text-amber-400">
                    <flux:icon icon="exclamation-triangle" variant="outline" class="size-5" />
                </div>
            @endif
            
            <div class="flex-1 min-w-0">
                <h3 class="text-lg font-bold text-zinc-900 dark:text-zinc-50 leading-6">
                    {{ $title }}
                </h3>
                
                <div class="mt-2 text-sm text-zinc-500 dark:text-zinc-400 leading-normal font-medium">
                    {{ $slot }}
                </div>
            </div>
        </div>

        <!-- Details Container (if slot is provided) -->
        @if(isset($details))
            <div class="px-6 py-4 bg-zinc-50 dark:bg-zinc-800/20 border-t border-b border-zinc-100 dark:border-zinc-800/80">
                {{ $details }}
            </div>
        @endif

        <!-- Warning / Alerts Container (if slot is provided) -->
        @if(isset($warning))
            <div class="px-6 py-3 bg-rose-50/50 dark:bg-rose-950/10 border-b border-rose-100/50 dark:border-rose-900/20 text-rose-700 dark:text-rose-400 text-xs font-semibold leading-relaxed">
                <div class="flex gap-2">
                    <flux:icon icon="exclamation-circle" variant="mini" class="size-4 shrink-0 text-rose-500 mt-0.5" />
                    <div>{{ $warning }}</div>
                </div>
            </div>
        @endif

        <!-- Footer / Actions -->
        <div class="px-6 py-4 bg-zinc-50/50 dark:bg-zinc-900/50 border-t border-zinc-100 dark:border-zinc-800/50 flex justify-end gap-3">
            <flux:button size="sm" wire:click="{{ $onCancel }}">{{ $cancelText }}</flux:button>
            <flux:button size="sm" :variant="$variant === 'danger' ? 'danger' : 'primary'" wire:click="{{ $onConfirm }}" :disabled="$disabled">
                {{ $confirmText }}
            </flux:button>
        </div>
    </div>
</div>
