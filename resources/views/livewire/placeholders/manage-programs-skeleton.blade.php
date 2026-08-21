<div class="w-full flex flex-col gap-8 text-left">
    <!-- Header Section with real text + Add Program button skeleton -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 w-full text-left">
        <div class="flex flex-col items-start text-left">
            <flux:heading size="xl" level="1" class="text-left">Manage Academic Programs</flux:heading>
            <flux:subheading class="text-left">Degree programs catalog, department assignments, program head leadership, and student allocations.</flux:subheading>
        </div>
        <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-32 shimmer shrink-0"></div>
    </div>

    <!-- 4 Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <x-skeleton type="stat-card" />
        <x-skeleton type="stat-card" />
        <x-skeleton type="stat-card" />
        <x-skeleton type="stat-card" />
    </div>

    <!-- Filters Bar -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 shadow-xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3 flex-1">
            <div class="flex-1 min-w-[240px] h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
            <div class="w-full sm:w-48 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
            <div class="w-full sm:w-48 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
        </div>
    </div>

    <!-- Programs Table -->
    <x-skeleton type="table" :rows="6" :cols="5" :colWidths="['w-48', 'w-32', 'w-48', 'w-32', 'w-20']" />
</div>
