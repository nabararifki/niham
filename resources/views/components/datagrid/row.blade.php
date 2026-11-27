@props([
    'rowId' => null,
    'rowNumber' => null,
    'selectable' => false,
    'disabledSelection' => false,
    'disabledTitle' => null,
    'invalid' => false,
])

@if ($selectable && ! $disabledSelection)
    <tr data-row-id="{{ $rowId }}"
        x-data="{ rowId: {{ (int) $rowId }}, isInvalid: {{ $invalid ? 'true' : 'false' }} }"
        @contextmenu="openRowMenu($event, rowId)"
        :class="isSelected(rowId)
            ? (isInvalid
                ? 'bg-red-100 dark:bg-red-950 border-l-4 border-error ring-2 ring-inset ring-accent'
                : 'bg-indigo-100 dark:bg-indigo-900 ring-2 ring-inset ring-accent')
            : (isInvalid
                ? 'bg-red-50 dark:bg-red-900/20 border-l-4 border-error'
                : 'bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700')"
        {{ $attributes->merge(['class' => 'transition-colors']) }}>
        <td class="px-3 py-2.5 text-xs font-mono cursor-pointer select-none whitespace-nowrap"
            @click="toggleRow(rowId)"
            :class="isSelected(rowId) ? 'text-accent' : 'text-gray-400 dark:text-gray-500'"
            title="{{ __('assets.toggle_row_selection') }}">
            <span x-show="!isSelected(rowId)">{{ $rowNumber !== null ? number_format((int) $rowNumber) : '' }}</span>
            <span x-show="isSelected(rowId)" x-cloak
                  class="inline-flex items-center justify-center w-4 h-4 rounded bg-accent text-white align-middle"
                  aria-hidden="true">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </span>
            @if (isset($rowMeta))
                {{ $rowMeta }}
            @endif
        </td>
        {{ $slot }}
    </tr>
@elseif ($selectable && $disabledSelection)
    <tr data-row-id="{{ $rowId }}"
        {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 transition-colors hover:bg-gray-50 dark:hover:bg-gray-700']) }}>
        <td class="px-3 py-2.5 text-xs font-mono text-gray-400 dark:text-gray-500 whitespace-nowrap select-none">
            <span class="inline-flex items-center gap-1.5">
                <span>{{ $rowNumber !== null ? number_format((int) $rowNumber) : '' }}</span>
                @if ($disabledTitle)
                    <x-tooltip :text="$disabledTitle" width="w-48">
                        <svg class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </x-tooltip>
                @endif
            </span>
        </td>
        {{ $slot }}
    </tr>
@else
    <tr {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700']) }}>
        @if ($rowNumber !== null)
            <td class="px-3 py-2.5 text-xs font-mono text-gray-400 dark:text-gray-500 whitespace-nowrap select-none">
                {{ number_format((int) $rowNumber) }}
            </td>
        @endif
        {{ $slot }}
    </tr>
@endif
