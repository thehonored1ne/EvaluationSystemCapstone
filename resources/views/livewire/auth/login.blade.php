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

<div class="flex flex-col gap-6 text-[#800000]">
    <style>
        /* Dark red theme overrides & focus outline cleanup */
        .login-dark-red input,
        .login-dark-red input:focus,
        .login-dark-red input:focus-visible,
        .login-dark-red select:focus,
        .login-dark-red textarea:focus,
        .login-dark-red [data-flux-input],
        .login-dark-red [data-flux-input]:focus-within,
        .login-dark-red [data-flux-input] input,
        .login-dark-red [data-flux-input] input:focus,
        .login-dark-red [data-flux-input] input:focus-visible {
            outline: none !important;
            outline-width: 0 !important;
            outline-style: none !important;
            box-shadow: none !important;
        }
        .login-dark-red input:focus,
        .login-dark-red input:focus-visible,
        .login-dark-red [data-flux-input]:focus-within {
            border-color: #800000 !important;
            box-shadow: 0 0 0 2px rgba(128, 0, 0, 0.25) !important;
            border-radius: 0.5rem !important;
        }
        .login-dark-red label,
        .login-dark-red [data-flux-label],
        .login-dark-red [data-flux-input] button,
        .login-dark-red [data-flux-input] svg,
        .login-dark-red [data-flux-input] [data-flux-icon] {
            color: #800000 !important;
            font-weight: 600 !important;
        }
        .login-btn-darkred {
            background-color: #800000 !important;
            color: #ffffff !important;
        }
        .login-btn-darkred:hover {
            background-color: #660000 !important;
            color: #ffffff !important;
        }
        .back-btn-darkred {
            color: #800000 !important;
        }
        .back-btn-darkred:hover {
            background-color: rgba(128, 0, 0, 0.08) !important;
            color: #660000 !important;
        }
    </style>

    <div class="flex w-full flex-col gap-2 text-center">
        <h1 class="text-2xl font-bold tracking-tight" style="color: #800000 !important;">{{ __('Log in to your account') }}</h1>
        <p class="text-center text-sm font-medium" style="color: rgba(128, 0, 0, 0.8) !important;">{{ __('Enter your Student/Employee ID or Email and password below to log in') }}</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="text-center" style="color: #800000 !important;" :status="session('status')" />

    <form wire:submit="login" class="flex flex-col gap-6 login-dark-red">
        <!-- Identifier -->
        <div>
            <flux:input 
                wire:model="identifier" 
                label="{{ __('Student ID / Employee ID / Email Address') }}" 
                type="text" 
                name="identifier" 
                required 
                autofocus 
                autocomplete="username" 
                placeholder="e.g. 2023-07-00483 / example@gmail.com"
                style="color: #800000 !important;"
            />
        </div>

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
                viewable
                style="color: #800000 !important;"
            />

            @if (Route::has('password.request'))
                <a class="absolute right-0 top-0 text-xs font-semibold underline underline-offset-2 transition-colors z-10" style="color: #800000 !important;" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <!-- Remember Me -->
        <div class="flex items-center gap-2">
            <flux:checkbox wire:model="remember" label="{{ __('Remember me') }}" class="font-medium" style="accent-color: #800000 !important; color: #800000 !important;" />
        </div>

        <div class="flex flex-col gap-3">
            <button type="submit" class="w-full py-3 px-4 login-btn-darkred font-semibold text-sm rounded-lg shadow-md transition-all duration-200 flex items-center justify-center gap-2 cursor-pointer" style="background-color: #800000 !important; color: #ffffff !important;">
                <span>{{ __('Log in') }}</span>
            </button>
            <a href="{{ route('home') }}" class="w-full py-2.5 px-4 back-btn-darkred font-semibold rounded-lg transition-all duration-200 flex items-center justify-center gap-2 text-sm" style="color: #800000 !important;" wire:navigate>
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>{{ __('Back to Home Page') }}</span>
            </a>
        </div>
    </form>
</div>
