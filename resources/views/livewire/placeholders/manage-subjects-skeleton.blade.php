<div class="w-full flex flex-col gap-8 text-left">
    <!-- Header Section with real text + 4 action buttons skeleton -->
    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 w-full text-left">
        <div class="flex flex-col items-start text-left">
            <flux:heading size="xl" level="1" class="text-left">Manage Subjects</flux:heading>
            <flux:subheading class="text-left">Curriculum catalog, year level curriculum placement, semester offerings, and section class assignments.</flux:subheading>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-36 shimmer"></div>
            <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-32 shimmer"></div>
            <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-32 shimmer"></div>
            <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-28 shimmer"></div>
        </div>
    </div>

    <!-- Top Row 3 Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <x-skeleton type="stat-card" />
        <x-skeleton type="stat-card" />
        <x-skeleton type="stat-card" />
    </div>

    <!-- Filters Bar -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 shadow-xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3 flex-1">
            <div class="flex-1 min-w-[220px] h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
            <div class="w-full sm:w-48 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
            <div class="w-full sm:w-48 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
            <div class="w-full sm:w-48 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
        </div>
    </div>

    <!-- Subjects Table -->
    <x-skeleton type="table" :rows="8" :cols="5" :colWidths="['w-32', 'w-64', 'w-24', 'w-32', 'w-20']" />
</div>
