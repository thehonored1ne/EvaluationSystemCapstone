<div class="w-full flex flex-col gap-8">
    <div class="flex justify-between items-center">
        <!-- Title Skeleton -->
        <div class="h-9 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 shimmer"></div>
        <!-- Right side actions skeleton -->
        <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-md w-36 shimmer"></div>
    </div>

    <!-- Stats Cards Skeleton Row -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <x-skeleton type="card" />
        <x-skeleton type="card" />
        <x-skeleton type="card" />
        <x-skeleton type="card" />
    </div>

    <!-- Charts Skeletons Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="p-6 bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-2xl space-y-4">
            <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/3 shimmer"></div>
            <div class="h-64 bg-zinc-50 dark:bg-zinc-900/50 rounded-xl shimmer"></div>
        </div>

        <div class="p-6 bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-2xl space-y-4">
            <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/3 shimmer"></div>
            <div class="h-64 bg-zinc-50 dark:bg-zinc-900/50 rounded-xl shimmer"></div>
        </div>
    </div>
</div>
