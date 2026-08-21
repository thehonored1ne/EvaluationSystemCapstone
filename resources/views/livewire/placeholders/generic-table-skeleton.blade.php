<div class="w-full flex flex-col gap-6 text-left">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <!-- Title Skeleton -->
        <div class="space-y-1">
            <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-md w-56 shimmer"></div>
            <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-96 shimmer mt-1"></div>
        </div>
        <!-- Create Button Skeleton -->
        <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-36 shimmer shrink-0"></div>
    </div>
    
    <!-- Filters Bar Skeleton -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 shadow-xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3 flex-1">
            <div class="flex-1 min-w-[220px] h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
            <div class="w-full sm:w-48 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
        </div>
    </div>
    
    <!-- Table Skeleton -->
    <x-skeleton type="table" :rows="7" :cols="5" :colWidths="['w-48', 'w-36', 'w-32', 'w-24', 'w-20']" />

    <!-- Pagination Skeleton -->
    <div class="flex justify-between items-center pt-2">
        <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded w-44 shimmer"></div>
        <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-48 shimmer"></div>
    </div>
</div>
