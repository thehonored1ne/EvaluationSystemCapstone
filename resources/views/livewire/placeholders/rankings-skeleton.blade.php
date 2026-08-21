<div class="space-y-8 text-left w-full">
    <!-- Header with real text + Semester select skeleton -->
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <flux:heading size="xl" level="1">Institutional Rankings</flux:heading>
            <flux:subheading>Top faculty performance scores and college department average comparisons.</flux:subheading>
        </div>
        <div class="w-full sm:w-56 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
    </div>

    <!-- 4 Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <x-skeleton type="stat-card" />
        <x-skeleton type="stat-card" />
        <x-skeleton type="stat-card" />
        <x-skeleton type="stat-card" />
    </div>

    <!-- Top 3 Podium Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="p-6 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-xs space-y-3 flex flex-col items-center text-center">
            <div class="size-12 rounded-full bg-zinc-200 dark:bg-zinc-800 shimmer"></div>
            <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded w-32 shimmer"></div>
            <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded w-24 shimmer"></div>
            <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded w-20 shimmer"></div>
        </div>
        <div class="p-6 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-xs space-y-3 flex flex-col items-center text-center">
            <div class="size-12 rounded-full bg-zinc-200 dark:bg-zinc-800 shimmer"></div>
            <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded w-32 shimmer"></div>
            <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded w-24 shimmer"></div>
            <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded w-20 shimmer"></div>
        </div>
        <div class="p-6 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-xs space-y-3 flex flex-col items-center text-center">
            <div class="size-12 rounded-full bg-zinc-200 dark:bg-zinc-800 shimmer"></div>
            <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded w-32 shimmer"></div>
            <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded w-24 shimmer"></div>
            <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded w-20 shimmer"></div>
        </div>
    </div>

    <!-- Ranked Faculty Table -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
            <div class="space-y-1">
                <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 shimmer"></div>
                <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-64 shimmer"></div>
            </div>
            <div class="w-full sm:w-64 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
        </div>
        <x-skeleton type="table" :rows="6" :cols="5" :colWidths="['w-16', 'w-48', 'w-36', 'w-28', 'w-24']" />
    </div>
</div>
