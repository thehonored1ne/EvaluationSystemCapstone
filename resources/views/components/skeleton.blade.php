@props([
    'type' => 'text', // 'text', 'card', 'stat-card', 'table', 'circle', 'badge', 'button', 'chart', 'wizard'
    'lines' => 3,
    'rows' => 5,
    'cols' => 4,
    'colWidths' => null, // e.g. ['w-12', 'w-48', 'w-32', 'w-24']
    'class' => '',
    'hasProgress' => false,
    'hasIcon' => true,
])

@if ($type === 'text')
    <div class="space-y-2.5 {{ $class }}">
        @for ($i = 0; $i < $lines; $i++)
            <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md shimmer {{ $i === $lines - 1 ? 'w-2/3' : 'w-full' }}"></div>
        @endfor
    </div>

@elseif ($type === 'circle' || $type === 'avatar')
    <div class="rounded-full bg-zinc-200 dark:bg-zinc-800 shrink-0 shimmer {{ $class ?: 'size-10' }}"></div>

@elseif ($type === 'badge')
    <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-full shrink-0 shimmer {{ $class ?: 'w-24' }}"></div>

@elseif ($type === 'button')
    <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl shrink-0 shimmer {{ $class ?: 'w-32' }}"></div>

@elseif ($type === 'stat-card')
    <div class="flex flex-col justify-between p-6 bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs border-l-[5px] border-l-[#9b0000] dark:border-l-[#f89696] {{ $class }}">
        <div class="flex justify-between items-start">
            <div class="space-y-2 w-2/3">
                <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-28 shimmer"></div>
                <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-md w-20 shimmer"></div>
            </div>
            @if ($hasIcon)
                <div class="size-7 bg-zinc-200 dark:bg-zinc-800 rounded-lg shimmer shrink-0"></div>
            @endif
        </div>
        @if ($hasProgress)
            <div class="w-full bg-zinc-200 dark:bg-zinc-800 rounded-full h-3 mt-4 shimmer"></div>
            <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded-md w-3/4 mt-2 shimmer"></div>
        @endif
    </div>

@elseif ($type === 'card')
    <div class="p-6 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-xs space-y-4 {{ $class }}">
        <div class="flex items-center justify-between">
            <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/3 shimmer"></div>
            <div class="h-6 w-16 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
        </div>
        <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/2 shimmer"></div>
    </div>

@elseif ($type === 'chart')
    <div class="p-6 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-xs space-y-4 {{ $class }}">
        <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 pb-3">
            <div class="space-y-1">
                <div class="h-5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-36 shimmer"></div>
            </div>
            <div class="h-6 w-20 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
        </div>
        <div class="h-56 flex items-end justify-between gap-3 pt-6 px-2">
            <div class="h-[35%] w-full bg-zinc-200 dark:bg-zinc-800 rounded-t-md shimmer"></div>
            <div class="h-[60%] w-full bg-zinc-200 dark:bg-zinc-800 rounded-t-md shimmer"></div>
            <div class="h-[85%] w-full bg-zinc-200 dark:bg-zinc-800 rounded-t-md shimmer"></div>
            <div class="h-[50%] w-full bg-zinc-200 dark:bg-zinc-800 rounded-t-md shimmer"></div>
            <div class="h-[70%] w-full bg-zinc-200 dark:bg-zinc-800 rounded-t-md shimmer"></div>
            <div class="h-[40%] w-full bg-zinc-200 dark:bg-zinc-800 rounded-t-md shimmer"></div>
        </div>
    </div>

@elseif ($type === 'table')
    <div class="w-full overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800 shadow-xs bg-white dark:bg-zinc-900 {{ $class }}">
        <table class="w-full min-w-[700px] divide-y divide-zinc-200 dark:divide-zinc-800">
            <thead class="bg-zinc-50 dark:bg-zinc-800/60">
                <tr>
                    @for ($c = 0; $c < $cols; $c++)
                        @php
                            $width = is_array($colWidths) && isset($colWidths[$c]) ? $colWidths[$c] : 'w-auto';
                        @endphp
                        <th class="px-5 py-3.5 text-left {{ $width }}">
                            <div class="h-3.5 bg-zinc-200 dark:bg-zinc-700 rounded-md w-24 shimmer"></div>
                        </th>
                    @endfor
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @for ($r = 0; $r < $rows; $r++)
                    <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40">
                        @for ($c = 0; $c < $cols; $c++)
                            <td class="px-5 py-4">
                                @if ($c === 0)
                                    <div class="flex items-center gap-3">
                                        <div class="size-8 rounded-full bg-zinc-200 dark:bg-zinc-800 shimmer shrink-0"></div>
                                        <div class="space-y-1.5 flex-1">
                                            <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-36 shimmer"></div>
                                            <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded-md w-24 shimmer"></div>
                                        </div>
                                    </div>
                                @elseif ($c === $cols - 1)
                                    <div class="flex justify-end gap-2">
                                        <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-lg w-16 shimmer"></div>
                                    </div>
                                @else
                                    <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-28 shimmer"></div>
                                @endif
                            </td>
                        @endfor
                    </tr>
                @endfor
            </tbody>
        </table>
    </div>

@elseif ($type === 'wizard')
    <div class="w-full max-w-4xl mx-auto space-y-6 {{ $class }}">
        <!-- Progress Header -->
        <div class="p-6 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-xs space-y-4">
            <div class="flex justify-between items-center">
                <div class="space-y-1">
                    <div class="h-6 bg-zinc-200 dark:bg-zinc-800 rounded-md w-48 shimmer"></div>
                    <div class="h-3.5 bg-zinc-200 dark:bg-zinc-800 rounded-md w-32 shimmer"></div>
                </div>
                <div class="h-7 bg-zinc-200 dark:bg-zinc-800 rounded-full w-24 shimmer"></div>
            </div>
            <div class="w-full bg-zinc-200 dark:bg-zinc-800 rounded-full h-2.5 shimmer"></div>
            <!-- Question Pills Grid -->
            <div class="flex flex-wrap gap-2 pt-2">
                @for ($q = 1; $q <= 10; $q++)
                    <div class="size-9 rounded-lg bg-zinc-200 dark:bg-zinc-800 shimmer"></div>
                @endfor
            </div>
        </div>

        <!-- Question Card -->
        <div class="p-8 bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-xs space-y-6">
            <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-24 shimmer"></div>
            <div class="h-7 bg-zinc-200 dark:bg-zinc-800 rounded-md w-4/5 shimmer"></div>
            <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-3/5 shimmer"></div>

            <!-- Rating Buttons (1-5) -->
            <div class="grid grid-cols-5 gap-3 pt-4">
                @for ($r = 1; $r <= 5; $r++)
                    <div class="h-16 rounded-xl bg-zinc-200 dark:bg-zinc-800 shimmer flex flex-col items-center justify-center gap-1"></div>
                @endfor
            </div>

            <!-- Action Controls -->
            <div class="flex justify-between items-center pt-6 border-t border-zinc-200 dark:border-zinc-800">
                <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-28 shimmer"></div>
                <div class="h-10 bg-zinc-200 dark:bg-zinc-800 rounded-xl w-28 shimmer"></div>
            </div>
        </div>
    </div>
@endif
