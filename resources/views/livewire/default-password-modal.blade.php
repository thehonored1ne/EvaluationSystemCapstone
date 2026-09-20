<?php

use Livewire\Volt\Component;

new class extends Component
{
    public function dismiss(): void
    {
        session(['default_password_modal_dismissed' => true]);
    }

    public function dismissLater(): void
    {
        session(['default_password_modal_dismissed' => true]);
    }

    public function goToChangePassword()
    {
        session(['default_password_modal_dismissed' => true]);

        return $this->redirect(route('settings.password'), navigate: true);
    }

    public function shouldShow(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        if (session('default_password_modal_dismissed')) {
            return false;
        }

        if (request()->routeIs('settings.password')) {
            return false;
        }

        return (bool) auth()->user()?->isUsingDefaultPassword();
    }
}; ?>

<div class="contents">
    @if($this->shouldShow())
        <div 
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-xs p-4"
            x-data="{ show: true }"
            x-init="sessionStorage.removeItem('default_password_modal_dismissed')"
            x-show="show"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform scale-95"
            x-transition:enter-end="opacity-100 transform scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform scale-100"
            x-transition:leave-end="opacity-0 transform scale-95"
            role="dialog"
            aria-modal="true"
            aria-labelledby="default-pass-modal-title"
        >
            <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-800 w-[calc(100vw-2rem)] sm:w-full max-w-md sm:max-w-lg overflow-hidden transform transition-all duration-300">
                <!-- Modal Body -->
                <div class="p-5 sm:p-6">
                    <div class="mb-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] sm:text-xs font-bold uppercase tracking-wider bg-rose-100/80 dark:bg-rose-950/60 text-[#9b0000] dark:text-[#e07a7a] border border-rose-200 dark:border-rose-900/50">
                            Security Advisory
                        </span>
                    </div>

                    <h3 id="default-pass-modal-title" class="text-base sm:text-lg font-bold text-zinc-900 dark:text-zinc-100 tracking-tight leading-snug">
                        Default Password In Use
                    </h3>

                    <p class="mt-2 text-xs sm:text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                        Your account is currently using the institutional default password. For the privacy and security of your evaluation records and account data, we strongly recommend changing it to a private, secure password.
                    </p>

                    <!-- Informational Callout Box -->
                    <div class="mt-4 p-3 sm:p-3.5 rounded-xl bg-amber-500/10 dark:bg-amber-950/20 border border-amber-500/20 dark:border-amber-900/30 text-amber-900 dark:text-amber-300/90 text-[11px] sm:text-xs leading-relaxed">
                        This security notice will continue to remind you on future logins until your password is changed.
                    </div>
                </div>

                <!-- Modal Footer Controls -->
                <div class="px-5 sm:px-6 py-3.5 sm:py-4 bg-zinc-50/80 dark:bg-zinc-900/80 border-t border-zinc-100 dark:border-zinc-800/80 flex flex-col-reverse sm:flex-row items-center justify-end gap-2 sm:gap-3">
                    <button 
                        type="button" 
                        @click="show = false"
                        wire:click="dismissLater" 
                        class="w-full sm:w-auto px-4 py-2 sm:py-2.5 rounded-xl text-xs sm:text-sm font-semibold text-zinc-700 dark:text-zinc-300 hover:bg-zinc-200/70 dark:hover:bg-zinc-800 transition-colors cursor-pointer text-center"
                    >
                        Remind Me Later
                    </button>

                    <button 
                        type="button" 
                        @click="show = false"
                        wire:click="goToChangePassword" 
                        class="w-full sm:w-auto px-5 py-2 sm:py-2.5 rounded-xl text-xs sm:text-sm font-bold text-white bg-[#7a0000] hover:bg-[#9b0000] active:bg-[#600000] shadow-sm hover:shadow transition-all duration-150 flex items-center justify-center cursor-pointer"
                    >
                        Change Password Now
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
