<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Evaluation System</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @fluxAppearance
</head>
<body x-data class="relative min-h-screen flex flex-col items-center justify-center font-sans antialiased overflow-x-hidden overflow-y-auto px-4 py-8 pb-32 sm:pb-24" style="background-image: url('{{ asset('welcome-bg.jpg') }}'); background-size: cover; background-position: center; background-repeat: no-repeat;">
    
    <!-- Premium background overlay for depth & readability -->
    <div class="absolute inset-0 bg-black/25 dark:bg-black/45 backdrop-blur-[2px] pointer-events-none"></div>

    @php
        $activeSemester = \App\Models\Semester::getActive();
        $announcement = null;

        if ($activeSemester) {
            $starts = $activeSemester->evaluation_starts_at;
            $ends = $activeSemester->evaluation_ends_at;
            $now = \Illuminate\Support\Carbon::now('Asia/Manila');

            if ($activeSemester->is_evaluation_open) {
                if ($starts && $ends) {
                    $startsManila = $starts->copy()->timezone('Asia/Manila');
                    $endsManila = $ends->copy()->timezone('Asia/Manila');

                    if ($now->lt($startsManila)) {
                        $announcement = 'Evaluation is open from ' . $startsManila->format('g:ia n/j/y') . ' to ' . $endsManila->format('g:ia n/j/y');
                    } elseif ($now->gt($endsManila)) {
                        $announcement = 'Evaluation is closed ' . $endsManila->diffForHumans();
                    } else {
                        $announcement = 'Evaluation is open from ' . $startsManila->format('g:ia n/j/y') . ' to ' . $endsManila->format('g:ia n/j/y');
                    }
                } else {
                    $announcement = 'Evaluation is currently open';
                }
            } else {
                if ($ends) {
                    $endsManila = $ends->copy()->timezone('Asia/Manila');
                    $announcement = 'Evaluation is closed ' . $endsManila->diffForHumans();
                } else {
                    $announcement = 'Evaluation is currently closed';
                }
            }
        } else {
            $announcement = 'Evaluation is currently closed';
        }
    @endphp

    <!-- Center Hero Section -->
    <div class="relative z-10 flex flex-col items-center justify-center w-full max-w-3xl px-6 text-center transition-opacity opacity-100 duration-700 starting:opacity-0">
        <main class="w-full flex flex-col items-center justify-center">

            <!-- 1-Liner Evaluation Announcement Pill (Positioned well above logo) -->
            @if($announcement)
                <div class="mb-14 sm:mb-20 flex justify-center">
                    <span class="inline-flex items-center px-5 py-2 rounded-full bg-black/45 backdrop-blur-md border border-white/20 text-white/95 text-xs sm:text-sm font-semibold tracking-wide shadow-lg drop-shadow-[0_2px_8px_rgba(0,0,0,0.5)]">
                        {{ $announcement }}
                    </span>
                </div>
            @endif

            <!-- Brand Logo (Enlarged with Luminous Glow) -->
            <div class="mb-6 sm:mb-8 flex justify-center">
                <img src="{{ asset('GRC-o-Evaluation-LOGO.webp') }}" alt="Global Reciprocal Colleges Online Evaluation Logo" class="h-32 sm:h-40 md:h-48 w-auto object-contain drop-shadow-[0_6px_35px_rgba(255,255,255,0.65)] transition-transform duration-300 hover:scale-105" />
            </div>

            <!-- Typewriter Tagline Animation (Zero CLS: Cycles Word by Word: Type -> Delete -> Next Word) -->
            <div 
                x-data="{
                    words: ['TOUCHING HEARTS', 'RENEWING MINDS', 'TRANSFORMING LIVES'],
                    wordIndex: 0,
                    text: '',
                    charIndex: 0,
                    isDeleting: false,
                    type() {
                        const currentWord = this.words[this.wordIndex];

                        if (!this.isDeleting) {
                            this.text = currentWord.substring(0, this.charIndex + 1);
                            this.charIndex++;

                            if (this.charIndex === currentWord.length) {
                                this.isDeleting = true;
                                setTimeout(() => this.type(), 2000);
                                return;
                            }
                            setTimeout(() => this.type(), 80);
                        } else {
                            this.text = currentWord.substring(0, this.charIndex - 1);
                            this.charIndex--;

                            if (this.charIndex === 0) {
                                this.isDeleting = false;
                                this.wordIndex = (this.wordIndex + 1) % this.words.length;
                                setTimeout(() => this.type(), 400);
                                return;
                            }
                            setTimeout(() => this.type(), 40);
                        }
                    }
                }"
                x-init="type()"
                class="h-8 mb-16 sm:mb-20 flex items-center justify-center px-4 text-center overflow-hidden"
            >
                <p class="text-white text-xs sm:text-sm md:text-base font-extrabold tracking-[0.25em] sm:tracking-[0.3em] uppercase leading-none drop-shadow-[0_2px_12px_rgba(0,0,0,0.7)] whitespace-nowrap">
                    <span x-text="text"></span><span class="inline-block w-[2.5px] h-[1.15em] ml-1.5 bg-white animate-pulse align-middle"></span>
                </p>
            </div>
            
            <!-- Primary Action Button (Log In / Dashboard) -->
            <div>
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center px-10 py-4 bg-white text-black hover:bg-zinc-100 font-semibold tracking-wide rounded-full shadow-2xl transition-all duration-300 hover:scale-105 active:scale-95 text-sm gap-2">
                            Go to Dashboard
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"></path>
                            </svg>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-10 py-4 bg-white text-black hover:bg-zinc-100 font-bold tracking-wide rounded-full shadow-2xl transition-all duration-300 hover:scale-105 active:scale-95 text-md">
                            Log In
                        </a>
                    @endauth
                @endif
            </div>
            
        </main>
    </div>

    <!-- Responsive Bottom Footer & Action Bar -->
    <div class="fixed bottom-0 inset-x-0 z-20 p-3 sm:p-5 flex flex-col sm:flex-row items-center justify-between gap-2.5 sm:gap-3 pointer-events-none">
        
        <!-- Left: Copyright & Terms Link -->
        <div class="pointer-events-auto flex flex-wrap items-center justify-center gap-1.5 sm:gap-2 text-white/80 text-[11px] sm:text-xs font-medium backdrop-blur-md px-3.5 sm:px-4 py-1.5 rounded-full bg-black/40 border border-white/15 shadow-lg">
            <span>© 2026 GRC</span>
            <span class="opacity-60">•</span>
            <button 
                type="button" 
                onclick="window.dispatchEvent(new CustomEvent('open-terms-modal'))"
                @click="$dispatch('open-terms-modal')" 
                class="text-white hover:underline underline-offset-2 transition-colors cursor-pointer font-semibold"
            >
                Terms & Privacy Policy
            </button>
        </div>

        <!-- Right: Report an Issue Floating Button -->
        <div class="pointer-events-auto shrink-0">
            <a 
                href="https://grc-reporting.vercel.app" 
                target="_blank" 
                rel="noopener noreferrer" 
                class="inline-flex items-center justify-center px-4 sm:px-5 py-1.5 sm:py-2 bg-black/40 hover:bg-black/60 text-white font-medium tracking-wide rounded-full border border-white/20 backdrop-blur-md shadow-lg transition-all duration-300 hover:scale-105 active:scale-95 text-[11px] sm:text-xs gap-1.5"
            >
                <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-white/95" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <span>Report an Issue</span>
            </a>
        </div>
    </div>

    <x-terms-modal />
    @livewireScripts
    @fluxScripts
</body>
</html>
