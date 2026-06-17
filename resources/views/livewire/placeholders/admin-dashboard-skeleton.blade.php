<div class="space-y-6">
    <!-- Header spacing -->
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div class="space-y-1 text-left">
            <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 shimmer"></div>
            <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-96 shimmer mt-1"></div>
        </div>
        <div class="w-full sm:w-auto flex justify-end">
            <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-full w-60 shimmer"></div>
        </div>
    </div>

    <!-- Top Row Cards Skeletons -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <x-skeleton type="card" />
        <x-skeleton type="card" />
        <x-skeleton type="card" />
        <x-skeleton type="card" />
    </div>

    <!-- Middle Row: Active Window and AI Breakdown Skeletons -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="p-6 bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-2xl space-y-4">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/3 shimmer"></div>
                <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-full w-20 shimmer"></div>
            </div>
            <div class="h-32 bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200/50 dark:border-zinc-800/50 rounded-xl shimmer"></div>
            <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/4 mt-4 shimmer"></div>
        </div>
        
        <div class="p-6 bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-2xl space-y-4">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/3 shimmer"></div>
                <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-full w-20 shimmer"></div>
            </div>
            <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-full w-full shimmer mt-2"></div>
            <div class="grid grid-cols-3 gap-2 mt-4">
                <div class="h-20 bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200/50 dark:border-zinc-800/50 rounded-xl shimmer"></div>
                <div class="h-20 bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200/50 dark:border-zinc-800/50 rounded-xl shimmer"></div>
                <div class="h-20 bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200/50 dark:border-zinc-800/50 rounded-xl shimmer"></div>
            </div>
        </div>
    </div>
</div>
