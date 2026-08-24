<div class="space-y-8 text-left w-full">
    <!-- Header with real text + Progress Counter Badge skeleton -->
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <flux:heading size="xl" level="1" class="text-left font-black tracking-tight">Evaluator Dashboard</flux:heading>
        </div>
        <div class="h-7 bg-zinc-200 dark:bg-zinc-800 rounded-full w-36 shimmer shrink-0"></div>
    </div>

    <!-- Active Evaluation Period Status Banner -->
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

    <!-- Tab Navigation Bar -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-2 shadow-xs flex flex-wrap gap-2">
        <div class="h-9 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-36 shimmer"></div>
        <div class="h-9 bg-zinc-100 dark:bg-zinc-800/40 rounded-lg w-32"></div>
        <div class="h-9 bg-zinc-100 dark:bg-zinc-800/40 rounded-lg w-36"></div>
    </div>

    <!-- Evaluatees Table Container -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
            <div class="space-y-1">
                <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 shimmer"></div>
                <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-64 shimmer"></div>
            </div>
            <div class="w-full sm:w-64 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
        </div>

        <x-skeleton type="table" :rows="6" :cols="5" :colWidths="['w-56', 'w-32', 'w-36', 'w-24', 'w-24']" />
    </div>
</div>
