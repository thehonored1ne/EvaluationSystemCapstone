<div class="w-full flex flex-col gap-6">
    <!-- Header with real text + Add Question button skeleton -->
    <div class="flex justify-between items-start md:items-center flex-col md:flex-row gap-4">
        <div>
            <flux:heading size="xl" level="1">Evaluation Questions Setup</flux:heading>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">Configure, organize, and reorder evaluation questions across all institutional evaluation roles.</p>
        </div>
        <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-36 shimmer shrink-0"></div>
    </div>

    <!-- Tabs Selection with Standardized Terms & Badges -->
    <div class="flex border-b border-zinc-200 dark:border-zinc-800 gap-1.5 md:gap-3 overflow-x-auto pb-0">
        <div class="pb-3 text-xs md:text-sm font-bold border-b-2 border-[#9b0000] dark:border-[#e07a7a] px-2.5 text-[#9b0000] dark:text-[#e07a7a] flex items-center gap-1.5">
            <span>Student</span>
            <div class="w-5 h-4 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
        </div>
        @foreach(['Dean', 'Program Head', 'Department Head', 'Peer', 'Supervisor', 'Self'] as $tab)
            <div class="pb-3 text-xs md:text-sm font-semibold text-zinc-400 px-2.5 flex items-center gap-1.5">
                <span>{{ $tab }}</span>
                <div class="w-5 h-4 bg-zinc-100 dark:bg-zinc-800 rounded-full shimmer"></div>
            </div>
        @endforeach
    </div>

    <!-- Subheader Filter & Search Bar -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">
            Category Context: <span class="font-bold text-zinc-800 dark:text-zinc-200">Student evaluates Faculty Professor</span>
        </div>
        <div class="w-full sm:w-64 h-9 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shimmer"></div>
    </div>

    <!-- Criteria Parts & Questions Skeleton Stream -->
    <div class="space-y-6">
        @for ($p = 0; $p < 3; $p++)
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden shadow-xs">
                <div class="flex flex-wrap items-center justify-between bg-zinc-50 dark:bg-zinc-800/40 px-4 py-3.5 border-b border-zinc-200 dark:border-zinc-800 gap-2">
                    <div class="flex items-center gap-2.5">
                        <div class="h-5 w-12 bg-zinc-200 dark:bg-zinc-800 rounded-md shimmer"></div>
                        <div class="h-5 w-48 bg-zinc-200 dark:bg-zinc-800 rounded-md shimmer"></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="h-6 w-24 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
                        <div class="h-6 w-28 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
                    </div>
                </div>
                <div class="divide-y divide-zinc-150 dark:divide-zinc-800">
                    @for ($q = 0; $q < 3; $q++)
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between p-4 gap-3 sm:gap-4">
                            <div class="flex items-start gap-3 flex-1 w-full">
                                <div class="size-6 rounded bg-zinc-200 dark:bg-zinc-800 shimmer shrink-0"></div>
                                <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-3/4 shimmer"></div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <div class="h-6 w-16 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
                                <div class="size-8 bg-zinc-200 dark:bg-zinc-800 rounded-lg shimmer"></div>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
        @endfor
    </div>
</div>
