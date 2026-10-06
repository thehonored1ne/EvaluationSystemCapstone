<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="relative min-h-screen font-sans antialiased flex flex-col items-center justify-center overflow-x-hidden" style="background-image: url('{{ asset('welcome-bg-12.webp') }}'); background-size: cover; background-position: center; background-repeat: no-repeat;">
        <!-- Background overlay to darken bg by 20% (identical to welcome page) -->
        <div class="absolute inset-0 bg-black/20 pointer-events-none"></div>

        <div class="relative z-10 flex min-h-svh w-full flex-col items-center justify-center p-3.5 sm:p-6 md:p-10">
            <div class="liquid-glass-card flex w-full max-w-sm sm:max-w-md flex-col gap-4 sm:gap-6 p-4.5 sm:p-8 rounded-2xl sm:rounded-[1.75rem]">
                <a href="{{ route('home') }}" class="relative z-10 flex flex-col items-center justify-center gap-2 font-medium group py-0.5 sm:py-1" wire:navigate>
                    <img src="{{ asset('GRC-o-Evaluation-LOGO3.webp') }}" alt="Global Reciprocal Colleges Online Evaluation Logo" class="h-16 sm:h-24 md:h-28 w-auto object-contain transition-transform duration-200 group-hover:scale-105" />
                    <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                </a>
                <div class="relative z-10 flex flex-col gap-4 sm:gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
