<div class="w-full flex flex-col gap-6 text-left">
    <!-- Header with real text + 3 action buttons skeleton -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <flux:heading size="xl" level="1">Manage Classes & Enrollment</flux:heading>
            <flux:subheading class="text-left mt-1">Class section allocations, professor assignments, student enrollment, and scheduling.</flux:subheading>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-28 shimmer"></div>
            <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-32 shimmer"></div>
            <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-28 shimmer"></div>
        </div>
    </div>

    <!-- Search & Advanced Filter Controls Bar -->
    <div class="flex flex-col gap-3 bg-gray-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-gray-200 dark:border-zinc-700">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-3 w-full">
            <div class="flex-1 min-w-[220px] h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shimmer"></div>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2.5 flex-1 items-center">
                <div class="h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shimmer"></div>
                <div class="h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shimmer"></div>
                <div class="h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shimmer"></div>
                <div class="h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shimmer"></div>
            </div>
        </div>
    </div>

    <!-- Classes Table -->
    <x-skeleton type="table" :rows="8" :cols="7" :colWidths="['w-28', 'w-48', 'w-40', 'w-36', 'w-24', 'w-20', 'w-20']" />
</div>
