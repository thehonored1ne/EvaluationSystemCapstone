<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="relative min-h-screen font-sans antialiased flex flex-col items-center justify-center overflow-x-hidden" style="background-image: url('{{ asset('welcome-bg.jpg') }}'); background-size: cover; background-position: center; background-repeat: no-repeat;">
        <!-- Background overlay for depth & readability (same as welcome page) -->
        <div class="absolute inset-0 bg-black/35 dark:bg-black/55 backdrop-blur-[3px] pointer-events-none"></div>

        <div class="relative z-10 flex min-h-svh w-full flex-col items-center justify-center p-4 sm:p-6 md:p-10">
            <div class="flex w-full max-w-md flex-col gap-6 bg-white p-6 sm:p-8 rounded-2xl shadow-2xl border border-zinc-200" style="background-color: #ffffff !important; opacity: 1 !important;">
                <a href="{{ route('home') }}" class="flex flex-col items-center justify-center gap-2 font-medium group py-1" wire:navigate>
                    <img src="{{ asset('GRC-o-Evaluation-LOGO.webp') }}" alt="Global Reciprocal Colleges Online Evaluation Logo" class="h-16 w-auto object-contain transition-transform duration-200 group-hover:scale-105" />
                    <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>
        @fluxScripts
    </body>
</html>
