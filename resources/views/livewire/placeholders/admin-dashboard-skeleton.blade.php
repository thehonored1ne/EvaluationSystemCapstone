<div class="w-full flex flex-col gap-8 text-left">
    <!-- Header Section with Academic Term & Live Status Context Skeleton (1:1 Mirror) -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 w-full text-left">
        <div class="flex flex-col items-start text-left">
            <div class="flex items-center gap-3 flex-wrap">
                <flux:heading size="xl" level="1" class="text-left font-extrabold tracking-tight">Admin Dashboard</flux:heading>
                <div class="h-6 w-36 bg-zinc-200 dark:bg-zinc-800 rounded-md shimmer"></div>
                <div class="h-6 w-28 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <div class="h-8 w-20 bg-zinc-200 dark:bg-zinc-800 rounded-lg shimmer"></div>
            <div class="h-8 w-20 bg-zinc-200 dark:bg-zinc-800 rounded-lg shimmer"></div>
        </div>
    </div>

    <!-- Top Row: 4 Executive KPI Cards (1:1 Mirror) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        @for ($i = 0; $i < 4; $i++)
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] p-5.5 flex flex-col justify-between min-h-[196px]">
                <div>
                    <div class="h-6 flex items-center justify-between">
                        <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded w-36 shimmer"></div>
                    </div>
                    <div class="h-9 bg-zinc-200 dark:bg-zinc-800 rounded-md w-28 shimmer mt-3.5"></div>
                    <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-40 shimmer mt-2.5"></div>
                </div>
                <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-48 shimmer mt-auto pt-3.5"></div>
            </div>
        @endfor
    </div>

    <!-- Analytics Charts Row: Evaluator Role Turnout & Department Performance Benchmark (1:1 Mirror) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Chart 1: Completion Rate by Role Skeleton -->
        <div class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-5 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] min-h-[400px]">
            <div class="flex items-center justify-between gap-2 sm:gap-3 border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded w-48 shimmer"></div>
            </div>

            <!-- Fixed-height Utility / Sub-bar Skeleton -->
            <div class="h-6 flex items-center justify-center text-xs px-0.5">
                <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded w-40 shimmer"></div>
            </div>

            <!-- Simple Chart Canvas Container Shape -->
            <div class="h-72 w-full pt-1.5 flex items-center justify-center">
                <div class="w-full h-full rounded-lg bg-zinc-100 dark:bg-zinc-800/40 shimmer"></div>
            </div>

            <!-- Footer Action Toolbar Skeleton -->
            <div class="flex items-center justify-between gap-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded w-36 shimmer"></div>
                <div class="h-7 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-28 shimmer"></div>
            </div>
        </div>

        <!-- Chart 2: Completion Rate by Department Skeleton -->
        <div class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-5 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] min-h-[400px]">
            <div class="flex items-center justify-between gap-2.5 border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded w-56 shimmer"></div>
                <div class="h-7 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-32 shimmer"></div>
            </div>

            <!-- Fixed-height Utility / Sub-bar Skeleton -->
            <div class="h-6 flex items-center justify-center text-xs px-0.5">
                <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded w-40 shimmer"></div>
            </div>

            <!-- Simple Chart Canvas Container Shape -->
            <div class="h-72 w-full pt-1.5 flex items-center justify-center">
                <div class="w-full h-full rounded-lg bg-zinc-100 dark:bg-zinc-800/40 shimmer"></div>
            </div>

            <!-- Footer Action Toolbar Skeleton -->
            <div class="flex items-center justify-between gap-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded w-36 shimmer"></div>
                <div class="h-7 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-28 shimmer"></div>
            </div>
        </div>
    </div>

    <!-- Middle Row: Evaluation Window Status & Overall Feedback Insights (1:1 Mirror) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Panel 1: Evaluation Period Status Skeleton -->
        <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-6 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] min-h-[420px]">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded w-48 shimmer"></div>
                <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-32 shimmer"></div>
            </div>
            <div class="space-y-4 flex-1 flex flex-col justify-between">
                <div class="h-16 bg-zinc-100 dark:bg-zinc-800/50 rounded-xl shimmer"></div>
                <div class="h-32 bg-zinc-100 dark:bg-zinc-800/50 rounded-xl shimmer"></div>
            </div>
            <div class="pt-2 flex justify-end">
                <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-28 shimmer"></div>
            </div>
        </div>

        <!-- Panel 2: Overall Evaluation Feedback Overview Skeleton -->
        <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-6 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] min-h-[420px]">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded w-52 shimmer"></div>
                <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-full w-24 shimmer"></div>
            </div>
            <div class="space-y-4 flex-1 flex flex-col justify-between">
                <!-- 3 Stat Blocks Shape -->
                <div class="grid grid-cols-3 gap-2 sm:gap-3">
                    <div class="h-20 rounded-xl bg-zinc-100 dark:bg-zinc-800/50 shimmer"></div>
                    <div class="h-20 rounded-xl bg-zinc-100 dark:bg-zinc-800/50 shimmer"></div>
                    <div class="h-20 rounded-xl bg-zinc-100 dark:bg-zinc-800/50 shimmer"></div>
                </div>
                <!-- Highlights Container Shape -->
                <div class="h-32 bg-zinc-100 dark:bg-zinc-800/50 rounded-xl shimmer"></div>
            </div>
        </div>
    </div>

    <!-- Unified High-Density Activity & Completion Table Skeleton (1:1 Mirror) -->
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] overflow-hidden">
        <div class="px-6 pt-5 pb-3 border-b border-zinc-200 dark:border-zinc-800 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded w-56 shimmer"></div>
            <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-52 shimmer"></div>
        </div>
        <div class="p-6 space-y-3 min-h-[300px]">
            @for ($i = 0; $i < 5; $i++)
                <div class="h-10 bg-zinc-100 dark:bg-zinc-800/40 rounded-lg shimmer w-full"></div>
            @endfor
        </div>
    </div>

    <!-- Quick System Actions Skeleton (1:1 Mirror) -->
    <div class="flex flex-col gap-8 mt-2">
        <div>
            <flux:heading size="lg">Quick System Actions</flux:heading>
            <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-0.5">Direct shortcuts to evaluation monitoring, questionnaire configuration, and master user records.</p>
        </div>

        @foreach ([
            'Evaluation Monitoring & Reports',
            'Schedules & Questionnaires',
            'User Accounts & Organization'
        ] as $category)
            <div class="flex flex-col gap-3">
                <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 pb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-zinc-900 dark:text-zinc-100">{{ $category }}</span>
                    <flux:badge variant="neutral" size="sm">4 shortcuts</flux:badge>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] flex items-start gap-3.5 text-left">
                            <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shrink-0">
                                <div class="size-5 bg-zinc-200 dark:bg-zinc-700 rounded shimmer"></div>
                            </div>
                            <div class="min-w-0 flex-1 space-y-1.5">
                                <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded w-28 shimmer"></div>
                                <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-full shimmer"></div>
                                <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-4/5 shimmer"></div>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
        @endforeach
    </div>
</div>
