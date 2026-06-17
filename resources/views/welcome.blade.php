<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Evaluation System</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,600,700|inter:300,400,500,600" rel="stylesheet" />
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="relative min-h-screen flex flex-col items-center justify-center font-sans antialiased overflow-hidden" style="background-image: url('{{ asset('welcome-bg.jpg') }}'); background-size: cover; background-position: center; background-repeat: no-repeat;">
    
    <!-- Premium background overlay for depth & readability -->
    <div class="absolute inset-0 bg-black/25 dark:bg-black/45 backdrop-blur-[2px] pointer-events-none"></div>

    <!-- Center Hero Section -->
    <div class="relative z-10 flex flex-col items-center justify-center w-full max-w-2xl px-6 text-center transition-opacity opacity-100 duration-700 starting:opacity-0">
        <main class="w-full flex flex-col items-center justify-center">
            
            <!-- Modern Floating Brand Icon -->
            <div class="mb-8">
                <x-app-logo-icon class="w-16 h-16 fill-current text-white drop-shadow-[0_2px_10px_rgba(0,0,0,0.2)]" />
            </div>
            
            <!-- Title (Elegant Playfair Display Font) -->
            <h1 class="text-5xl md:text-6xl font-medium tracking-normal text-white mb-6 drop-shadow-[0_2px_15px_rgba(0,0,0,0.3)] leading-tight" style="font-family: 'Playfair Display', serif;">
                GRC Evaluation System
            </h1>
            
            <!-- Subtitle (Inter Font, Elegant light tracking) -->
            <p class="text-white/90 dark:text-zinc-200 text-base md:text-lg font-light tracking-wide leading-relaxed mb-12 max-w-md drop-shadow-[0_1px_8px_rgba(0,0,0,0.2)]">
                Welcome to the central portal for academic and performance evaluations.
            </p>
            
            <!-- Primary Action Button (Log In / Dashboard) -->
            <div>
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center px-10 py-4 bg-white text-zinc-950 hover:bg-zinc-100 font-medium tracking-wide rounded-full shadow-2xl transition-all duration-300 hover:scale-105 active:scale-95 hover:shadow-white/15 text-sm">
                            Go to Dashboard
                            <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"></path>
                            </svg>
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-10 py-4 bg-white text-zinc-950 hover:bg-zinc-100 font-medium tracking-wide rounded-full shadow-2xl transition-all duration-300 hover:scale-105 active:scale-95 hover:shadow-white/15 text-sm">
                            Access Portal
                            <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"></path>
                            </svg>
                        </a>
                    @endauth
                @endif
            </div>
            
        </main>
    </div>
    
</body>
</html>
