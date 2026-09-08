<div class="space-y-6">
    <!-- Header with real text + 3 action buttons skeleton -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">Manage Employees</h1>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <div class="h-10 w-[133px] bg-zinc-200 dark:bg-zinc-800 rounded-lg shimmer"></div>
            <div class="h-10 w-[182px] bg-zinc-200 dark:bg-zinc-800 rounded-lg shimmer"></div>
            <div class="h-10 w-[154px] bg-zinc-200 dark:bg-zinc-800 rounded-lg shimmer"></div>
        </div>
    </div>

    <!-- Search & Filters Bar (1:1 with actual page) -->
    <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
        <div class="flex-1 min-w-0 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shimmer"></div>
        <div class="w-full sm:w-44 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shimmer shrink-0"></div>
        <div class="w-full sm:w-36 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shimmer shrink-0"></div>
        <div class="w-full sm:w-36 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shimmer shrink-0"></div>
        <div class="w-full sm:w-48 h-10 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shimmer shrink-0"></div>
        <div class="h-10 w-[78px] bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg shimmer shrink-0"></div>
    </div>

    <!-- Employees Table Skeleton (1:1 matching the 9 actual columns) -->
    <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900 overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm min-w-[920px] lg:min-w-0 lg:table-fixed">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/50 dark:text-zinc-400">
                    <tr>
                        <th class="py-3.5 px-3 w-10 lg:w-[4%] text-center">
                            <div class="h-4 w-4 bg-zinc-200 dark:bg-zinc-800 rounded mx-auto shimmer"></div>
                        </th>
                        <th class="py-3.5 px-4 w-28 lg:w-[10%] font-semibold">Employee ID</th>
                        <th class="py-3.5 px-4 w-44 lg:w-[18%] font-semibold">Full Name</th>
                        <th class="py-3.5 px-4 w-28 lg:w-[10%] font-semibold">Role</th>
                        <th class="py-3.5 px-4 w-52 lg:w-[19%] font-semibold">Department</th>
                        <th class="py-3.5 px-4 w-28 lg:w-[10%] font-semibold">Employment</th>
                        <th class="py-3.5 px-4 w-44 lg:w-[16%] font-semibold">Email</th>
                        <th class="py-3.5 px-4 w-20 lg:w-[7%] font-semibold">Status</th>
                        <th class="py-3.5 px-4 w-16 lg:w-[6%] font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @for($i = 0; $i < 10; $i++)
                        <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                            <!-- Checkbox -->
                            <td class="py-3.5 px-3 text-center">
                                <div class="h-4 w-4 bg-zinc-200 dark:bg-zinc-800 rounded mx-auto shimmer"></div>
                            </td>
                            <!-- Employee ID -->
                            <td class="py-3.5 px-4">
                                <div class="h-4 w-20 bg-zinc-200 dark:bg-zinc-800 rounded shimmer"></div>
                            </td>
                            <!-- Full Name -->
                            <td class="py-3.5 px-4">
                                <div class="h-4 w-32 bg-zinc-200 dark:bg-zinc-800 rounded shimmer"></div>
                            </td>
                            <!-- Role Text -->
                            <td class="py-3.5 px-4">
                                <div class="h-4 w-18 bg-zinc-200 dark:bg-zinc-800 rounded shimmer"></div>
                            </td>
                            <!-- Department -->
                            <td class="py-3.5 px-4">
                                <div class="h-4 w-36 bg-zinc-200 dark:bg-zinc-800 rounded shimmer"></div>
                            </td>
                            <!-- Employment Type Text -->
                            <td class="py-3.5 px-4">
                                <div class="h-4 w-18 bg-zinc-200 dark:bg-zinc-800 rounded shimmer"></div>
                            </td>
                            <!-- Email -->
                            <td class="py-3.5 px-4">
                                <div class="h-4 w-36 bg-zinc-200 dark:bg-zinc-800 rounded shimmer"></div>
                            </td>
                            <!-- Status Badge -->
                            <td class="py-3.5 px-4">
                                <div class="h-5 w-14 bg-zinc-200 dark:bg-zinc-800 rounded-md shimmer"></div>
                            </td>
                            <!-- Action Button -->
                            <td class="py-3.5 px-4 text-right">
                                <div class="h-8 w-16 bg-zinc-200 dark:bg-zinc-800 rounded-md shimmer ml-auto"></div>
                            </td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        </div>

        <!-- Pagination Skeleton Bar -->
        <div class="border-t border-zinc-200 px-6 py-4 dark:border-zinc-700 flex items-center justify-between">
            <div class="h-4 w-44 bg-zinc-200 dark:bg-zinc-800 rounded shimmer"></div>
            <div class="h-[38px] w-28 bg-zinc-200 dark:bg-zinc-800 rounded-lg shimmer"></div>
        </div>
    </div>
</div>
