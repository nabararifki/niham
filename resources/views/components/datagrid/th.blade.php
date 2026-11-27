@props([
    'label' => '',
    'required' => false,
    'align' => 'left',
    'field' => null,
    'bulkType' => null,
    'bulkOptions' => null,
    'bulkWidth' => 'w-36',
    'locked' => false,
    'lockedLabel' => null,
    'lockedTitle' => null,
    'step' => 'any',
    'bulkPlaceholder' => null,
])

@php
    $alignClass = match ($align) {
        'center' => 'text-center',
        'right'  => 'text-right',
        default  => 'text-left',
    };
    $hasBulk = $field !== null && $bulkType !== null;
    $bulkInputClass = "mt-1.5 block {$bulkWidth} border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs normal-case font-normal dark:bg-gray-900/50 dark:text-white";
@endphp

<th {{ $attributes->merge(['class' => "px-3 py-3 {$alignClass} text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider align-top whitespace-nowrap"]) }}>
    @if (isset($labelExtra))
        <div class="flex items-center {{ $align === 'center' ? 'justify-center' : '' }} gap-1"
             @if($hasBulk || isset($bulkWidget) || $locked) :class="selectionCount > 0 ? 'text-[10px] leading-tight' : ''" @endif>
            <span>{{ $label }}@if($required) *@endif</span>
            {{ $labelExtra }}
        </div>
    @else
        <div @if($hasBulk || isset($bulkWidget) || $locked) :class="selectionCount > 0 ? 'text-[10px] leading-tight' : ''" @endif>
            {{ $label }}@if($required) *@endif
        </div>
    @endif

    @if (isset($bulkWidget))
        <template x-if="selectionCount > 0">
            {{ $bulkWidget }}
        </template>
    @elseif ($locked)
        <template x-if="selectionCount > 0">
            <select disabled
                    data-department-locked
                    title="{{ $lockedTitle }}"
                    class="{{ $bulkInputClass }} bg-gray-100 dark:bg-gray-900/70 text-gray-500 dark:text-gray-400 cursor-not-allowed">
                <option>{{ $lockedLabel }}</option>
            </select>
        </template>
    @elseif ($hasBulk)
        <template x-if="selectionCount > 0">
            @if ($bulkType === 'select')
                <select @change="bulkUpdate('{{ $field }}', $event.target.value)"
                        class="{{ $bulkInputClass }}">
                    <option value="">{{ $bulkPlaceholder ?? __('assets.select_placeholder') }}</option>
                    <template x-for="option in (options['{{ $bulkOptions }}'] || [])" :key="option.id">
                        <option :value="option.id" x-text="option.name"></option>
                    </template>
                </select>
            @elseif ($bulkType === 'date')
                <input type="date"
                       @change="bulkUpdate('{{ $field }}', $event.target.value)"
                       class="{{ $bulkInputClass }}" />
            @elseif ($bulkType === 'number')
                <input type="number"
                       step="{{ $step }}"
                       @change="bulkUpdate('{{ $field }}', $event.target.value)"
                       class="{{ $bulkInputClass }}" />
            @else
                <input type="text"
                       @change="bulkUpdate('{{ $field }}', $event.target.value)"
                       @if($bulkPlaceholder) placeholder="{{ $bulkPlaceholder }}" @endif
                       class="{{ $bulkInputClass }}" />
            @endif
        </template>
    @endif
</th>
