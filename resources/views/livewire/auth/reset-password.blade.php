<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        if ($status != Password::PasswordReset) {
            $this->addError('email', __($status));

            return;
        }

        Session::flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="{{ __('Reset password') }}" description="{{ __('Please enter your new password below') }}" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center text-[#7a0000] font-semibold text-sm" :status="session('status')" />

    <form wire:submit="resetPassword" class="flex flex-col gap-5">
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
                autocomplete="email"
                class="w-full px-3.5 py-2.5 bg-white text-zinc-900 font-semibold text-sm rounded-xl border border-zinc-300 focus:border-[#9b0000] focus:ring-2 focus:ring-[#9b0000]/25 focus:outline-hidden transition-all placeholder:text-zinc-400 placeholder:font-normal shadow-2xs"
            />
            @error('email')
                <p class="text-xs text-rose-600 font-semibold">{{ $message }}</p>
            @enderror
        </div>

        <!-- Password -->
        <div class="flex flex-col gap-1.5 text-left">
            <label for="password" class="block text-xs font-bold uppercase tracking-wider text-[#7a0000]">
                {{ __('Password') }}
            </label>
            <input 
                wire:model="password" 
                id="password"
                type="password"
                name="password" 
                required 
                autocomplete="new-password" 
                placeholder="Enter new password"
                class="w-full px-3.5 py-2.5 bg-white text-zinc-900 font-semibold text-sm rounded-xl border border-zinc-300 focus:border-[#9b0000] focus:ring-2 focus:ring-[#9b0000]/25 focus:outline-hidden transition-all placeholder:text-zinc-400 placeholder:font-normal shadow-2xs"
            />
            @error('password')
                <p class="text-xs text-rose-600 font-semibold">{{ $message }}</p>
            @enderror
        </div>

        <!-- Confirm Password -->
        <div class="flex flex-col gap-1.5 text-left">
            <label for="password_confirmation" class="block text-xs font-bold uppercase tracking-wider text-[#7a0000]">
                {{ __('Confirm password') }}
            </label>
            <input 
                wire:model="password_confirmation" 
                id="password_confirmation"
                type="password"
                name="password_confirmation" 
                required 
                autocomplete="new-password" 
                placeholder="Confirm new password"
                class="w-full px-3.5 py-2.5 bg-white text-zinc-900 font-semibold text-sm rounded-xl border border-zinc-300 focus:border-[#9b0000] focus:ring-2 focus:ring-[#9b0000]/25 focus:outline-hidden transition-all placeholder:text-zinc-400 placeholder:font-normal shadow-2xs"
            />
            @error('password_confirmation')
                <p class="text-xs text-rose-600 font-semibold">{{ $message }}</p>
            @enderror
        </div>

        <button 
            type="submit" 
            class="w-full py-3 px-4 bg-[#7a0000] hover:bg-[#9b0000] active:bg-[#600000] text-white font-bold text-sm rounded-xl shadow-md hover:shadow-lg transition-all duration-150 flex items-center justify-center gap-2 cursor-pointer"
        >
            <span>{{ __('Reset password') }}</span>
        </button>
    </form>
</div>
