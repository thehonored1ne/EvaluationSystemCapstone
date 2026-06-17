@props([
    'type' => 'text', // 'text', 'card', 'table', 'circle'
    'lines' => 3,
    'rows' => 5,
    'cols' => 4,
    'class' => ''
])

@if ($type === 'text')
    <div class="space-y-2.5 {{ $class }}">
        @for ($i = 0; $i < $lines; $i++)
            <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md shimmer {{ $i === $lines - 1 ? 'w-2/3' : 'w-full' }}"></div>
        @endfor
    </div>
@elseif ($type === 'circle')
    <div class="rounded-full bg-zinc-200 dark:bg-zinc-800 shimmer {{ $class }}"></div>
@elseif ($type === 'card')
    <div class="p-6 bg-zinc-50/50 dark:bg-zinc-900/50 border border-zinc-200/50 dark:border-zinc-800/50 rounded-2xl space-y-4 {{ $class }}">
        <div class="flex items-center justify-between">
            <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/3 shimmer"></div>
            <div class="h-8 w-8 bg-zinc-200 dark:bg-zinc-800 rounded-full shimmer"></div>
        </div>
        <div class="h-8 bg-zinc-200 dark:bg-zinc-800 rounded-md w-1/2 mt-2 shimmer"></div>
        <div class="h-3 bg-zinc-200 dark:bg-zinc-800 rounded-md w-2/3 mt-1 shimmer"></div>
    </div>
@elseif ($type === 'table')
    <div class="border border-zinc-200 dark:border-zinc-800 rounded-2xl overflow-hidden bg-white dark:bg-zinc-950 {{ $class }}">
        <div class="p-4 bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-800 flex gap-4">
            @for ($c = 0; $c < $cols; $c++)
                <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md flex-1 shimmer"></div>
            @endfor
        </div>
        <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
            @for ($r = 0; $r < $rows; $r++)
                <div class="p-4 flex gap-4">
                    @for ($c = 0; $c < $cols; $c++)
                        <div class="h-4 bg-zinc-200 dark:bg-zinc-800 rounded-md flex-1 shimmer {{ $c === $cols - 1 ? 'w-2/3' : 'w-full' }}"></div>
                    @endfor
                </div>
            @endfor
        </div>
    </div>
@endif
