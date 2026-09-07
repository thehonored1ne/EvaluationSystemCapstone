<div class="w-full flex flex-col gap-6 text-left">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 w-full">
        <div>
            <div class="flex items-center gap-3 flex-wrap">
                <flux:heading size="xl" level="1" class="font-extrabold tracking-tight">System Activity & Logs</flux:heading>
                <div class="h-6 w-52 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
            </div>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                Audit trail of administrative actions and historical evaluation submissions ledger.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <flux:button href="{{ route('admin.dashboard') }}" wire:navigate size="sm" variant="subtle" icon="arrow-left" class="border border-zinc-200 dark:border-zinc-700">
                Dashboard
            </flux:button>
        </div>
    </div>

    <!-- Main Card Container with Tab Switcher -->
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col overflow-hidden">
        <!-- Tab Navigation Bar -->
        <div class="px-6 pt-5 pb-3 border-b border-zinc-200 dark:border-zinc-800 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="flex items-center gap-2 p-1 rounded-lg bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-xs shrink-0">
                <div class="px-3.5 py-1.5 rounded-md font-semibold bg-white dark:bg-zinc-900 text-zinc-900 dark:text-zinc-100 shadow-xs">
                    System Audit Trail
                </div>
                <div class="px-3.5 py-1.5 rounded-md font-semibold text-zinc-600 dark:text-zinc-400">
                    Submissions Ledger
                </div>
            </div>
            <div class="h-4 w-44 bg-zinc-200 dark:bg-zinc-800 rounded shimmer"></div>
        </div>

        <!-- Audit Logs Skeleton Content -->
        <div class="p-4 sm:p-6 flex flex-col gap-5">
            <!-- Filters Bar Skeleton -->
            <div class="flex flex-col gap-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="lg:col-span-2 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shimmer"></div>
                    <div class="h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shimmer"></div>
                    <div class="h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shimmer"></div>
                </div>
                <div class="h-12 bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700/60 rounded-lg shimmer"></div>
            </div>

            <!-- Table Skeleton with balanced column proportions -->
            <x-skeleton type="table" :rows="10" :cols="5" :colWidths="['w-44 lg:w-[18%]', 'w-28 lg:w-[12%]', 'w-36 lg:w-[16%]', 'w-48 lg:w-[18%]', 'min-w-[220px] lg:w-[36%]']" />
        </div>
    </div>
</div>
