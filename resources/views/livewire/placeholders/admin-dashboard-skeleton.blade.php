<div class="space-y-8 text-left w-full">
    <!-- Header with actual title & subtitle + Active Period badge skeleton -->
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <flux:heading size="xl" level="1" class="text-left">Admin Dashboard</flux:heading>
            <flux:subheading class="text-left">System overview, active window progress, and AI sentiment insights.</flux:subheading>
        </div>
        <div class="w-full sm:w-auto flex justify-end">
            <div class="h-7 bg-zinc-200 dark:bg-zinc-800 rounded-full w-56 shimmer shrink-0"></div>
        </div>
    </div>

    <!-- Top Row Statistics Cards (4 Cards) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <x-skeleton type="stat-card" />
        <x-skeleton type="stat-card" />
        <x-skeleton type="stat-card" :hasProgress="true" />
        <x-skeleton type="stat-card" />
    </div>

    <!-- Middle Row: Evaluation Period Status & AI Sentiment Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Evaluation Period Status Card -->
        <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-6 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">Evaluation Period Status</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Shows whether student & employee evaluation forms can be submitted right now.</p>
                </div>
            </div>
            <div class="space-y-4 flex-1 flex flex-col justify-between">
                <div class="h-16 bg-zinc-100 dark:bg-zinc-800/60 rounded-xl shimmer"></div>
                <div class="p-4 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-zinc-200 dark:border-zinc-700/60 space-y-3">
                    <div class="flex justify-between pb-2 border-b border-zinc-200 dark:border-zinc-700">
                        <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-36 shimmer"></div>
                        <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-28 shimmer"></div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 pt-1">
                        <div class="space-y-1">
                            <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-16 shimmer"></div>
                            <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded w-32 shimmer"></div>
                        </div>
                        <div class="space-y-1">
                            <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-16 shimmer"></div>
                            <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded w-32 shimmer"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pt-2">
                <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-full shimmer"></div>
            </div>
        </div>

        <!-- Sentiment Feedback Overview Card -->
        <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-6 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">Feedback Sentiment Overview</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">AI sentiment distribution across all submitted evaluation comments.</p>
                </div>
                <div class="h-6 w-24 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
            </div>
            <div class="space-y-4">
                <div class="flex justify-between items-center">
                    <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-40 shimmer"></div>
                    <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-28 shimmer"></div>
                </div>
                <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded-full w-full shimmer"></div>
                <div class="grid grid-cols-3 gap-3 pt-2">
                    <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700/60 space-y-2">
                        <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-16 shimmer"></div>
                        <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded w-12 shimmer"></div>
                    </div>
                    <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700/60 space-y-2">
                        <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-16 shimmer"></div>
                        <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded w-12 shimmer"></div>
                    </div>
                    <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700/60 space-y-2">
                        <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-16 shimmer"></div>
                        <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded w-12 shimmer"></div>
                    </div>
                </div>
            </div>
            <div class="pt-2">
                <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-full shimmer"></div>
            </div>
        </div>
    </div>

    <!-- Chart.js Visual Analytics Row (2 Charts) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <x-skeleton type="chart" />
        <x-skeleton type="chart" />
    </div>

    <!-- Section 5: Recent Submissions & Admin Audit Log (Exact h-[480px]) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Recent Submissions Stream -->
        <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col h-[480px] border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3 shrink-0">
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">Recent Submissions Log</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Live stream of submitted evaluations.</p>
                </div>
                <div class="h-6 w-16 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
            </div>
            <div class="space-y-3.5 pt-4 overflow-hidden flex-1">
                @for ($i = 0; $i < 5; $i++)
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-zinc-200 dark:border-zinc-700/60 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 flex-1">
                            <div class="size-8 rounded-full bg-zinc-200 dark:bg-zinc-800 shimmer shrink-0"></div>
                            <div class="space-y-1.5 flex-1">
                                <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-36 shimmer"></div>
                                <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 shimmer"></div>
                            </div>
                        </div>
                        <div class="h-5 w-16 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer shrink-0"></div>
                    </div>
                @endfor
            </div>
        </div>

        <!-- Audit Log Stream -->
        <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col h-[480px] border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3 shrink-0">
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">Audit Log Activities</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Track of changes performed in the system.</p>
                </div>
                <div class="h-6 w-16 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
            </div>
            <div class="space-y-3.5 pt-4 overflow-hidden flex-1">
                @for ($i = 0; $i < 5; $i++)
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-zinc-200 dark:border-zinc-700/60 flex items-start gap-3">
                        <div class="size-2 rounded-full bg-zinc-300 dark:bg-zinc-700 mt-2 shrink-0"></div>
                        <div class="space-y-1.5 flex-1">
                            <div class="flex items-center gap-2">
                                <div class="h-5 w-16 bg-zinc-200 dark:bg-zinc-800 rounded-md shimmer"></div>
                                <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-32 shimmer"></div>
                            </div>
                            <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded-md w-full shimmer"></div>
                        </div>
                        <div class="h-3 w-12 bg-zinc-200 dark:bg-zinc-800 rounded shimmer shrink-0 mt-1"></div>
                    </div>
                @endfor
            </div>
        </div>
    </div>

    <!-- Section 6 (Bottom): Quick System Actions Panel (12 Wide Cards Matching 1:1) -->
    <div class="flex flex-col gap-6 mt-2">
        <div>
            <flux:heading size="lg">Quick System Actions</flux:heading>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Click any shortcut below to navigate directly to evaluation tracking, user management, and academic settings.</p>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @for ($i = 0; $i < 12; $i++)
                <div class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 text-left">
                    <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shrink-0">
                        <div class="size-5 bg-zinc-200 dark:bg-zinc-700 rounded shimmer"></div>
                    </div>
                    <div class="space-y-1.5 flex-1 min-w-0">
                        <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-4/5 shimmer"></div>
                        <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded-md w-full shimmer"></div>
                        <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded-md w-2/3 shimmer"></div>
                    </div>
                </div>
            @endfor
        </div>
    </div>
</div>
