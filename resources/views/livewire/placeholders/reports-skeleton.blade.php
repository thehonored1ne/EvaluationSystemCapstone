<div class="space-y-8 w-full text-left">
    <!-- Header & Tab Navigation Bar with real text -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-zinc-200 dark:border-zinc-800 pb-4">
        <div>
            <flux:heading size="xl" level="1">Evaluation Reports</flux:heading>
            <flux:subheading>Official performance evaluation reports, criteria breakdowns, and qualitative feedback summaries.</flux:subheading>
        </div>
        <!-- Actions & Mode Switcher -->
        <div class="flex items-center gap-3">
            <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-36 shimmer"></div>
            <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-32 shimmer"></div>
        </div>
    </div>

    <!-- Filter Control Bar -->
    <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl p-4 sm:p-6 shadow-xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3 flex-1">
            <div class="w-full sm:w-60 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
            <div class="w-full sm:w-60 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
            <div class="w-full sm:w-48 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
        </div>
        <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-full sm:w-32 shimmer shrink-0"></div>
    </div>

    <!-- Summary Executive KPIs Grid (4 Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <x-skeleton type="stat-card" />
        <x-skeleton type="stat-card" />
        <x-skeleton type="stat-card" />
        <x-skeleton type="stat-card" />
    </div>

    <!-- 2-Column AI Executive Insights & Attention Alert Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Faculty Requiring Attention Card -->
        <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs space-y-4 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div class="space-y-1">
                    <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 shimmer"></div>
                    <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-64 shimmer"></div>
                </div>
                <div class="h-6 w-20 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
            </div>
            <x-skeleton type="table" :rows="3" :cols="4" :colWidths="['w-36', 'w-24', 'w-20', 'w-32']" />
        </div>

        <!-- Prescriptive Recommendations Card -->
        <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs space-y-4 border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696]">
            <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
                <div class="space-y-1">
                    <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 shimmer"></div>
                    <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-64 shimmer"></div>
                </div>
            </div>
            <div class="space-y-3 pt-2">
                @for ($i = 0; $i < 3; $i++)
                    <div class="p-3.5 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-zinc-200 dark:border-zinc-700/60 space-y-2">
                        <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded w-40 shimmer"></div>
                        <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded w-full shimmer"></div>
                    </div>
                @endfor
            </div>
        </div>
    </div>

    <!-- Academic Department Leaderboard Table -->
    <div class="p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs space-y-4">
        <div class="flex justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-3">
            <div class="space-y-1">
                <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-56 shimmer"></div>
                <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-72 shimmer"></div>
            </div>
        </div>
        <x-skeleton type="table" :rows="4" :cols="5" :colWidths="['w-16', 'w-48', 'w-32', 'w-28', 'w-24']" />
    </div>
</div>
