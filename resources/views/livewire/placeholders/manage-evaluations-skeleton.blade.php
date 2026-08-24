<div class="w-full flex flex-col gap-6 text-left">
    <!-- Header Banner with real text + header actions skeleton -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 w-full">
        <div>
            <flux:heading size="xl" level="1" class="text-left font-black tracking-tight">Completion Tracking</flux:heading>
        </div>

        <!-- Header Action Controls Skeleton -->
        <div class="flex items-center gap-3">
            <div class="h-9 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-36 shimmer"></div>
        </div>
    </div>

    <!-- Active Academic Period Indicator Banner Skeleton -->
    <div class="p-4 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-zinc-200 dark:border-zinc-800 flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-2.5">
            <span class="size-2.5 rounded-full bg-zinc-300 dark:bg-zinc-700"></span>
            <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded w-64 shimmer"></div>
        </div>
        <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-full w-28 shimmer"></div>
    </div>

    <!-- Top 4 Summary Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
        <x-skeleton type="stat-card" />
        <x-skeleton type="stat-card" :hasProgress="true" />
        <x-skeleton type="stat-card" :hasProgress="true" />
        <x-skeleton type="stat-card" />
    </div>

    <!-- 6 Category Tabs Bar -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-2 shadow-xs flex flex-wrap gap-2">
        <div class="h-9 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-28 shimmer"></div>
        <div class="h-9 bg-zinc-100 dark:bg-zinc-800/40 rounded-lg w-24"></div>
        <div class="h-9 bg-zinc-100 dark:bg-zinc-800/40 rounded-lg w-32"></div>
        <div class="h-9 bg-zinc-100 dark:bg-zinc-800/40 rounded-lg w-36"></div>
        <div class="h-9 bg-zinc-100 dark:bg-zinc-800/40 rounded-lg w-28"></div>
        <div class="h-9 bg-zinc-100 dark:bg-zinc-800/40 rounded-lg w-20"></div>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 shadow-xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3 flex-1">
            <div class="flex-1 min-w-[240px] h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
            <div class="w-full sm:w-48 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
            <div class="w-full sm:w-44 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
        </div>
    </div>

    <!-- Tracking Table -->
    <x-skeleton type="table" :rows="7" :cols="6" :colWidths="['w-48', 'w-36', 'w-32', 'w-28', 'w-24', 'w-24']" />
</div>
