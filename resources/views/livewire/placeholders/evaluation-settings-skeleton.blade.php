<div class="w-full flex flex-col gap-6 text-left">
    <!-- Header Section -->
    <div class="flex justify-between items-center w-full">
        <div>
            <flux:heading size="xl" level="1">Evaluation Settings</flux:heading>
        </div>
    </div>

    <!-- Quick Navigation Anchor Bar (Static) -->
    <div class="bg-white dark:bg-zinc-900 p-3 sm:p-4 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-wrap items-center gap-2 sm:gap-3">
        <span class="text-xs font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500 w-full sm:w-auto">Quick Navigation:</span>
        <div class="h-7 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-32 shimmer"></div>
        <div class="h-7 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-44 shimmer"></div>
        <div class="h-7 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-40 shimmer"></div>
    </div>

    <!-- SECTION 1: Active Evaluation Status & Toggle Control Banner -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 sm:p-6 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-4 sm:gap-6 w-full">
        <div class="space-y-2">
            <div class="flex items-center gap-3">
                <h2 class="text-base sm:text-lg font-bold text-zinc-900 dark:text-zinc-100">System Access Status</h2>
                <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-full w-24 shimmer"></div>
            </div>
            <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-72 shimmer"></div>
        </div>
        <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-full sm:w-44 shimmer shrink-0"></div>
    </div>

    <!-- SECTION 2: Evaluation Window Schedule -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 sm:p-6 shadow-xs flex flex-col gap-4 sm:gap-6 w-full">
        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-800 pb-3">
            <div>
                <h2 class="text-base font-bold text-zinc-900 dark:text-zinc-100">Evaluation Window Schedule</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Configure automated start and end datetime limits for the active semester.</p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
            <div class="space-y-2">
                <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-32 shimmer"></div>
                <div class="h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
            </div>
            <div class="space-y-2">
                <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-32 shimmer"></div>
                <div class="h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
            </div>
        </div>
        <div class="flex justify-end gap-3 pt-2">
            <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-36 shimmer"></div>
            <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-32 shimmer"></div>
        </div>
    </div>

    <!-- SECTION 3: Evaluation Weight Score Card & Dynamic Target Points -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 sm:p-6 shadow-xs flex flex-col gap-6 w-full">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-zinc-100 dark:border-zinc-800 pb-4">
            <div>
                <h2 class="text-base font-bold text-zinc-900 dark:text-zinc-100">Evaluation Weights & Dynamic Max Targets</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Configure point allocation and percentage weight distribution.</p>
            </div>
            <!-- Tab switcher skeleton -->
            <div class="flex gap-2 bg-zinc-100 dark:bg-zinc-800 p-1 rounded-xl">
                <div class="h-7 w-36 bg-zinc-200 dark:bg-zinc-700 rounded-lg shimmer"></div>
                <div class="h-7 w-32 bg-transparent rounded-lg"></div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
            @for ($i = 0; $i < 5; $i++)
                <div class="p-4 rounded-xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700/60 space-y-3">
                    <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded w-28 shimmer"></div>
                    <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-md w-full shimmer"></div>
                    <div class="h-2 bg-zinc-200 dark:bg-zinc-800 rounded-full w-full shimmer"></div>
                </div>
            @endfor
        </div>

        <div class="flex justify-end pt-2">
            <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-36 shimmer"></div>
        </div>
    </div>

    <!-- SECTION 4: Academic Years & Semesters Period Management Table -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 sm:p-6 shadow-xs flex flex-col gap-6 w-full">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-100 dark:border-zinc-800 pb-4">
            <div>
                <h2 class="text-base font-bold text-zinc-900 dark:text-zinc-100">Academic Years & Semesters</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Add, configure, or activate academic periods for institutional evaluations.</p>
            </div>
            <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-44 shimmer"></div>
        </div>

        <x-skeleton type="table" :rows="5" :cols="5" :colWidths="['w-36', 'w-36', 'w-28', 'w-48', 'w-24']" />
    </div>
</div>
