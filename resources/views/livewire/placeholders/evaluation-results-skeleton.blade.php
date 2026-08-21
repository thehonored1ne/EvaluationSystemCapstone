<div class="space-y-6 text-left w-full">
    <!-- Header with real text -->
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <flux:heading size="xl" level="1">Evaluation Results Directory</flux:heading>
            <flux:subheading>Search and inspect detailed submission results and qualitative comments across departments.</flux:subheading>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 sm:p-6 shadow-xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3 flex-1">
            <div class="flex-1 min-w-[220px] h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
            <div class="w-full sm:w-48 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
            <div class="w-full sm:w-40 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
            <div class="w-full sm:w-48 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
        </div>
    </div>

    <!-- Directory Table -->
    <x-skeleton type="table" :rows="8" :cols="5" :colWidths="['w-56', 'w-32', 'w-48', 'w-32', 'w-24']" />

    <!-- Pagination Skeleton -->
    <div class="flex justify-between items-center pt-2">
        <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded w-44 shimmer"></div>
        <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-48 shimmer"></div>
    </div>
</div>
