<div class="w-full flex flex-col gap-6 text-left">
    <!-- Header Section -->
    <div class="flex justify-between items-center w-full">
        <div>
            <flux:heading size="xl" level="1">Evaluation Settings</flux:heading>
        </div>
    </div>

    <!-- SECTION 1: Academic Years & Semesters Period Management Table -->
    <div id="academic-periods-section" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 sm:p-6 shadow-xs flex flex-col gap-4 sm:gap-6 w-full">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-100 dark:border-zinc-800 pb-4">
            <div>
                <h2 class="text-base font-bold text-zinc-900 dark:text-zinc-100">Academic Years & Semesters</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Catalog of institutional academic years and semester evaluation periods.</p>
            </div>
            <div class="h-9 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-40 shimmer"></div>
        </div>

        <!-- Search & Filter Toolbar Skeleton -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
            <div class="h-8 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg w-full sm:w-64 shimmer"></div>
            <div class="h-8 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg w-full sm:w-44 shimmer"></div>
        </div>

        <x-skeleton type="table" :rows="5" :cols="5" :colWidths="['w-36', 'w-36', 'w-28', 'w-24', 'w-24']" />
    </div>

    <!-- SECTION 2: Evaluation Weights & Questionnaire Parts Allocation -->
    <div id="weights-section" class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 sm:p-6 shadow-xs flex flex-col gap-6 w-full border-l-[5px] border-l-[#9b0000] dark:border-l-[#e07a7a]">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-zinc-100 dark:border-zinc-800 pb-4">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-zinc-900 dark:text-zinc-100">Evaluation Weights & Questionnaire Parts Allocation</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Configure overall scale, target percentage weights, and specific questionnaire criteria parts directly for each category.</p>
            </div>
            <!-- Tab switcher skeleton -->
            <div class="flex gap-2">
                <div class="h-7 w-28 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
                <div class="h-7 w-36 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
            </div>
        </div>

        <!-- Tab Navigation Switcher Skeleton -->
        <div class="flex border-b border-zinc-200 dark:border-zinc-800 gap-4 pb-0">
            <div class="h-8 w-44 bg-zinc-200 dark:bg-zinc-800 rounded-t shimmer"></div>
            <div class="h-8 w-48 bg-zinc-100 dark:bg-zinc-800/50 rounded-t shimmer"></div>
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
            <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-44 shimmer"></div>
        </div>
    </div>
</div>
