<div class="flex flex-col gap-8 w-full max-w-5xl mx-auto px-4 py-6">
    <!-- Navigation Tabs Skeleton -->
    <div class="flex border-b border-zinc-200 dark:border-zinc-850">
        <div class="px-5 py-2.5 border-b-2 border-indigo-650 dark:border-indigo-400">
            <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-24 shimmer"></div>
        </div>
        <div class="px-5 py-2.5 border-b-2 border-transparent">
            <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-24 shimmer"></div>
        </div>
    </div>

    <!-- Filters Bar Skeleton -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm">
        <div class="space-y-2">
            <div class="h-7 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 shimmer"></div>
            <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-96 shimmer"></div>
        </div>

        <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto shrink-0">
            <div class="w-full md:w-64 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md shimmer"></div>
            <div class="w-full md:w-48 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md shimmer"></div>
        </div>
    </div>

    <!-- Individual Report Mock Skeleton -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl shadow-xl p-8 md:p-12 space-y-8 flex flex-col gap-8">
        <div class="text-center border-b-2 border-zinc-150 pb-6 flex flex-col items-center justify-center gap-2">
            <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-md w-72 shimmer"></div>
            <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 mt-1 shimmer"></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-zinc-50 dark:bg-zinc-800/20 p-6 rounded-xl border border-zinc-150 dark:border-zinc-800">
            <div class="space-y-2">
                <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/3 shimmer"></div>
                <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-3/4 shimmer"></div>
            </div>
            <div class="space-y-2">
                <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/3 shimmer"></div>
                <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-3/4 shimmer"></div>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <x-skeleton type="card" />
            <x-skeleton type="card" />
            <x-skeleton type="card" />
            <x-skeleton type="card" />
        </div>
        <div class="space-y-4">
            <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 shimmer"></div>
            <x-skeleton type="table" :rows="4" :cols="3" />
        </div>
    </div>
</div>
