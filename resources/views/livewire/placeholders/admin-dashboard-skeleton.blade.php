<div class="w-full">
    <div class="w-full flex flex-col gap-8 text-left">
        <!-- 1. Header Section with Academic Term & Live Status Context Skeleton (1:1 Mirror) -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 w-full text-left">
            <div class="flex flex-col items-start text-left">
                <div class="flex items-center gap-3 flex-wrap">
                    <flux:heading size="xl" level="1" class="text-left font-extrabold tracking-tight">Admin Dashboard</flux:heading>
                    <div class="h-6 w-36 bg-zinc-200 dark:bg-zinc-800 rounded-md shimmer"></div>
                    <div class="h-6 w-28 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
                </div>
            </div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <!-- Unified Evaluation Schedule Card Skeleton -->
                <div class="inline-flex items-center gap-2 p-1 pl-2.5 rounded-lg border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 shadow-2xs">
                    <div class="h-4 w-28 bg-zinc-200 dark:bg-zinc-800 rounded shimmer"></div>
                    <div class="h-8 w-28 bg-zinc-200 dark:bg-zinc-800 rounded-lg shimmer"></div>
                </div>
                <div class="h-8 w-20 bg-zinc-200 dark:bg-zinc-800 rounded-lg shimmer"></div>
            </div>
        </div>

        <!-- 2. Top Row: 4 Executive KPI Cards (1:1 Mirror) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <!-- Card 1 Skeleton: Overall Institutional Rating -->
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs p-5.5 flex flex-col justify-between">
                <div>
                    <div class="h-6 flex items-center justify-between">
                        <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded w-36 shimmer"></div>
                    </div>
                    <div class="flex items-baseline gap-2 mt-3.5 flex-wrap">
                        <div class="h-9 bg-zinc-200 dark:bg-zinc-800 rounded-md w-24 shimmer"></div>
                        <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-28 shimmer"></div>
                    </div>
                    <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-40 shimmer mt-2.5"></div>
                </div>
                <div class="mt-auto pt-3.5 min-h-[36px] flex flex-col justify-end gap-1.5">
                    <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-full shimmer"></div>
                    <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-3/4 shimmer"></div>
                </div>
            </div>

            <!-- Card 2 Skeleton: Positive Feedback Rate -->
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs p-5.5 flex flex-col justify-between">
                <div>
                    <div class="h-6 flex items-center justify-between">
                        <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded w-36 shimmer"></div>
                    </div>
                    <div class="flex items-baseline gap-2 mt-3.5 flex-wrap">
                        <div class="h-9 bg-zinc-200 dark:bg-zinc-800 rounded-md w-20 shimmer"></div>
                        <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-20 shimmer"></div>
                    </div>
                    <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-44 shimmer mt-2.5"></div>
                </div>
                <div class="mt-auto pt-3.5 min-h-[36px] flex flex-col justify-end gap-1.5">
                    <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-full shimmer"></div>
                    <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-2/3 shimmer"></div>
                </div>
            </div>

            <!-- Card 3 Skeleton: Overall Completion Rate -->
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs p-5.5 flex flex-col justify-between">
                <div>
                    <div class="h-6 flex items-center justify-between">
                        <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded w-36 shimmer"></div>
                    </div>
                    <div class="flex items-baseline gap-2 mt-3.5 flex-wrap">
                        <div class="h-9 bg-zinc-200 dark:bg-zinc-800 rounded-md w-20 shimmer"></div>
                        <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-24 shimmer"></div>
                    </div>
                    <div class="mt-2.5 space-y-1.5">
                        <div class="w-full bg-zinc-200/80 dark:bg-zinc-700 rounded-full h-2 overflow-hidden">
                            <div class="h-2 rounded-full w-2/3 bg-zinc-300 dark:bg-zinc-600 shimmer"></div>
                        </div>
                        <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-24 shimmer"></div>
                    </div>
                </div>
                <div class="mt-auto pt-3.5 min-h-[36px] flex flex-col justify-end gap-1.5">
                    <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-full shimmer"></div>
                    <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-4/5 shimmer"></div>
                </div>
            </div>

            <!-- Card 4 Skeleton: Pending Evaluators -->
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs p-5.5 flex flex-col justify-between">
                <div>
                    <div class="h-6 flex items-center justify-between gap-2">
                        <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded w-32 shimmer"></div>
                        <div class="h-6 w-24 bg-zinc-100 dark:bg-zinc-800 rounded-md shimmer"></div>
                    </div>
                    <div class="flex items-baseline gap-2 mt-3.5 flex-wrap">
                        <div class="h-9 bg-zinc-200 dark:bg-zinc-800 rounded-md w-16 shimmer"></div>
                        <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-20 shimmer"></div>
                    </div>
                    <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-40 shimmer mt-2.5"></div>
                </div>
                <div class="mt-auto pt-3.5 min-h-[36px] flex flex-col justify-end gap-1.5">
                    <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-full shimmer"></div>
                    <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-3/5 shimmer"></div>
                </div>
            </div>
        </div>

        <!-- 3. Analytics Charts Row (1:1 Mirror - min-h-[508px]) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Chart 1: Completion Rate by Role Skeleton -->
            <div class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-5 min-h-[508px]">
                <div class="flex items-center justify-between gap-2 sm:gap-3 border-b border-zinc-200 dark:border-zinc-800 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded w-44 shimmer"></div>
                    </div>
                </div>

                <!-- Fixed-height Utility / Sub-bar Skeleton -->
                <div class="h-6 flex items-center justify-center text-xs px-0.5">
                    <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded w-40 shimmer"></div>
                </div>

                <!-- Chart Canvas Container Shape -->
                <div class="h-72 w-full pt-1.5 flex items-center justify-center">
                    <div class="w-full h-full rounded-lg bg-zinc-100 dark:bg-zinc-800/40 shimmer"></div>
                </div>

                <!-- Footer Action Toolbar Skeleton -->
                <div class="flex items-center justify-between gap-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded w-36 shimmer"></div>
                    <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-28 shimmer"></div>
                </div>
            </div>

            <!-- Chart 2: Completion Rate by Department Skeleton -->
            <div class="p-4 sm:p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-5 min-h-[508px]">
                <div class="flex items-center justify-between gap-2.5 border-b border-zinc-200 dark:border-zinc-800 pb-3">
                    <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded w-56 shimmer"></div>
                    <div class="h-[31px] bg-zinc-200 dark:bg-zinc-800 rounded-lg w-32 shimmer"></div>
                </div>

                <!-- Fixed-height Utility / Sub-bar Skeleton -->
                <div class="h-6 flex items-center justify-center text-xs px-0.5">
                    <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded w-40 shimmer"></div>
                </div>

                <!-- Chart Canvas Container Shape -->
                <div class="h-72 w-full pt-1.5 flex items-center justify-center">
                    <div class="w-full h-full rounded-lg bg-zinc-100 dark:bg-zinc-800/40 shimmer"></div>
                </div>

                <!-- Footer Action Toolbar Skeleton -->
                <div class="flex items-center justify-between gap-3 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                    <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded w-36 shimmer"></div>
                    <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-28 shimmer"></div>
                </div>
            </div>
        </div>

        <!-- 4. Middle Row: Submission Trend & Overall Feedback Insights (1:1 Mirror - min-h-[477px]) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Panel 1: Submission Trend Skeleton (1:1 Mirror) -->
            <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-5 min-h-[477px]">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-zinc-200 dark:border-zinc-800 pb-3">
                    <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded w-36 shimmer"></div>
                    <!-- Daily / Cumulative pill switcher -->
                    <div class="h-7 w-28 bg-zinc-100 dark:bg-zinc-800 rounded-lg shimmer"></div>
                </div>
                <!-- 3 Stat Blocks Shape (h-[102px]) -->
                <div class="grid grid-cols-3 gap-2 sm:gap-3">
                    <div class="h-[102px] p-2.5 sm:p-3 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/30 text-center flex flex-col items-center justify-between">
                        <div class="h-3 bg-zinc-200 dark:bg-zinc-700 rounded w-16 shimmer"></div>
                        <div class="h-7 bg-zinc-200 dark:bg-zinc-700 rounded w-16 shimmer"></div>
                        <div class="h-3 bg-zinc-200 dark:bg-zinc-700 rounded w-14 shimmer"></div>
                    </div>
                    <div class="h-[102px] p-2.5 sm:p-3 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/30 text-center flex flex-col items-center justify-between">
                        <div class="h-3 bg-zinc-200 dark:bg-zinc-700 rounded w-16 shimmer"></div>
                        <div class="h-7 bg-zinc-200 dark:bg-zinc-700 rounded w-16 shimmer"></div>
                        <div class="h-3 bg-zinc-200 dark:bg-zinc-700 rounded w-14 shimmer"></div>
                    </div>
                    <div class="h-[102px] p-2.5 sm:p-3 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/30 text-center flex flex-col items-center justify-between">
                        <div class="h-3 bg-zinc-200 dark:bg-zinc-700 rounded w-16 shimmer"></div>
                        <div class="h-7 bg-zinc-200 dark:bg-zinc-700 rounded w-16 shimmer"></div>
                        <div class="h-3 bg-zinc-200 dark:bg-zinc-700 rounded w-14 shimmer"></div>
                    </div>
                </div>
                <!-- Canvas Container Shape (h-56 sm:h-60) -->
                <div class="h-56 sm:h-60 w-full pt-1">
                    <div class="w-full h-full rounded-lg bg-zinc-100 dark:bg-zinc-800/40 shimmer"></div>
                </div>
            </div>

            <!-- Panel 2: AI Feedback Analysis Skeleton (1:1 Mirror) -->
            <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col justify-between gap-6 min-h-[477px]">
                <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                    <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded w-44 shimmer"></div>
                    <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-full w-24 shimmer"></div>
                </div>
                <div class="space-y-4 flex-1 flex flex-col justify-between">
                    <!-- 3 Stat Blocks Shape (h-[102px]) -->
                    <div class="grid grid-cols-3 gap-2 sm:gap-3">
                        <div class="h-[102px] p-2 sm:p-3 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/30 text-center flex flex-col items-center justify-between">
                            <div class="h-3 bg-zinc-200 dark:bg-zinc-700 rounded w-14 shimmer"></div>
                            <div class="h-7 bg-zinc-200 dark:bg-zinc-700 rounded w-14 shimmer"></div>
                            <div class="h-3 bg-zinc-200 dark:bg-zinc-700 rounded w-10 shimmer"></div>
                        </div>
                        <div class="h-[102px] p-2 sm:p-3 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/30 text-center flex flex-col items-center justify-between">
                            <div class="h-3 bg-zinc-200 dark:bg-zinc-700 rounded w-14 shimmer"></div>
                            <div class="h-7 bg-zinc-200 dark:bg-zinc-700 rounded w-14 shimmer"></div>
                            <div class="h-3 bg-zinc-200 dark:bg-zinc-700 rounded w-10 shimmer"></div>
                        </div>
                        <div class="h-[102px] p-2 sm:p-3 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/30 text-center flex flex-col items-center justify-between">
                            <div class="h-3 bg-zinc-200 dark:bg-zinc-700 rounded w-14 shimmer"></div>
                            <div class="h-7 bg-zinc-200 dark:bg-zinc-700 rounded w-14 shimmer"></div>
                            <div class="h-3 bg-zinc-200 dark:bg-zinc-700 rounded w-10 shimmer"></div>
                        </div>
                    </div>
                    <!-- AI-Extracted Key Feedback Highlights Skeleton (exact h-[222px] container matching live) -->
                    <div class="h-[222px] pt-3 border-t border-zinc-100 dark:border-zinc-800 flex flex-col justify-between gap-2.5">
                        <div class="flex items-center justify-between">
                            <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded w-44 shimmer"></div>
                            <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-32 shimmer"></div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 flex-1">
                            <!-- Praise column -->
                            <div class="p-3 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/30 flex flex-col justify-between">
                                <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 pb-1.5">
                                    <div class="h-3 bg-zinc-200 dark:bg-zinc-700 rounded w-20 shimmer"></div>
                                    <div class="h-2.5 bg-zinc-200 dark:bg-zinc-700 rounded w-12 shimmer"></div>
                                </div>
                                <div class="flex flex-col gap-2.5 py-1">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="h-3 bg-zinc-200 dark:bg-zinc-700 rounded w-28 shimmer"></div>
                                        <div class="h-4 bg-zinc-200 dark:bg-zinc-700 rounded w-16 shimmer"></div>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="h-3 bg-zinc-200 dark:bg-zinc-700 rounded w-24 shimmer"></div>
                                        <div class="h-4 bg-zinc-200 dark:bg-zinc-700 rounded w-16 shimmer"></div>
                                    </div>
                                </div>
                            </div>
                            <!-- Focus areas column -->
                            <div class="p-3 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/30 flex flex-col justify-between">
                                <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 pb-1.5">
                                    <div class="h-3 bg-zinc-200 dark:bg-zinc-700 rounded w-20 shimmer"></div>
                                    <div class="h-2.5 bg-zinc-200 dark:bg-zinc-700 rounded w-16 shimmer"></div>
                                </div>
                                <div class="flex flex-col gap-2.5 py-1">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="h-3 bg-zinc-200 dark:bg-zinc-700 rounded w-28 shimmer"></div>
                                        <div class="h-4 bg-zinc-200 dark:bg-zinc-700 rounded w-16 shimmer"></div>
                                    </div>
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="h-3 bg-zinc-200 dark:bg-zinc-700 rounded w-24 shimmer"></div>
                                        <div class="h-4 bg-zinc-200 dark:bg-zinc-700 rounded w-16 shimmer"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Unified High-Density Activity & Completion Table Skeleton (1:1 Mirror - 453px) -->
        <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex flex-col overflow-hidden">
            <!-- Tab Header Bar Skeleton -->
            <div class="px-6 pt-5 pb-3 border-b border-zinc-200 dark:border-zinc-800 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded w-56 shimmer"></div>
                <!-- Tab Switcher Skeleton (h-[38px] matching live 38px button toolbar) -->
                <div class="h-[38px] bg-zinc-100 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 w-64 shimmer"></div>
            </div>

            <!-- Table Container Skeleton (exact 380px height to match live max-h-[380px]) -->
            <div class="h-[380px] overflow-hidden flex flex-col">
                <!-- Table Thead Skeleton (6 columns: Submitted, Evaluation Type, Subject, Target, Flow, Status) -->
                <div class="h-11 bg-zinc-50 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700 px-5 flex items-center justify-between">
                    <div class="h-3.5 bg-zinc-200 dark:bg-zinc-700 rounded w-20 shimmer"></div>
                    <div class="h-3.5 bg-zinc-200 dark:bg-zinc-700 rounded w-16 shimmer"></div>
                    <div class="h-3.5 bg-zinc-200 dark:bg-zinc-700 rounded w-20 shimmer"></div>
                    <div class="h-3.5 bg-zinc-200 dark:bg-zinc-700 rounded w-20 shimmer"></div>
                    <div class="h-3.5 bg-zinc-200 dark:bg-zinc-700 rounded w-32 shimmer"></div>
                    <div class="h-3.5 bg-zinc-200 dark:bg-zinc-700 rounded w-14 shimmer"></div>
                </div>

                <!-- Table Rows Skeleton (6 rows @ ~55px each = 330px + 44px thead = ~374px) -->
                <div class="flex-1 divide-y divide-zinc-100 dark:divide-zinc-800/60 flex flex-col justify-between">
                    @for ($i = 0; $i < 6; $i++)
                        <div class="px-5 py-3.5 flex items-center justify-between gap-4">
                            <div class="space-y-1 w-44 shrink-0">
                                <div class="h-3.5 bg-zinc-100 dark:bg-zinc-800/80 rounded w-24 shimmer"></div>
                                <div class="h-2.5 bg-zinc-100 dark:bg-zinc-800/50 rounded w-32 shimmer"></div>
                            </div>
                            <div class="w-28 shrink-0">
                                <div class="h-5 bg-zinc-100 dark:bg-zinc-800/60 rounded w-20 shimmer"></div>
                            </div>
                            <div class="w-36 shrink-0 hidden sm:block">
                                <div class="h-5 bg-zinc-100 dark:bg-zinc-800/60 rounded w-28 shimmer"></div>
                            </div>
                            <div class="w-44 shrink-0 hidden md:block">
                                <div class="h-3.5 bg-zinc-100 dark:bg-zinc-800/80 rounded w-28 shimmer"></div>
                                <div class="h-2.5 bg-zinc-100 dark:bg-zinc-800/50 rounded w-16 shimmer mt-1"></div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="h-3.5 bg-zinc-100 dark:bg-zinc-800/70 rounded w-4/5 shimmer"></div>
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        <!-- 6. Quick System Actions Skeleton (1:1 Mirror - h-[104px] cards) -->
        <div class="flex flex-col gap-8 mt-2">
            <div>
                <flux:heading size="lg" class="font-extrabold tracking-tight">Quick System Actions</flux:heading>
            </div>

            @foreach ([
                'Evaluation Monitoring & Reports',
                'Schedules & Questionnaires',
                'User Accounts & Organization'
            ] as $category)
                <div class="flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-zinc-900 dark:text-zinc-100">{{ $category }}</span>
                        <flux:badge variant="neutral" size="sm">4 shortcuts</flux:badge>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        @for ($i = 0; $i < 4; $i++)
                            <div class="p-4 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs flex items-start gap-3.5 text-left h-[104px]">
                                <div class="p-2.5 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shrink-0">
                                    <div class="size-5 bg-zinc-200 dark:bg-zinc-700 rounded shimmer"></div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded w-28 shimmer"></div>
                                    <div class="mt-1 space-y-1">
                                        <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-full shimmer"></div>
                                        <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-4/5 shimmer"></div>
                                    </div>
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
