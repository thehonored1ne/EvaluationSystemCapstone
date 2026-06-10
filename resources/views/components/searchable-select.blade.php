@props([
    'name',
    'label' => null,
    'placeholder' => 'Select...',
    'options' => [], // array of ['value' => ..., 'label' => ...]
    'required' => false,
    'live' => false,
])

<flux:field>
    @if($label)
        <flux:label>{{ $label }} @if($required)<span class="text-red-500">*</span>@endif</flux:label>
    @endif

    <div x-data="{
        open: false,
        search: '',
        selectedVal: @if($live) @entangle($name).live @else @entangle($name) @endif,
        options: {{ json_encode($options) }},
        selectedLabel: '',
        get filteredOptions() {
            if (!this.search) return this.options;
            return this.options.filter(opt => opt.label.toLowerCase().includes(this.search.toLowerCase()));
        },
        select(val, label) {
            this.selectedVal = val;
            this.selectedLabel = label;
            this.search = label;
            this.open = false;
        },
        init() {
            let matched = this.options.find(opt => opt.value == this.selectedVal);
            if (matched) {
                this.selectedLabel = matched.label;
                this.search = matched.label;
            }
            this.$watch('selectedVal', val => {
                let m = this.options.find(opt => opt.value == val);
                if (m) {
                    this.selectedLabel = m.label;
                    this.search = m.label;
                } else {
                    this.selectedLabel = '';
                    this.search = '';
                }
            });
        }
    }" class="relative w-full" @click.away="open = false; search = selectedLabel">
        
        <div class="relative">
            <flux:input 
                type="text" 
                x-model="search" 
                @focus="open = true" 
                @click="open = true"
                placeholder="{{ $placeholder }}"
                icon-trailing="chevron-down"
                class="w-full"
            />
        </div>

        <!-- Dropdown Container -->
        <div x-show="open" 
             x-transition
             class="absolute {{ $attributes->get('position', 'z-50 w-full mt-1') }} bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-700 rounded-lg shadow-lg max-h-60 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800"
             style="display: none;">
            
            <template x-for="opt in filteredOptions" :key="opt.value">
                <div @mousedown="select(opt.value, opt.label)"
                     class="px-3 py-2 text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800 cursor-pointer flex justify-between items-center"
                     :class="{'bg-zinc-50 dark:bg-zinc-850 font-semibold text-zinc-900 dark:text-zinc-100': opt.value == selectedVal}">
                    <span x-text="opt.label"></span>
                    <span x-show="opt.value == selectedVal" class="text-indigo-600 dark:text-indigo-400">
                        <flux:icon icon="check" variant="mini" class="size-4" />
                    </span>
                </div>
            </template>

            <div x-show="filteredOptions.length === 0" class="px-3 py-3 text-sm text-zinc-400 italic text-center">
                No matching results found
            </div>
        </div>
    </div>
    
    <flux:error :name="$name" />
</flux:field>
