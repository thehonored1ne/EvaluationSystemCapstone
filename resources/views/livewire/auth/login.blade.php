<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string')]
    public string $identifier = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        $user = null;

        // 1. Try finding by email
        if (filter_var($this->identifier, FILTER_VALIDATE_EMAIL)) {
            $user = \App\Models\User::where('email', $this->identifier)->first();
        }

        // 2. Try finding by student number
        if (!$user) {
            $student = \App\Models\Student::where('student_number', $this->identifier)->first();
            if ($student) {
                $user = \App\Models\User::where('student_id', $student->id)->first();
            }
        }

        // 3. Try finding by employee number
        if (!$user) {
            $employee = \App\Models\Employee::where('employee_number', $this->identifier)->first();
            if ($employee) {
                $user = \App\Models\User::where('employee_id', $employee->id)->first();
            }
        }

        // 4. Verify password and authenticate
        if ($user && $user->is_active && \Illuminate\Support\Facades\Hash::check($this->password, $user->password)) {
            Auth::login($user, $this->remember);
            RateLimiter::clear($this->throttleKey());
            Session::regenerate();

            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
            return;
        }

        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'identifier' => __('auth.failed'),
        ]);
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'identifier' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->identifier).'|'.request()->ip());
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Log in to your account" description="Enter your ID and password below to log in" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="login" class="flex flex-col gap-6">
        <!-- Identifier -->
        <flux:input wire:model="identifier" label="{{ __('Student ID / Employee ID') }}" type="text" name="identifier" required autofocus autocomplete="username" placeholder="Enter your ID" />

        <!-- Password -->
        <div class="relative">
            <flux:input
                wire:model="password"
                label="{{ __('Password') }}"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="Password"
            />

            @if (Route::has('password.request'))
                <x-text-link class="absolute right-0 top-0" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </x-text-link>
            @endif
        </div>

        <!-- Remember Me -->
        <flux:checkbox wire:model="remember" label="{{ __('Remember me') }}" />

        <div class="flex items-center justify-end">
            <flux:button variant="primary" type="submit" class="w-full">{{ __('Log in') }}</flux:button>
        </div>
    </form>
</div>
