<div class="flex flex-col gap-8 w-full max-w-6xl mx-auto px-4 py-6 text-left">
    <!-- Header Card with real text + semester select skeleton -->
    <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-zinc-50">Evaluation Analytics</h1>
            <p class="text-zinc-500 dark:text-zinc-400 text-sm mt-1">Explore statistical distributions, averages, and comparisons across semesters.</p>
        </div>
        <div class="w-full md:w-64 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl shimmer"></div>
    </div>

    <!-- KPI Summaries (4 Cards) -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <x-skeleton type="stat-card" />
        <x-skeleton type="stat-card" />
        <x-skeleton type="stat-card" />
        <x-skeleton type="stat-card" />
    </div>

    <!-- Visual Charts Grid (2 Charts) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <x-skeleton type="chart" />
        <x-skeleton type="chart" />
    </div>
</div>
