<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink($this->only('email'));

        session()->flash('status', __('A reset link will be sent if the account exists.'));
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="{{ __('Forgot password') }}" description="{{ __('Enter your email to receive a password reset link') }}" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center text-[#7a0000] font-semibold text-sm" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="flex flex-col gap-5">
        <!-- Email Address -->
        <div class="flex flex-col gap-1.5 text-left">
            <label for="email" class="block text-xs font-bold uppercase tracking-wider text-[#7a0000]">
                {{ __('Email Address') }}
            </label>
            <input 
                wire:model="email" 
                id="email"
                type="email" 
                name="email" 
                required 
                autofocus 
                autocomplete="email"
                placeholder="name@grc.edu.ph"
                class="w-full px-3.5 py-2.5 bg-white text-zinc-900 font-semibold text-sm rounded-xl border border-zinc-300 focus:border-[#9b0000] focus:ring-2 focus:ring-[#9b0000]/25 focus:outline-hidden transition-all placeholder:text-zinc-400 placeholder:font-normal shadow-2xs"
            />
            @error('email')
                <p class="text-xs text-rose-600 font-semibold">{{ $message }}</p>
            @enderror
        </div>

        <button 
            type="submit" 
            class="w-full py-3 px-4 bg-[#7a0000] hover:bg-[#9b0000] active:bg-[#600000] text-white font-bold text-sm rounded-xl shadow-md hover:shadow-lg transition-all duration-150 flex items-center justify-center gap-2 cursor-pointer"
        >
            <span>{{ __('Email password reset link') }}</span>
        </button>
    </form>

    <div class="space-x-1 text-center text-sm font-medium text-zinc-600">
        {{ __('Or, return to') }}
        <a href="{{ route('login') }}" class="font-bold text-[#7a0000] hover:text-[#9b0000] hover:underline" wire:navigate>{{ __('log in') }}</a>
    </div>
</div>
