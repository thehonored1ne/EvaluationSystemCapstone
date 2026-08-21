<div class="space-y-8 text-left w-full">
    <!-- Header with real text + Progress Badge skeleton -->
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <flux:heading size="xl" level="1" class="text-left font-black tracking-tight">Student Dashboard</flux:heading>
            <flux:subheading class="text-left text-zinc-500 dark:text-zinc-400">
                Evaluate your assigned teachers for the active semester.
            </flux:subheading>
        </div>
        <div class="h-7 bg-zinc-200 dark:bg-zinc-800 rounded-full w-36 shimmer shrink-0"></div>
    </div>

    <!-- Active Schedule Status Banner -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 sm:p-6 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
        <div class="space-y-2">
            <div class="flex items-center gap-3">
                <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 shimmer"></div>
                <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-full w-24 shimmer"></div>
            </div>
            <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-64 shimmer"></div>
        </div>
        <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-full w-32 shimmer shrink-0"></div>
    </div>

    <!-- Enrolled Classes Grid (Cards) -->
    <div class="space-y-4">
        <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
            <div>
                <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">Enrolled Classes</h3>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">List of classes enrolled in for the active semester.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @for ($i = 0; $i < 6; $i++)
                <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-6 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
                    <div class="space-y-3">
                        <div class="flex justify-between items-start">
                            <div class="h-6 w-24 bg-zinc-200 dark:bg-zinc-800 rounded-md shimmer"></div>
                            <div class="h-5 w-16 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
                        </div>
                        <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-4/5 shimmer"></div>
                        <div class="flex items-center gap-3 pt-2">
                            <div class="size-9 rounded-full bg-zinc-200 dark:bg-zinc-800 shimmer shrink-0"></div>
                            <div class="space-y-1 flex-1">
                                <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-32 shimmer"></div>
                                <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded-md w-24 shimmer"></div>
                            </div>
                        </div>
                    </div>
                    <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 flex justify-between items-center">
                        <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded w-24 shimmer"></div>
                        <div class="h-9 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-32 shimmer"></div>
                    </div>
                </div>
            @endfor
        </div>
    </div>
</div>
