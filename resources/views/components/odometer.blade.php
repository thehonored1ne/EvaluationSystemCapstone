@props([
    'value',
    'class' => '',
    'prefix' => '',
    'suffix' => '',
    'decimals' => 0,
    'precision' => null,
])

@php
    $effectiveDecimals = (int) ($precision ?? $decimals ?? 0);
@endphp

<span 
    x-data="{
        value: {{ (float) ($value ?? 0) }},
        decimals: {{ $effectiveDecimals }},
        digits: [],
        init() {
            let str = this.decimals > 0 ? Number(this.value).toFixed(this.decimals) : Math.round(Number(this.value)).toString();
            this.digits = str.split('').map(c => (c >= '0' && c <= '9' ? '0' : c));
            
            this.$watch('value', (val) => {
                this.updateDigits(val);
            });
            
            setTimeout(() => {
                this.updateDigits(this.value);
            }, 50);
        },
        updateDigits(val) {
            let str = this.decimals > 0 ? Number(val).toFixed(this.decimals) : Math.round(Number(val)).toString();
            this.digits = str.split('');
        }
    }"
    :data-value="value = {{ (float) ($value ?? 0) }}"
    class="inline-flex items-center {{ $class }}"
>
    <!-- Prefix -->
    @if($prefix)
        <span>{{ $prefix }}</span>
    @endif

    <!-- Loop through characters -->
    <template x-for="(char, index) in digits" :key="index">
        <span class="inline-flex items-center">
            <!-- Show rolling column if it is a digit -->
            <span 
                x-show="char >= '0' && char <= '9'" 
                class="inline-block relative overflow-hidden" 
                style="height: 1.15em; line-height: 1.15em;"
            >
                <span 
                    class="flex flex-col transition-transform duration-[1500ms] cubic-bezier(0.34, 1.56, 0.64, 1)" 
                    :style="char >= '0' && char <= '9' ? `transform: translateY(-${char * 10}%);` : ''"
                >
                    <span>0</span>
                    <span>1</span>
                    <span>2</span>
                    <span>3</span>
                    <span>4</span>
                    <span>5</span>
                    <span>6</span>
                    <span>7</span>
                    <span>8</span>
                    <span>9</span>
                </span>
            </span>

            <!-- Show symbol statically if it is not a digit -->
            <span 
                x-show="!(char >= '0' && char <= '9')" 
                x-text="char"
            ></span>
        </span>
    </template>

    <!-- Suffix -->
    @if($suffix)
        <span>{{ $suffix }}</span>
    @endif
</span>
