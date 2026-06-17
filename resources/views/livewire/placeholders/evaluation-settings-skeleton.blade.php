<div class="w-full flex flex-col gap-8">
    <div class="flex justify-between items-center">
        <!-- Title Skeleton -->
        <div class="h-9 bg-zinc-200 dark:bg-zinc-800 rounded-md w-64 shimmer"></div>
    </div>

    <!-- Active Schedule Card Skeleton -->
    <div class="p-6 bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-2xl space-y-6">
        <div class="border-b border-zinc-200 dark:border-zinc-800 pb-3 flex justify-between items-center">
            <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 shimmer"></div>
            <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-full w-24 shimmer"></div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="space-y-2">
                <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/3 shimmer"></div>
                <div class="h-10 bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-md shimmer"></div>
            </div>
            <div class="space-y-2">
                <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/3 shimmer"></div>
                <div class="h-10 bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-md shimmer"></div>
            </div>
            <div class="space-y-2">
                <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/3 shimmer"></div>
                <div class="h-10 bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-md shimmer"></div>
            </div>
        </div>
    </div>

    <!-- Department & Program Settings Skeletons -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Departments Table Card -->
        <div class="p-6 bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-2xl space-y-4">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/3 shimmer"></div>
                <div class="h-9 bg-zinc-200 dark:bg-zinc-800 rounded-md w-28 shimmer"></div>
            </div>
            <x-skeleton type="table" :rows="3" :cols="3" />
        </div>

        <!-- Programs Table Card -->
        <div class="p-6 bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-2xl space-y-4">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/3 shimmer"></div>
                <div class="h-9 bg-zinc-200 dark:bg-zinc-800 rounded-md w-28 shimmer"></div>
            </div>
            <x-skeleton type="table" :rows="3" :cols="3" />
        </div>
    </div>
</div>
