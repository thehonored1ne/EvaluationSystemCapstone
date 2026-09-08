<div class="flex flex-col gap-6 sm:gap-8 w-full max-w-6xl mx-auto px-2 sm:px-4 md:px-6 py-3 sm:py-6">
    <!-- Header with real text + Progress Counter Badge skeleton -->
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm">
        <div class="space-y-2">
            <div class="h-7 bg-zinc-200 dark:bg-zinc-800 rounded-md w-64 shimmer"></div>
            <div class="h-4 bg-zinc-100 dark:bg-zinc-800/60 rounded-md w-48 shimmer"></div>
        </div>
        <div class="h-7 bg-zinc-200 dark:bg-zinc-800 rounded-full w-32 shimmer shrink-0"></div>
    </div>

    <!-- Segmented In-Page Tab Navigation Chips Skeleton -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar sm:flex-wrap">
        <div class="h-10 bg-[#9b0000]/30 rounded-xl w-28 shimmer shrink-0"></div>
        <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-36 shimmer shrink-0"></div>
        <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-36 shimmer shrink-0"></div>
    </div>

    <!-- Main Card Container -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 sm:p-6 shadow-xs space-y-5">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <div class="space-y-1.5">
                <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 shimmer"></div>
                <div class="h-3.5 bg-zinc-100 dark:bg-zinc-800/60 rounded-md w-64 shimmer"></div>
            </div>
            <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-full w-24 shimmer shrink-0"></div>
        </div>

        <!-- Progress Bar Skeleton -->
        <div class="bg-zinc-50 dark:bg-zinc-800/40 p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-700 space-y-2">
            <div class="flex justify-between items-center">
                <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-32 shimmer"></div>
                <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-12 shimmer"></div>
            </div>
            <div class="w-full bg-zinc-200 dark:bg-zinc-700 h-2 rounded-full overflow-hidden">
                <div class="bg-[#9b0000]/50 h-2 w-1/3 rounded-full shimmer"></div>
            </div>
        </div>

        <!-- Table Skeleton -->
        <x-skeleton type="table" :rows="4" :cols="3" :colWidths="['w-48', 'w-28', 'w-24']" />
    </div>
</div>
