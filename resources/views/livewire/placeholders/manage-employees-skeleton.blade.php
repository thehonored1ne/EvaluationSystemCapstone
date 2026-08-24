<div class="space-y-6 text-left w-full">
    <!-- Header with real text + 3 action buttons skeleton -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">Manage Employees</h1>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-28 shimmer"></div>
            <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-36 shimmer"></div>
            <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-36 shimmer"></div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="flex flex-col md:flex-row gap-4 items-end bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-lg border border-gray-200 dark:border-zinc-700">
        <div class="flex-1 w-full min-w-[260px] h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shimmer"></div>
        <div class="w-full md:w-48 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shimmer"></div>
        <div class="w-full md:w-40 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shimmer"></div>
        <div class="w-full md:w-36 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shimmer"></div>
    </div>

    <!-- Employees Table -->
    <x-skeleton type="table" :rows="8" :cols="6" :colWidths="['w-56', 'w-32', 'w-32', 'w-48', 'w-24', 'w-24']" />
</div>
