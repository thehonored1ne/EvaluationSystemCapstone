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

    <!-- Top 4 Summary Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 w-full">
        @for ($i = 0; $i < 4; $i++)
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs p-5 flex flex-col">
                <div class="h-3.5 w-32 bg-zinc-200 dark:bg-zinc-800 rounded shimmer"></div>
                <div class="flex items-baseline gap-2 mt-3">
                    <div class="h-8 w-24 bg-zinc-200 dark:bg-zinc-800 rounded shimmer"></div>
                    <div class="h-5 w-16 bg-zinc-100 dark:bg-zinc-800 rounded"></div>
                </div>
                <div class="h-3 w-40 bg-zinc-100 dark:bg-zinc-800/60 rounded mt-3"></div>
            </div>
        @endfor
    </div>

    <!-- 6 Category Tabs Bar (Exact 1:1 match with grid-cols-2 sm:grid-cols-3 lg:grid-cols-6) -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-1.5 sm:p-2 shadow-xs grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 w-full">
        <div class="h-9 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-full shimmer"></div>
        <div class="h-9 bg-zinc-100 dark:bg-zinc-800/40 rounded-lg w-full"></div>
        <div class="h-9 bg-zinc-100 dark:bg-zinc-800/40 rounded-lg w-full"></div>
        <div class="h-9 bg-zinc-100 dark:bg-zinc-800/40 rounded-lg w-full"></div>
        <div class="h-9 bg-zinc-100 dark:bg-zinc-800/40 rounded-lg w-full"></div>
        <div class="h-9 bg-zinc-100 dark:bg-zinc-800/40 rounded-lg w-full"></div>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 shadow-xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3 flex-1">
            <div class="flex-1 min-w-[240px] h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
            <div class="w-full sm:w-56 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
            <div class="w-full sm:w-44 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
        </div>
        <div class="flex items-center gap-2 self-end md:self-auto">
            <div class="w-20 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
        </div>
    </div>

    <!-- Tracking Table -->
    <x-skeleton type="table" :rows="7" :cols="6" :colWidths="['w-48', 'w-36', 'w-32', 'w-28', 'w-24', 'w-24']" />
</div>
