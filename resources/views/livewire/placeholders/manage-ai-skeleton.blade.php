<div class="flex flex-col gap-6 text-left w-full">
    <!-- Header with real text + Retrain button skeleton -->
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
        <div>
            <flux:heading size="xl" level="1">AI Pipeline & Classifier</flux:heading>
        </div>
        <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-40 shimmer shrink-0"></div>
    </div>

    <!-- Top 4 Executive KPI Cards Skeleton -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
        @for($i = 0; $i < 4; $i++)
            <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-5 shadow-xs flex flex-col justify-between gap-3">
                <div class="h-3 w-28 bg-zinc-200 dark:bg-zinc-800 rounded shimmer"></div>
                <div class="space-y-1.5">
                    <div class="h-8 w-20 bg-zinc-200 dark:bg-zinc-800 rounded shimmer"></div>
                    <div class="h-3 w-36 bg-zinc-100 dark:bg-zinc-800/60 rounded shimmer"></div>
                </div>
            </div>
        @endfor
    </div>

    <!-- Metrics and Correction Grid -->
    <div class="flex flex-col lg:flex-row gap-6 items-start w-full">
        <!-- Left: Model Metrics & Confusion Matrix -->
        <div class="w-full lg:w-[32%] flex flex-col gap-6 shrink-0">
            <div class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-xs space-y-4">
                <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-32 shimmer"></div>
                <div class="flex items-center gap-4 py-2">
                    <div class="size-16 rounded-full bg-zinc-200 dark:bg-zinc-800 shimmer shrink-0"></div>
                    <div class="space-y-1.5 flex-1">
                        <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-36 shimmer"></div>
                        <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 shimmer"></div>
                    </div>
                </div>
                <div class="h-px bg-zinc-200 dark:bg-zinc-800 w-full my-2"></div>
                <div class="space-y-2">
                    <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-32 shimmer"></div>
                    <div class="h-28 bg-zinc-100 dark:bg-zinc-800/60 rounded-lg shimmer"></div>
                </div>
            </div>
        </div>

        <!-- Right: Classification Table & Search -->
        <div class="w-full lg:flex-1 flex flex-col gap-4">
            <div class="p-5 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-xs space-y-4">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
                    <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 shimmer"></div>
                    <div class="flex items-center gap-2.5 w-full sm:w-auto">
                        <div class="w-full sm:w-52 h-9 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shimmer"></div>
                        <div class="w-full sm:w-56 h-9 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shimmer"></div>
                    </div>
                </div>

                <x-skeleton type="table" :rows="6" :cols="5" :colWidths="['w-1/2', 'w-16', 'w-16', 'w-20', 'w-24']" />
            </div>
        </div>
    </div>
</div>
