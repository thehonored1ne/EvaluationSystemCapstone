<div class="w-full flex flex-col gap-6">
    <div class="flex justify-between items-center">
        <!-- Title Skeleton -->
        <div class="h-9 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 shimmer"></div>
        <!-- Create Button Skeleton -->
        <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-md w-36 shimmer"></div>
    </div>
    
    <!-- Filters Bar Skeleton -->
    <div class="flex flex-col md:flex-row gap-4 items-end bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-lg border border-gray-200 dark:border-zinc-700">
        <div class="flex-1 w-full min-w-[300px] h-10 bg-zinc-150 dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-700 rounded-md shimmer"></div>
        <div class="w-full md:w-64 h-10 bg-zinc-150 dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-700 rounded-md shimmer"></div>
        <div class="w-10 h-10 bg-zinc-150 dark:bg-zinc-700 border border-zinc-200 dark:border-zinc-700 rounded-md shimmer"></div>
    </div>
    
    <!-- Table Skeleton -->
    <x-skeleton type="table" :rows="5" :cols="6" />

    <!-- Pagination Skeleton -->
    <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/3 shimmer mt-2"></div>
</div>
