<div class="space-y-8 text-left w-full">
    <!-- Header with title & subtitle -->
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <flux:heading size="xl" level="1" class="text-left">Admin Dashboard</flux:heading>
        </div>
    </div>

    <!-- Top Row: Unified Single-Surface Metric Strip Skeleton (1:1 with Dashboard) -->
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-zinc-200 dark:divide-zinc-800 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
        @for ($i = 0; $i < 4; $i++)
            <div class="p-6 flex flex-col justify-between space-y-3">
                <div class="flex items-center justify-between">
                    <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded w-28 shimmer"></div>
                    <div class="size-5 bg-zinc-200 dark:bg-zinc-800 rounded shimmer"></div>
                </div>
                <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded w-20 shimmer"></div>
            </div>
        @endfor
    </div>

    <!-- Middle Row: Evaluation Period Status & AI Sentiment Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Evaluation Period Status Card -->
        <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-6 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">Evaluation Period Status</h3>
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
            <div class="pt-2 flex justify-end">
                <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-28 shimmer"></div>
            </div>
        </div>

        <!-- Sentiment Feedback Overview Card -->
        <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-6 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">Overall Evaluation Feedback</h3>
                </div>
            </div>
            <div class="space-y-4">
                <div class="p-4 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-zinc-200 dark:border-zinc-700/60 flex justify-between items-center">
                    <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded w-48 shimmer"></div>
                    <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-full w-24 shimmer"></div>
                </div>
                <div class="grid grid-cols-3 gap-3 pt-1">
                    <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700/60 space-y-2 text-center">
                        <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-12 mx-auto shimmer"></div>
                        <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded w-10 mx-auto shimmer"></div>
                    </div>
                    <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700/60 space-y-2 text-center">
                        <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-12 mx-auto shimmer"></div>
                        <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded w-10 mx-auto shimmer"></div>
                    </div>
                    <div class="p-3.5 rounded-xl bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-200 dark:border-zinc-700/60 space-y-2 text-center">
                        <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-12 mx-auto shimmer"></div>
                        <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded w-10 mx-auto shimmer"></div>
                    </div>
                </div>
            </div>
            <div class="pt-2 flex justify-end">
                <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-28 shimmer"></div>
            </div>
        </div>
    </div>

    <!-- Chart.js Visual Analytics Row (2 Charts) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Chart 1 Skeleton -->
        <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-4 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] min-h-[360px]">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">
                        Ratings Distribution Chart
                    </h3>
                </div>
                <div class="h-6 w-24 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
            </div>
            <div class="h-64 flex items-end justify-between gap-3 pt-6 px-2">
                <div class="h-[75%] w-full bg-zinc-200 dark:bg-zinc-800 rounded-t-md shimmer"></div>
                <div class="h-[55%] w-full bg-zinc-200 dark:bg-zinc-800 rounded-t-md shimmer"></div>
                <div class="h-[35%] w-full bg-zinc-200 dark:bg-zinc-800 rounded-t-md shimmer"></div>
                <div class="h-[20%] w-full bg-zinc-200 dark:bg-zinc-800 rounded-t-md shimmer"></div>
                <div class="h-[10%] w-full bg-zinc-200 dark:bg-zinc-800 rounded-t-md shimmer"></div>
            </div>
        </div>

        <!-- Chart 2 Skeleton -->
        <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-4 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] min-h-[360px]">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-zinc-100">
                        Department Average Ratings Chart
                    </h3>
                </div>
                <div class="h-6 w-20 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
            </div>
            <div class="h-64 flex flex-col justify-around pt-4 px-2 space-y-3">
                <div class="h-6 w-[88%] bg-zinc-200 dark:bg-zinc-800 rounded-r-md shimmer"></div>
                <div class="h-6 w-[92%] bg-zinc-200 dark:bg-zinc-800 rounded-r-md shimmer"></div>
                <div class="h-6 w-[85%] bg-zinc-200 dark:bg-zinc-800 rounded-r-md shimmer"></div>
                <div class="h-6 w-[78%] bg-zinc-200 dark:bg-zinc-800 rounded-r-md shimmer"></div>
            </div>
        </div>
    </div>

    <!-- Section 5: Unified High-Density Activity & Completion Table Skeleton (1:1 with Table) -->
    <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] overflow-hidden">
        <div class="px-6 pt-5 pb-3 border-b border-zinc-200 dark:border-zinc-800 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="space-y-1">
                <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded w-52 shimmer"></div>
            </div>
            <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-64 shimmer"></div>
        </div>
        <div class="p-6 space-y-3 max-h-[380px] overflow-hidden">
            @for ($i = 0; $i < 6; $i++)
                <div class="flex items-center justify-between py-2 border-b border-zinc-100 dark:border-zinc-800/60 gap-4">
                    <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded w-28 shimmer"></div>
                    <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded w-20 shimmer"></div>
                    <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded w-36 shimmer"></div>
                    <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded w-1/3 shimmer"></div>
                </div>
            @endfor
        </div>
    </div>

    <!-- Section 6 (Bottom): Quick System Actions Panel Skeleton -->
    <div class="flex flex-col gap-8 mt-2">
        <div>
            <flux:heading size="lg">Quick System Actions</flux:heading>
        </div>

        <!-- 1. Evaluation Monitoring & Reports Skeleton -->
        <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 pb-2">
                <div class="h-3.5 w-56 bg-zinc-200 dark:bg-zinc-800 rounded shimmer"></div>
                <div class="h-5 w-20 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @for ($i = 0; $i < 4; $i++)
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

        <!-- 2. Schedules & Questionnaires Skeleton -->
        <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 pb-2">
                <div class="h-3.5 w-52 bg-zinc-200 dark:bg-zinc-800 rounded shimmer"></div>
                <div class="h-5 w-20 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @for ($i = 0; $i < 4; $i++)
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

        <!-- 3. User Accounts & Organization Skeleton -->
        <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 pb-2">
                <div class="h-3.5 w-60 bg-zinc-200 dark:bg-zinc-800 rounded shimmer"></div>
                <div class="h-5 w-20 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @for ($i = 0; $i < 4; $i++)
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
</div>
