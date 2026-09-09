<?php

use App\Models\Employee;
use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component
{
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
        try {
            $this->validate();

            $this->ensureIsNotRateLimited();

            $ident = trim($this->identifier);

            // 1. Try finding by email (case-insensitive)
            if (filter_var($ident, FILTER_VALIDATE_EMAIL)) {
                $user = User::whereRaw('LOWER(email) = ?', [strtolower($ident)])->first();
            }

            // 2. Try finding by student number (case-insensitive)
            if (! $user) {
                $student = Student::whereRaw('LOWER(student_number) = ?', [strtolower($ident)])->first();
                if ($student) {
                    $user = User::where('student_id', $student->id)->first();
                }
            }

            // 3. Try finding by employee number (case-insensitive)
            if (! $user) {
                $employee = Employee::whereRaw('LOWER(employee_number) = ?', [strtolower($ident)])->first();
                if ($employee) {
                    $user = User::where('employee_id', $employee->id)->first();
                }
            }

            // 4. Special fallback for admin aliases ('admin', 'admin@example.com', 'admin@grc.edu.ph')
            if (! $user && in_array(strtolower($ident), ['admin', 'admin@example.com', 'admin@grc.edu.ph', 'administrator'])) {
                $user = User::role('admin')->first();
            }

            // Mitigate timing attacks / user enumeration via constant-time hash check
            $dummyHash = config('auth.dummy_hash', '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ012');
            $passwordMatches = Hash::check($this->password, $user ? $user->password : $dummyHash);

            // 5. Verify password and authenticate
            if ($user && $user->is_active && $passwordMatches) {
                Auth::login($user, $this->remember);
                RateLimiter::clear($this->throttleKey());
                RateLimiter::clear($this->ipThrottleKey());
                Session::regenerate();

                $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

                return;
            }

            // 6. Handle deactivated account (if correct password was entered for an inactive user)
            if ($user && ! $user->is_active && $passwordMatches) {
                RateLimiter::hit($this->throttleKey());
                RateLimiter::hit($this->ipThrottleKey(), 180);
                $this->dispatch('login-failed');

                throw ValidationException::withMessages([
                    'identifier' => __('Your account has been deactivated. Please contact the administrator or ICT office.'),
                ]);
            }

            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit($this->ipThrottleKey(), 180);

            $this->dispatch('login-failed');

            throw ValidationException::withMessages([
                'identifier' => __('Invalid ID/Email or password. Please verify your credentials and try again.'),
            ]);
        } catch (ValidationException $e) {
            $this->reset('password');
            $this->dispatch('login-failed');
            throw $e;
        }
    }

    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        // 1. IP-level throttle against password spraying (max 20 attempts per 3 minutes across any identifiers)
        if (RateLimiter::tooManyAttempts($this->ipThrottleKey(), 20)) {
            $seconds = RateLimiter::availableIn($this->ipThrottleKey());
            $this->dispatch('login-failed');

            throw ValidationException::withMessages([
                'identifier' => __('Too many login attempts from this network. Please try again in :minutes minute(s).', [
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        // 2. Targeted identifier-level throttle (max 5 attempts per identifier per IP)
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        $this->dispatch('login-failed');

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

    /**
     * Get the IP-level authentication rate limiting key.
     */
    protected function ipThrottleKey(): string
    {
        return 'login-ip|'.request()->ip();
    }
}; ?>

<div class="flex flex-col gap-6" x-init="sessionStorage.removeItem('default_password_modal_dismissed')">
    <div class="flex w-full flex-col gap-2 text-center">
        <h1 class="text-2xl font-black tracking-tight text-[#7a0000]">{{ __('Log in to your account') }}</h1>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="text-center text-[#7a0000] font-semibold text-sm" :status="session('status')" />

    <form 
        wire:submit="login" 
        x-data="{ isSubmitting: false }"
        x-on:submit="isSubmitting = true"
        x-on:login-failed.window="isSubmitting = false"
        x-on:livewire:error.window="isSubmitting = false"
        class="flex flex-col gap-5"
    >
        <!-- Identifier -->
        <div class="flex flex-col gap-1.5 text-left">
            <label for="identifier" class="block text-xs font-bold uppercase tracking-wider text-[#7a0000]">
                {{ __('Student/Employee ID or Email Address') }}
            </label>
            <div class="relative">
                <input 
                    wire:model="identifier" 
                    id="identifier"
                    type="text" 
                    name="identifier" 
                    required 
                    autofocus 
                    autocomplete="username" 
                    placeholder="e.g. 2026-01-0001 / name@grc.edu.ph"
                    class="w-full px-3.5 py-2.5 bg-white text-zinc-900 font-semibold text-sm rounded-xl border {{ $errors->has('identifier') ? 'border-rose-400 focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20' : 'border-zinc-300 focus:border-[#9b0000] focus:ring-2 focus:ring-[#9b0000]/25' }} focus:outline-hidden transition-all placeholder:text-zinc-400 placeholder:font-normal shadow-2xs"
                />
            </div>
        </div>

        <!-- Password -->
        <div class="flex flex-col gap-1.5 text-left" x-data="{ showPassword: false }">
            <div class="flex items-center justify-between">
                <label for="password" class="block text-xs font-bold uppercase tracking-wider text-[#7a0000]">
                    {{ __('Password') }}
                </label>
                @if (Route::has('password.request'))
                    <a class="text-xs font-semibold text-[#9b0000] hover:text-[#7a0000] hover:underline transition-colors" href="{{ route('password.request') }}" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </a>
                @endif
            </div>
            <div class="relative">
                <input 
                    wire:model="password" 
                    id="password"
                    :type="showPassword ? 'text' : 'password'"
                    name="password" 
                    required 
                    autocomplete="current-password" 
                    placeholder="Enter your password"
                    class="w-full px-3.5 py-2.5 pr-10 bg-white text-zinc-900 font-semibold text-sm rounded-xl border {{ $errors->has('identifier') ? 'border-rose-400 focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20' : 'border-zinc-300 focus:border-[#9b0000] focus:ring-2 focus:ring-[#9b0000]/25' }} focus:outline-hidden transition-all placeholder:text-zinc-400 placeholder:font-normal shadow-2xs"
                />
                <button 
                    type="button" 
                    @click="showPassword = !showPassword" 
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-zinc-400 hover:text-[#9b0000] transition-colors focus:outline-hidden cursor-pointer"
                    tabindex="-1"
                    title="Toggle password visibility"
                >
                    <svg x-show="!showPassword" xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg x-show="showPassword" xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="display: none;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                    </svg>
                </button>
            </div>
            @error('password')
                <p class="text-xs text-rose-600 font-semibold">{{ $message }}</p>
            @enderror
        </div>

        <!-- Remember Me -->
        <div class="flex items-center gap-2">
            <input 
                wire:model="remember" 
                id="remember" 
                type="checkbox" 
                class="size-4 rounded-md border-zinc-300 text-[#7a0000] focus:ring-[#9b0000] accent-[#7a0000] cursor-pointer"
            />
            <label for="remember" class="text-xs font-bold text-zinc-700 select-none cursor-pointer">
                {{ __('Remember me') }}
            </label>
        </div>

        <div class="flex flex-col gap-3 pt-2">
            <button 
                type="submit" 
                :disabled="isSubmitting"
                :class="isSubmitting ? 'pointer-events-none opacity-60 cursor-not-allowed' : 'cursor-pointer'"
                wire:offline.attr="disabled"
                class="w-full py-3 px-4 bg-[#7a0000] hover:bg-[#9b0000] active:bg-[#600000] text-white font-bold text-sm rounded-xl shadow-md hover:shadow-lg transition-all duration-150 flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed"
            >
                <span x-show="!isSubmitting">{{ __('Log in') }}</span>
                <span x-show="isSubmitting" x-cloak class="inline-flex items-center gap-2">
                    <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                    <span>{{ __('Logging in…') }}</span>
                </span>
            </button>
            <a 
                href="{{ route('home') }}" 
                class="w-full py-2.5 px-4 text-[#7a0000] hover:text-[#9b0000] hover:bg-zinc-100/80 font-bold rounded-xl transition-all duration-150 flex items-center justify-center gap-2 text-xs" 
                wire:navigate
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                <span>{{ __('Back to Home Page') }}</span>
            </a>

            @error('identifier')
                <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-700 text-xs flex items-start gap-2.5 text-left">
                    <flux:icon icon="exclamation-circle" class="size-4.5 text-rose-600 shrink-0 mt-0.5" />
                    <div class="flex-1 leading-relaxed">
                        <p class="font-bold">{{ $message }}</p>
                        @if(!str_contains($message, 'deactivated') && !str_contains($message, 'throttle'))
                            <p class="text-rose-600/90 text-[11px] mt-1 font-medium">
                                Hint: Check for typos in your Student ID or email, and ensure Caps Lock is off for your password.
                            </p>
                        @endif
                    </div>
                </div>
            @enderror
        </div>
    </form>
</div>
