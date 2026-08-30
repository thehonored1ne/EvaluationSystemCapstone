<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full light" data-theme="light" style="color-scheme: light;">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Error') — Evaluation System</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full flex flex-col items-center justify-between bg-white text-zinc-900 font-sans antialiased selection:bg-red-500 selection:text-white px-4 py-8 sm:py-12">

    <!-- Top Brand Header -->
    <header class="w-full max-w-5xl flex justify-center sm:justify-start">
        <a href="{{ url('/') }}" class="inline-flex items-center gap-3 transition-opacity hover:opacity-80">
            <img src="{{ asset('GRC-o-Evaluation-LOGO.webp') }}" alt="GRC Evaluation Logo" width="440" height="237" class="h-10 sm:h-12 w-auto object-contain" />
        </a>
    </header>

    <!-- Main Content Area -->
    <main class="w-full max-w-xl flex flex-col items-center text-center my-auto py-6 sm:py-10">
        
        <!-- Illustration Slot -->
        <div class="mb-6 sm:mb-8 flex items-center justify-center w-full">
            @yield('illustration')
        </div>

        <!-- Status Code -->
        <div class="text-xs sm:text-sm font-bold tracking-widest uppercase text-[#800000] mb-2">
            @yield('code', 'Error')
        </div>

        <!-- Main Heading -->
        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-zinc-900 mb-3">
            @yield('heading', 'Something went wrong')
        </h1>

        <!-- Subtitle / Message -->
        <p class="text-zinc-600 text-sm sm:text-base leading-relaxed max-w-md mx-auto mb-8 font-normal">
            @yield('message', 'The page you requested could not be found or processed.')
        </p>

        <!-- Single Clean Go Back Button -->
        <div class="flex items-center justify-center">
            <button type="button" onclick="window.history.length > 1 ? window.history.back() : window.location.href='{{ url('/') }}'" class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-zinc-900 hover:bg-zinc-800 text-white text-sm font-semibold tracking-tight shadow-sm transition-all duration-200 hover:scale-[1.02] active:scale-[0.98] cursor-pointer">
                <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                Go Back
            </button>
        </div>

    </main>

    <!-- Footer -->
    <footer class="w-full max-w-5xl flex justify-center text-xs text-zinc-400">
        &copy; {{ date('Y') }} Global Reciprocal Colleges. All rights reserved.
    </footer>

</body>
</html>
