<div class="w-full flex flex-col gap-8">
    <div class="flex justify-between items-center">
        <!-- Title Skeleton -->
        <div class="h-9 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 shimmer"></div>
    </div>

    <!-- AI Stats & Controls Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <x-skeleton type="card" />
        <x-skeleton type="card" />
        <x-skeleton type="card" />
    </div>

    <!-- AI Logs and Submissions Table Skeleton -->
    <div class="p-6 bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-2xl space-y-4">
        <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
            <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/4 shimmer"></div>
            <div class="h-9 bg-zinc-200 dark:bg-zinc-800 rounded-md w-32 shimmer"></div>
        </div>
        <x-skeleton type="table" :rows="4" :cols="5" />
    </div>
</div>
