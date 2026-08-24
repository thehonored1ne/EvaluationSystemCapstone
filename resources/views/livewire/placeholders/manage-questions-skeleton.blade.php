<div class="w-full flex flex-col gap-6 text-left">
    <!-- Header with real text + Add Question button skeleton -->
    <div class="flex justify-between items-start md:items-center flex-col md:flex-row gap-4">
        <div>
            <flux:heading size="xl" level="1">Evaluation Questions Setup</flux:heading>
        </div>
        <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-36 shimmer shrink-0"></div>
    </div>

    <!-- Tabs Selection with Standardized Terms & Underline Border-B -->
    <div class="flex border-b border-zinc-200 dark:border-zinc-800 gap-2 md:gap-4 overflow-x-auto pb-0">
        <div class="pb-3 text-xs md:text-sm font-bold border-b-2 border-[#9b0000] dark:border-[#f89696] px-2 text-[#9b0000] dark:text-[#f89696]">Student</div>
        <div class="pb-3 text-xs md:text-sm font-semibold text-zinc-400 px-2">Dean</div>
        <div class="pb-3 text-xs md:text-sm font-semibold text-zinc-400 px-2">Program Head</div>
        <div class="pb-3 text-xs md:text-sm font-semibold text-zinc-400 px-2">Department Head</div>
        <div class="pb-3 text-xs md:text-sm font-semibold text-zinc-400 px-2">Peer</div>
        <div class="pb-3 text-xs md:text-sm font-semibold text-zinc-400 px-2">Supervisor</div>
        <div class="pb-3 text-xs md:text-sm font-semibold text-zinc-400 px-2">Self</div>
    </div>

    <!-- Subheader Filter & Search Bar -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div class="text-xs text-zinc-500 dark:text-zinc-400 font-medium">
            Category Context: <span class="font-bold text-zinc-800 dark:text-zinc-200">Student evaluates Faculty Professor</span>
        </div>
        <div class="w-full sm:w-64 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
    </div>

    <!-- Criteria Parts & Questions Skeleton Stream -->
    <div class="space-y-6">
        @for ($p = 0; $p < 3; $p++)
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-6 shadow-xs space-y-4 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
                <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                    <div class="space-y-1">
                        <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 shimmer"></div>
                        <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-32 shimmer"></div>
                    </div>
                    <div class="h-6 w-24 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
                </div>
                <div class="space-y-3 pt-2">
                    @for ($q = 0; $q < 3; $q++)
                        <div class="p-4 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-zinc-200 dark:border-zinc-700/60 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3 flex-1">
                                <div class="size-7 rounded-lg bg-zinc-200 dark:bg-zinc-800 shimmer shrink-0"></div>
                                <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-3/4 shimmer"></div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <div class="h-6 w-16 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
                                <div class="h-8 w-16 bg-zinc-200 dark:bg-zinc-800 rounded-lg shimmer"></div>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
        @endfor
    </div>
</div>
