<footer class="mt-8 sm:mt-12 md:mt-16 w-full bg-[#e5e6eb] dark:bg-[#18181b] text-zinc-600 dark:text-zinc-400 py-3.5 sm:py-5 px-4 sm:px-6 text-center text-[11px] sm:text-xs shadow-xs border-t border-zinc-300 dark:border-zinc-800 shrink-0 print:hidden">
    <div class="w-full flex flex-col items-center justify-center gap-1 sm:gap-1.5 mx-auto leading-normal">
        <div class="flex flex-col sm:flex-row items-center justify-center gap-0.5 sm:gap-2 font-medium sm:font-semibold text-zinc-800 dark:text-zinc-200">
            <span>© 2026 Academic Evaluation System</span>
            <span class="hidden sm:inline opacity-60">&bull;</span>
            <span class="text-zinc-600 dark:text-zinc-400 sm:text-zinc-800 sm:dark:text-zinc-200">Global Reciprocal Colleges</span>
        </div>
        <div class="flex flex-wrap items-center justify-center gap-x-2.5 gap-y-1 text-zinc-500 dark:text-zinc-400 text-[10px] sm:text-xs font-medium">
            <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
                <span class="size-1.5 sm:size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>System Operational</span>
            </span>
            <span class="opacity-60">&bull;</span>
            <span class="whitespace-nowrap">v1.0.0</span>
            <span class="opacity-60">&bull;</span>
            <button 
                type="button" 
                onclick="window.dispatchEvent(new CustomEvent('open-terms-modal'))"
                @click="$dispatch('open-terms-modal')" 
                class="hover:underline hover:text-[#9b0000] dark:hover:text-[#e07a7a] transition-colors cursor-pointer whitespace-nowrap"
            >
                Terms & Privacy
            </button>
        </div>
    </div>
</footer>
