@props([
    'paginator' => null,
    'selectable' => false,
    'selectableIds' => null,
    'pageRowIds' => null,
    'bulkUpdateUrl' => null,
    'bulkDeleteUrl' => null,
    'reloadOnBulkUpdate' => true,
    'reloadOnBulkDelete' => true,
    'canDelete' => true,
    'stickyHeader' => true,
    'maxHeight' => null,
    'options' => [],
    'dialogId' => 'datagrid_confirm_dialog',
    'showPagination' => true,
    'paginationView' => null,
    'paginationData' => [],
    'tbodyId' => null,
    'rowNumberLabel' => null,
    'showIndexColumn' => true,
])

@php
    $resolvedRowIds = collect($pageRowIds ?? $selectableIds ?? ($paginator ? $paginator->items() : []))
        ->map(fn ($item) => is_object($item) ? (int) $item->id : (is_array($item) ? (int) ($item['id'] ?? 0) : (int) $item))
        ->filter(fn ($id) => $id > 0)
        ->values();

    $gridLabels = [
        'rowsSelected'      => __('assets.rows_selected', ['count' => '__COUNT__']),
        'deleteSelected'    => __('assets.delete_selected', ['count' => '__COUNT__']),
        'bulkEditHint'      => __('assets.bulk_edit_hint', ['count' => '__COUNT__']),
        'deleteRowTitle'    => __('assets.delete_row_title'),
        'deleteRowsTitle'   => __('assets.delete_rows_title'),
        'deleteRowConfirm'  => __('assets.delete_row_confirm'),
        'deleteRowsConfirm' => __('assets.delete_rows_confirm', ['count' => '__COUNT__']),
        'bulkUpdateError'   => __('assets.bulk_update_error', ['message' => '__MSG__']),
        'deleteRowError'    => __('assets.delete_row_error', ['message' => '__MSG__']),
        'deleteRowsError'   => __('assets.delete_rows_error', ['message' => '__MSG__']),
    ];

    $gridConfig = [
        'pageRowIds'         => $resolvedRowIds,
        'bulkUpdateUrl'      => $bulkUpdateUrl,
        'bulkDeleteUrl'      => $bulkDeleteUrl,
        'reloadOnBulkUpdate' => $reloadOnBulkUpdate,
        'reloadOnBulkDelete' => $reloadOnBulkDelete,
        'dialogId'           => $dialogId,
        'options'            => $options,
        'labels'             => $gridLabels,
    ];
@endphp

<div @if($selectable)
         x-data="nihamDataGrid(@js($gridConfig))"
         @keydown.escape.window="onEscape()"
         @datagrid-option-added.window="addOption($event.detail.listKey, $event.detail.option)"
     @endif
     {{ $attributes }}>

    @if ($selectable)
        {{-- Selection Toolbar --}}
        <div x-show="selectionCount > 0" x-cloak
             class="mb-4 flex flex-wrap items-center justify-between gap-3 px-4 py-3 rounded-xl bg-indigo-50 dark:bg-indigo-950 border border-indigo-200 dark:border-indigo-800">
            <div class="flex items-center gap-2 text-sm font-semibold text-accent">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span x-text="selectedText"></span>
                <span class="font-normal text-xs text-gray-500 dark:text-gray-400 hidden sm:inline">
                    · {{ __('assets.selection_page_scoped') }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400 hidden md:inline" x-text="bulkHintText"></span>
                <button type="button" @click="clearSelection()"
                        class="btn btn-xs btn-ghost border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                    {{ __('assets.clear_selection') }}
                </button>
                @if ($bulkDeleteUrl && $canDelete)
                    <button type="button" @click="requestDeleteSelected()"
                            class="btn btn-xs btn-error border-transparent text-white hover:opacity-90 rounded-lg">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        <span x-text="deleteSelectedText"></span>
                    </button>
                @endif
            </div>
        </div>
    @endif

    {{-- Solid Data Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
            <thead class="{{ $stickyHeader ? 'sticky top-0 z-10' : '' }} bg-gray-50 dark:bg-gray-900">
                <tr>
                    @if ($selectable)
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider w-10 align-top">
                            <input type="checkbox"
                                   x-ref="selectAll"
                                   :checked="allSelected"
                                   x-effect="$refs.selectAll.indeterminate = someSelected && !allSelected"
                                   @change="toggleAll()"
                                   aria-label="{{ __('assets.select_all_rows') }}"
                                   title="{{ __('assets.select_all_rows') }}"
                                   class="rounded border-gray-300 dark:border-gray-600 text-accent focus:ring-accent cursor-pointer">
                        </th>
                    @elseif ($showIndexColumn)
                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider w-10 align-top">
                            {{ $rowNumberLabel ?? __('messages.no') }}
                        </th>
                    @endif
                    {{ $head }}
                </tr>
            </thead>
            <tbody @if($tbodyId) id="{{ $tbodyId }}" @endif class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @if ($showPagination && $paginator && $paginator->hasPages())
        <div class="mt-4">
            @if ($paginationView)
                {{ $paginator->links($paginationView, $paginationData) }}
            @else
                {{ $paginator->links() }}
            @endif
        </div>
    @endif

    @if ($selectable)
        {{-- Row Context Menu --}}
        <div x-show="menuOpen" x-cloak
             @click.outside="closeRowMenu()"
             @scroll.window="closeRowMenu()"
             @resize.window="closeRowMenu()"
             :style="`top: ${menuY}px; left: ${menuX}px`"
             class="fixed z-50 min-w-[13rem] py-1.5 rounded-xl bg-white/95 dark:bg-gray-800/95 backdrop-blur-md border border-gray-200/50 dark:border-gray-700/50 shadow-2xl text-sm">
            <div class="px-3 py-1.5 text-xs font-semibold text-gray-400 dark:text-gray-500 border-b border-gray-200/50 dark:border-gray-700/50 mb-1"
                 x-text="selectedText"></div>
            @if ($bulkDeleteUrl && $canDelete)
                <button type="button" @click="requestDeleteSelected()"
                        class="w-full flex items-center gap-2.5 px-3 py-2 text-left text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    <span x-text="deleteSelectedText"></span>
                </button>
            @endif
            <button type="button" @click="clearSelection(); closeRowMenu()"
                    class="w-full flex items-center gap-2.5 px-3 py-2 text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                {{ __('assets.clear_selection') }}
            </button>
        </div>

        @if ($bulkDeleteUrl && $canDelete)
            <x-confirm-dialog
                :id="$dialogId"
                alpine-title="deleteTitleText"
                alpine-message="deleteConfirmText"
            />
        @endif
    @endif
</div>
