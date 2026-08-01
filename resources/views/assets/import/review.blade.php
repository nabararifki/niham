<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('assets.review_data') }}
        </h2>
    </x-slot>

    <style>
        progress.progress-primary::-webkit-progress-value {
            background-color: var(--property-accent, #4f46e5) !important;
        }
        progress.progress-primary::-moz-progress-bar {
            background-color: var(--property-accent, #4f46e5) !important;
        }
        progress.progress-primary {
            background-color: #e5e7eb !important;
        }
        .dark progress.progress-primary {
            background-color: #374151 !important;
        }
        progress.progress-primary::-webkit-progress-bar {
            background-color: #e5e7eb !important;
        }
        .dark progress.progress-primary::-webkit-progress-bar {
            background-color: #374151 !important;
        }
    </style>

    @php
        // Ids of the rows on THIS page. Selection is deliberately page-scoped —
        // review paginates server-side at 50/page, and letting a selection span
        // pages the user can't see is worse than making them re-select.
        $pageRowIds = collect($paginatedData->items())->pluck('id')->map(fn ($id) => (int) $id)->values();
    @endphp

    {{-- @js, not @json: these happen to be integers today, but @json emits literal
         double quotes for any string value, which would terminate this attribute. --}}
    <div class="py-8"
         @keydown.escape.window="onEscape()"
         x-data="importReview({ validCount: @js($validCount), invalidCount: @js($invalidCount), invalidPages: @js($invalidPages), total: @js($total), pageRowIds: @js($pageRowIds) })">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- ─── Progress Stepper (Top) ─────────────────────────────────────── --}}
            @include('assets.import.partials.stepper', ['currentStep' => 4])

            {{-- ─── Main Card ───────────────────────────────────────────────────── --}}
            <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md shadow-xl sm:rounded-2xl border border-gray-200/50 dark:border-gray-700/50 overflow-hidden">
                {{-- No action: this form is never submitted natively. Submit is always
                     intercepted by triggerPreflight(), and saving goes through
                     saveAll() → storeBatch(). It exists only to group the fields
                     that triggerPreflight() serialises via FormData. --}}
                <form method="POST" id="review-form" @submit.prevent="triggerPreflight()">
                    @csrf

                    {{-- Pass page offset so store() can merge edits at the correct global indices --}}
                    <input type="hidden" name="page_offset" value="{{ $pageOffset }}">

                    {{-- ─── Card Header ──────────────────────────────────────────── --}}
                    <div class="p-6 border-b border-gray-200/50 dark:border-gray-700/50 bg-gray-50/50 dark:bg-gray-900/50 flex flex-wrap justify-between items-center gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('assets.bulk_add_title') }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __('assets.bulk_add_desc') }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            {{-- Row counter badge --}}
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-accent/10 text-accent text-xs font-semibold border border-accent/20">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                {{ __('assets.review_page_info', [
                                    'from' => number_format($paginatedData->firstItem()),
                                    'to'   => number_format($paginatedData->lastItem()),
                                    'total'=> number_format($total),
                                ]) }}
                            </span>
                            {{-- Save All button (top) --}}
                            <button type="submit"
                                    :disabled="isValidating"
                                    class="inline-flex items-center px-5 py-2.5 bg-accent border border-transparent rounded-xl font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 transition-all shadow-sm shadow-accent/30 disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg x-show="!isValidating" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <svg x-show="isValidating" x-cloak class="animate-spin h-4 w-4 mr-2 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ __('assets.save_all_data') }}
                            </button>
                        </div>
                    </div>

                    <div class="p-4" x-data="{ rows: {{ $paginatedData->count() }} }">

                        {{-- Warnings --}}
                        @if (!empty($warning))
                            <div class="mb-4 bg-amber-100/60 dark:bg-amber-900/30 border border-amber-400/50 dark:border-amber-600/50 text-amber-800 dark:text-amber-200 px-4 py-3 rounded-lg flex items-start gap-3" role="alert">
                                <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <span class="text-sm">{{ $warning }}</span>
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="mb-4 bg-red-100/50 border border-red-400/50 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg" role="alert">
                                <strong class="font-bold">Oops!</strong>
                                <ul class="mt-2 list-disc list-inside text-sm">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        {{-- ─── Selection Toolbar ──────────────────────────────────────
                             The touch-accessible path to bulk delete. The context menu is a
                             shortcut for mouse users, never the only way to get here. --}}
                        <div x-show="selectionCount > 0" x-cloak
                             class="mb-4 flex flex-wrap items-center justify-between gap-3 px-4 py-3 rounded-xl bg-accent/10 border border-accent/20">
                            <div class="flex items-center gap-2 text-sm font-semibold text-accent">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
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
                                <button type="button" @click="requestDeleteSelected()"
                                        class="btn btn-xs btn-error border-transparent text-white hover:opacity-90 rounded-lg">
                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    <span x-text="deleteSelectedText"></span>
                                </button>
                            </div>
                        </div>

                        {{-- ─── Data Table ─────────────────────────────────────────── --}}
                        <div class="overflow-x-auto w-full rounded-xl border border-gray-200/50 dark:border-gray-700/50">
                            <table class="min-w-full divide-y divide-gray-200/50 dark:divide-gray-700/50 text-sm">
                                {{-- ─────────────────────────────────────────────────────────────
                                     While rows are selected each header shrinks its label and
                                     reveals a bulk-edit widget of the SAME type the column uses
                                     in a normal row — a select for category/department/status, a
                                     date picker for purchase_date, and so on. A generic text box
                                     everywhere would let someone type a category name that can
                                     never match a category id.

                                     They commit on @change (blur/Enter, or immediately on pick for
                                     a select) rather than on debounced input: one deliberate edit
                                     is one request, instead of every typing pause rewriting a
                                     column across the whole selection.
                                ──────────────────────────────────────────────────────────────── --}}
                                <thead class="bg-gray-50/80 dark:bg-gray-900/80">
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider w-8 align-top">
                                            {{-- indeterminate is a DOM property, not an attribute, so it
                                                 can't be set with a plain :binding — x-effect re-applies
                                                 it whenever the selection changes. --}}
                                            <input type="checkbox"
                                                   x-ref="selectAll"
                                                   :checked="allSelected"
                                                   x-effect="$refs.selectAll.indeterminate = someSelected && !allSelected"
                                                   @change="toggleAll()"
                                                   aria-label="{{ __('assets.select_all_rows') }}"
                                                   title="{{ __('assets.select_all_rows') }}"
                                                   class="rounded border-gray-300 dark:border-gray-600 text-accent focus:ring-accent cursor-pointer">
                                        </th>

                                        @php
                                            $thClass   = 'px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider align-top';
                                            $bulkInput = 'mt-1.5 block border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs normal-case font-normal dark:bg-gray-900/50 dark:text-white';
                                        @endphp

                                        <th class="{{ $thClass }}">
                                            <div :class="selectionCount > 0 ? 'text-[10px] leading-tight' : ''">{{ __('assets.tag') }} *</div>
                                            <template x-if="selectionCount > 0">
                                                <input type="text" @change="bulkUpdate('tag', $event.target.value)"
                                                       class="{{ $bulkInput }} w-32" />
                                            </template>
                                        </th>

                                        <th class="{{ $thClass }}">
                                            <div :class="selectionCount > 0 ? 'text-[10px] leading-tight' : ''">{{ __('assets.name') }} *</div>
                                            <template x-if="selectionCount > 0">
                                                <input type="text" @change="bulkUpdate('name', $event.target.value)"
                                                       class="{{ $bulkInput }} w-40" />
                                            </template>
                                        </th>

                                        <th class="{{ $thClass }}">
                                            <div :class="selectionCount > 0 ? 'text-[10px] leading-tight' : ''">{{ __('assets.category') }} *</div>
                                            <template x-if="selectionCount > 0">
                                                <select @change="bulkUpdate('category_id', $event.target.value)"
                                                        class="{{ $bulkInput }} w-36">
                                                    <option value="">{{ __('assets.select_placeholder') }}</option>
                                                    @foreach($categories as $cat)
                                                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                                    @endforeach
                                                </select>
                                            </template>
                                        </th>

                                        <th class="{{ $thClass }}">
                                            <div :class="selectionCount > 0 ? 'text-[10px] leading-tight' : ''">{{ __('assets.department') }} *</div>
                                            <template x-if="selectionCount > 0">
                                                <select @change="bulkUpdate('department_id', $event.target.value)"
                                                        class="{{ $bulkInput }} w-36">
                                                    <option value="">{{ __('assets.select_placeholder') }}</option>
                                                    @foreach($departments as $dept)
                                                        <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                                    @endforeach
                                                </select>
                                            </template>
                                        </th>

                                        <th class="{{ $thClass }}">
                                            <div :class="selectionCount > 0 ? 'text-[10px] leading-tight' : ''">{{ __('assets.status') }}</div>
                                            <template x-if="selectionCount > 0">
                                                <select @change="bulkUpdate('status', $event.target.value)"
                                                        class="{{ $bulkInput }} w-32">
                                                    <option value="">{{ __('assets.select_placeholder') }}</option>
                                                    <option value="in_service">{{ __('assets.in_service') }}</option>
                                                    <option value="out_of_service">{{ __('assets.out_of_service') }}</option>
                                                    <option value="disposed">{{ __('assets.disposed') }}</option>
                                                </select>
                                            </template>
                                        </th>

                                        <th class="{{ $thClass }}">
                                            <div :class="selectionCount > 0 ? 'text-[10px] leading-tight' : ''">{{ __('assets.model_brand') }}</div>
                                            <template x-if="selectionCount > 0">
                                                <input type="text" @change="bulkUpdate('model', $event.target.value)"
                                                       class="{{ $bulkInput }} w-32" />
                                            </template>
                                        </th>

                                        <th class="{{ $thClass }}">
                                            <div :class="selectionCount > 0 ? 'text-[10px] leading-tight' : ''">{{ __('assets.serial_number') }}</div>
                                            <template x-if="selectionCount > 0">
                                                <input type="text" @change="bulkUpdate('serial_number', $event.target.value)"
                                                       class="{{ $bulkInput }} w-32" />
                                            </template>
                                        </th>

                                        <th class="{{ $thClass }}">
                                            <div :class="selectionCount > 0 ? 'text-[10px] leading-tight' : ''">{{ __('assets.purchase_date') }}</div>
                                            <template x-if="selectionCount > 0">
                                                <input type="date" @change="bulkUpdate('purchase_date', $event.target.value)"
                                                       class="{{ $bulkInput }} w-36" />
                                            </template>
                                        </th>

                                        <th class="{{ $thClass }}">
                                            <div :class="selectionCount > 0 ? 'text-[10px] leading-tight' : ''">{{ __('assets.purchase_cost') }}</div>
                                            <template x-if="selectionCount > 0">
                                                <input type="number" step="any" @change="bulkUpdate('purchase_cost', $event.target.value)"
                                                       class="{{ $bulkInput }} w-28" />
                                            </template>
                                        </th>

                                        <th class="{{ $thClass }}">
                                            <div :class="selectionCount > 0 ? 'text-[10px] leading-tight' : ''">{{ __('assets.remarks') }}</div>
                                            <template x-if="selectionCount > 0">
                                                <input type="text" @change="bulkUpdate('remarks', $event.target.value)"
                                                       class="{{ $bulkInput }} w-40" />
                                            </template>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="review-tbody" class="divide-y divide-gray-200/50 dark:divide-gray-700/50 bg-white dark:bg-gray-800">

                                    {{-- ─────────────────────────────────────────────────────────────
                                         N+1 ELIMINATED: $categories and $departments are fetched
                                         ONCE in the controller and passed as collections.
                                         The Blade loop below NEVER touches the database.
                                         Index uses $pageOffset + $localIndex for global uniqueness.
                                    ──────────────────────────────────────────────────────────────── --}}
                                    @forelse($paginatedData as $localIndex => $item)
                                        @php
                                            // Global index: ensures form field names are unique
                                            // across all pages (assets[0..49], assets[50..99], etc.)
                                            $globalIndex = $pageOffset + $localIndex;
                                            $combined    = trim(($item['brand'] ?? '') . ' ' . ($item['model'] ?? ''));
                                        @endphp
                                        {{-- data-row-id is the DOM handle (a future multi-select can
                                             query [data-row-id]); rowId in the scope is what the
                                             edit/delete handlers read. Declared once per row so the
                                             two can never disagree. --}}
                                        {{-- The four states are spelled out rather than composed from two
                                             independent class lists, because "selected" and "invalid" both
                                             set a background: with two lists the winner would be decided by
                                             Tailwind's output order, not by anything written here. Branching
                                             guarantees exactly one background class is ever emitted, and
                                             keeps a selected invalid row visibly still invalid. --}}
                                        <tr data-row-id="{{ $item['id'] }}"
                                            x-data="{ rowId: {{ (int) $item['id'] }}, isInvalid: {{ ($item['is_invalid'] ?? false) ? 'true' : 'false' }} }"
                                            @contextmenu="openRowMenu($event, rowId)"
                                            :class="isSelected(rowId)
                                                ? (isInvalid
                                                    ? 'bg-red-100 dark:bg-red-900/50 border-l-4 border-error ring-2 ring-inset ring-accent/70'
                                                    : 'bg-accent/10 dark:bg-accent/20 ring-2 ring-inset ring-accent/70')
                                                : (isInvalid
                                                    ? 'bg-red-50 dark:bg-red-900/20 border-l-4 border-error'
                                                    : 'hover:bg-gray-50/50 dark:hover:bg-gray-700/30')"
                                            class="transition-colors">
                                            {{-- Row number, which becomes a ticked box while the row is
                                                 selected. Both states live in this one cell, which is also
                                                 the click target for toggling the row. --}}
                                            <td class="px-3 py-2.5 text-xs font-mono cursor-pointer select-none"
                                                @click="toggleRow(rowId)"
                                                :class="isSelected(rowId) ? 'text-accent' : 'text-gray-400 dark:text-gray-500'"
                                                title="{{ __('assets.toggle_row_selection') }}">
                                                <span x-show="!isSelected(rowId)">{{ number_format($globalIndex + 1) }}</span>
                                                {{-- x-cloak: selectedIds starts empty, so without it this
                                                     tick would flash on every row before Alpine boots. --}}
                                                <span x-show="isSelected(rowId)" x-cloak
                                                      class="inline-flex items-center justify-center w-4 h-4 rounded bg-accent text-white align-middle"
                                                      aria-hidden="true">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                </span>
                                            </td>

                                            {{-- Tag --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <input type="text"
                                                       name="assets[{{ $localIndex }}][tag]"
                                                       value="{{ old('assets.'.$localIndex.'.tag', $item['tag'] ?? ('AST-' . strtoupper(\Str::random(6)))) }}"
                                                       required
                                                       @input.debounce.500ms="autoSave('tag', $event.target.value, $data)"
                                                       class="block w-32 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                            {{-- Name --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <input type="text"
                                                       name="assets[{{ $localIndex }}][name]"
                                                       value="{{ old('assets.'.$localIndex.'.name', $item['name'] ?? '') }}"
                                                       required
                                                       @input.debounce.500ms="autoSave('name', $event.target.value, $data)"
                                                       class="block w-40 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                            {{-- Category — uses pre-fetched $categories collection, ZERO DB queries --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <select name="assets[{{ $localIndex }}][category_id]"
                                                        @change="autoSave('category_id', $event.target.value, $data)"
                                                        class="block w-36 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white">
                                                    <option value="">{{ __('assets.select_placeholder') }}</option>
                                                    @foreach($categories as $cat)
                                                        <option value="{{ $cat->id }}"
                                                            {{ old('assets.'.$localIndex.'.category_id', $item['category_id'] ?? '') == $cat->id ? 'selected' : '' }}>
                                                            {{ $cat->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>

                                            {{-- Department — uses pre-fetched $departments collection, ZERO DB queries --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <select name="assets[{{ $localIndex }}][department_id]"
                                                        @change="autoSave('department_id', $event.target.value, $data)"
                                                        class="block w-36 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white">
                                                    <option value="">{{ __('assets.select_placeholder') }}</option>
                                                    @foreach($departments as $dept)
                                                        <option value="{{ $dept->id }}"
                                                            {{ old('assets.'.$localIndex.'.department_id', $item['department_id'] ?? '') == $dept->id ? 'selected' : '' }}>
                                                            {{ $dept->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>

                                            {{-- Status --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <select name="assets[{{ $localIndex }}][status]"
                                                        required
                                                        @change="autoSave('status', $event.target.value, $data)"
                                                        class="block w-32 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white">
                                                    <option value="in_service"    {{ old('assets.'.$localIndex.'.status', $item['status'] ?? '') == 'in_service'    ? 'selected' : '' }}>{{ __('assets.in_service') }}</option>
                                                    <option value="out_of_service"{{ old('assets.'.$localIndex.'.status', $item['status'] ?? '') == 'out_of_service' ? 'selected' : '' }}>{{ __('assets.out_of_service') }}</option>
                                                    <option value="disposed"      {{ old('assets.'.$localIndex.'.status', $item['status'] ?? '') == 'disposed'       ? 'selected' : '' }}>{{ __('assets.disposed') }}</option>
                                                </select>
                                            </td>

                                            {{-- Model/Brand --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <input type="text"
                                                       name="assets[{{ $localIndex }}][model]"
                                                       value="{{ old('assets.'.$localIndex.'.model', $combined) }}"
                                                       @input.debounce.500ms="autoSave('model', $event.target.value, $data)"
                                                       class="block w-32 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                            {{-- Serial Number --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <input type="text"
                                                       name="assets[{{ $localIndex }}][serial_number]"
                                                       value="{{ old('assets.'.$localIndex.'.serial_number', $item['serial_number'] ?? '') }}"
                                                       @input.debounce.500ms="autoSave('serial_number', $event.target.value, $data)"
                                                       class="block w-32 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                            {{-- Purchase Date --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <input type="date"
                                                       name="assets[{{ $localIndex }}][purchase_date]"
                                                       value="{{ old('assets.'.$localIndex.'.purchase_date', $item['purchase_date'] ?? '') }}"
                                                       @change="autoSave('purchase_date', $event.target.value, $data)"
                                                       class="block w-36 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                            {{-- Purchase Cost --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <input type="number"
                                                       step="any"
                                                       name="assets[{{ $localIndex }}][purchase_cost]"
                                                       value="{{ old('assets.'.$localIndex.'.purchase_cost', $item['purchase_cost'] ?? '') }}"
                                                       @input.debounce.500ms="autoSave('purchase_cost', $event.target.value, $data)"
                                                       class="block w-28 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                            {{-- Remarks --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <input type="text"
                                                       name="assets[{{ $localIndex }}][remarks]"
                                                       value="{{ old('assets.'.$localIndex.'.remarks', $item['remarks'] ?? '') }}"
                                                       @input.debounce.500ms="autoSave('remarks', $event.target.value, $data)"
                                                       class="block w-40 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="11" class="px-3 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                                {{ __('assets.no_data_extracted') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- ─── Pagination Links ────────────────────────────────────── --}}
                        @if ($paginatedData->hasPages())
                            <div class="mt-5 flex flex-col sm:flex-row items-center justify-between gap-4">
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ __('assets.review_page_info', [
                                        'from'  => number_format($paginatedData->firstItem()),
                                        'to'    => number_format($paginatedData->lastItem()),
                                        'total' => number_format($total),
                                    ]) }}
                                </p>
                                <div class="text-sm">
                                    {{ $paginatedData->links('assets.import.partials.pagination', ['invalidPages' => $invalidPages]) }}
                                </div>
                            </div>
                        @endif

                        {{-- ─── Bottom Action Bar ───────────────────────────────────── --}}
                        <div class="mt-6 pt-4 border-t border-gray-200/50 dark:border-gray-700/50 flex flex-wrap justify-between items-center gap-4">

                            {{-- Back Button --}}
                            <a href="{{ route('assets.import-mapping') }}"
                               class="inline-flex items-center gap-2 px-4 py-2.5 bg-white/90 dark:bg-gray-800/90 backdrop-blur-md border border-gray-200/50 dark:border-gray-700/50 rounded-xl font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700 transition-all shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                {{ __('assets.back_to_mapping') }}
                            </a>

                            <div class="flex items-center gap-4">
                                {{-- Row counter --}}
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    {{ __('assets.total_rows') }}
                                    <span class="text-gray-900 dark:text-gray-100 font-semibold ml-1" x-text="Number(totalRows).toLocaleString()"></span>
                                </p>

                                {{-- Save All button (bottom) --}}
                                <button type="submit"
                                        :disabled="isValidating"
                                        class="inline-flex items-center gap-2 px-6 py-2.5 bg-accent border border-transparent rounded-xl font-semibold text-sm text-white uppercase tracking-widest hover:opacity-90 shadow-lg shadow-accent/30 focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                    <svg x-show="!isValidating" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <svg x-show="isValidating" x-cloak class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ __('assets.save_all_data') }}
                                </button>
                            </div>
                        </div>

                    </div>{{-- /p-4 --}}
                </form>
            </div>

            {{-- ─── Progress Stepper (Bottom) ──────────────────────────────────── --}}
            @include('assets.import.partials.stepper', ['currentStep' => 4])

            {{-- Pre-flight Confirmation Modal --}}
            <dialog id="preflight_modal" class="modal modal-bottom sm:modal-middle backdrop-blur-sm">
                <div class="modal-box bg-white/95 dark:bg-gray-800/95 backdrop-blur-md border border-gray-200/50 dark:border-gray-700/50 shadow-2xl rounded-2xl p-6 text-gray-900 dark:text-gray-100">
                    <!-- Header -->
                    <div class="flex justify-between items-center pb-4 border-b border-gray-200/50 dark:border-gray-700/50 mb-4">
                        <h3 class="font-bold text-lg text-gray-900 dark:text-white">
                            {{ __('assets.confirm_import_title') }}
                        </h3>
                        <form method="dialog">
                            <button class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </form>
                    </div>
                    
                    <!-- Content -->
                    <div class="space-y-4 my-4">
                        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed font-medium" x-text="validText">
                        </p>
                        <div x-show="invalidCount > 0" x-cloak
                             class="p-4 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/50 rounded-xl text-sm text-amber-700 dark:text-amber-400 flex items-start gap-2.5 shadow-sm">
                            <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span class="leading-relaxed" x-text="invalidText"></span>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="modal-action border-t border-gray-200/50 dark:border-gray-700/50 pt-4 flex justify-end gap-3">
                        <form method="dialog">
                            <button class="btn btn-sm btn-ghost border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                                {{ __('assets.close') }}
                            </button>
                        </form>
                        <button type="button" @click="saveAll()"
                                class="btn btn-sm bg-accent border-transparent text-white hover:opacity-90 rounded-lg">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __('assets.confirm_save') }}
                        </button>
                    </div>
                </div>
            </dialog>

            {{-- Glassmorphic Save Progress Tracker Modal --}}
            <dialog id="save_progress_modal" class="modal backdrop-blur-sm">
                <div class="modal-box bg-white/95 dark:bg-gray-800/95 backdrop-blur-md border border-gray-200/50 dark:border-gray-700/50 shadow-2xl rounded-2xl p-6 text-gray-900 dark:text-gray-100">

                    <!-- Loader and Title -->
                    <div class="flex flex-col items-center text-center mb-6">
                        <div class="w-16 h-16 rounded-2xl mb-4 flex items-center justify-center transition-all duration-500 shadow-lg bg-gradient-to-br from-accent/20 to-accent/5 text-accent ring-4 ring-accent/10">
                            <!-- Processing spinner -->
                            <svg class="w-8 h-8 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25 stroke-gray-200 dark:stroke-gray-700" cx="12" cy="12" r="10" stroke-width="4"></circle>
                                <path class="opacity-75 fill-current text-accent" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>

                        <h3 class="font-bold text-lg tracking-tight">{{ __('assets.saving_database') }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1" x-text="savePercentage + '%'"></p>
                    </div>

                    <!-- Progress Bar -->
                    <progress class="progress progress-primary w-full animate-pulse" :value="savePercentage" max="100"></progress>
                </div>
            </dialog>

            {{-- Delete Confirmation Modal — copy switches between the singular and
                 count-aware wording depending on how many rows are selected.
                 A native confirm() can't be styled or localized, which is why this
                 modal exists in the first place. --}}
            <dialog id="delete_row_modal" class="modal modal-bottom sm:modal-middle backdrop-blur-sm">
                <div class="modal-box bg-white/95 dark:bg-gray-800/95 backdrop-blur-md border border-gray-200/50 dark:border-gray-700/50 shadow-2xl rounded-2xl p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex justify-between items-center pb-4 border-b border-gray-200/50 dark:border-gray-700/50 mb-4">
                        <h3 class="font-bold text-lg text-gray-900 dark:text-white" x-text="deleteTitleText">
                        </h3>
                        <form method="dialog">
                            <button @click="cancelDeleteRow()" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </form>
                    </div>

                    <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed my-4" x-text="deleteConfirmText">
                    </p>

                    <div class="modal-action border-t border-gray-200/50 dark:border-gray-700/50 pt-4 flex justify-end gap-3">
                        <button type="button" @click="cancelDeleteRow()"
                                class="btn btn-sm btn-ghost border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                            {{ __('messages.cancel') }}
                        </button>
                        <button type="button" @click="confirmDeleteRow()"
                                class="btn btn-sm btn-error border-transparent text-white hover:opacity-90 rounded-lg">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            {{ __('messages.yes_delete') }}
                        </button>
                    </div>
                </div>
            </dialog>

            {{-- Row Context Menu — Blade-rendered and localized, not the native menu.
                 Positioned at the cursor; openRowMenu() decides whether we're even
                 allowed to take over the right-click. --}}
            <div x-show="menuOpen" x-cloak
                 @click.outside="closeRowMenu()"
                 @scroll.window="closeRowMenu()"
                 @resize.window="closeRowMenu()"
                 :style="`top: ${menuY}px; left: ${menuX}px`"
                 class="fixed z-50 min-w-[13rem] py-1.5 rounded-xl bg-white/95 dark:bg-gray-800/95 backdrop-blur-md border border-gray-200/50 dark:border-gray-700/50 shadow-2xl text-sm">
                <div class="px-3 py-1.5 text-xs font-semibold text-gray-400 dark:text-gray-500 border-b border-gray-200/50 dark:border-gray-700/50 mb-1"
                     x-text="selectedText"></div>
                <button type="button" @click="requestDeleteSelected()"
                        class="w-full flex items-center gap-2.5 px-3 py-2 text-left text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    <span x-text="deleteSelectedText"></span>
                </button>
                <button type="button" @click="clearSelection(); closeRowMenu()"
                        class="w-full flex items-center gap-2.5 px-3 py-2 text-left text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    {{ __('assets.clear_selection') }}
                </button>
            </div>

        </div>{{-- /max-w --}}
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('importReview', (config) => ({
                isValidating: false,
                savePercentage: 0,
                isSaving: false,
                validCount: config.validCount || 0,
                invalidCount: config.invalidCount || 0,
                invalidPages: config.invalidPages || [],
                totalRows: config.total || 0,
                pageRowIds: config.pageRowIds || [],
                selectedIds: [],
                menuOpen: false,
                menuX: 0,
                menuY: 0,

                get validText() {
                    return @json(__('assets.preflight_valid_rows', ['count' => '__COUNT__'])).replace('__COUNT__', Number(this.validCount).toLocaleString());
                },

                get invalidText() {
                    return @json(__('assets.preflight_invalid_warning', ['count' => '__COUNT__'])).replace('__COUNT__', Number(this.invalidCount).toLocaleString());
                },

                // ── Selection ────────────────────────────────────────────────
                get selectionCount() { return this.selectedIds.length; },
                get allSelected() {
                    return this.pageRowIds.length > 0 && this.selectedIds.length === this.pageRowIds.length;
                },
                get someSelected() {
                    return this.selectedIds.length > 0 && !this.allSelected;
                },

                get selectedText() {
                    return @json(__('assets.rows_selected', ['count' => '__COUNT__'])).replace('__COUNT__', Number(this.selectionCount).toLocaleString());
                },
                get deleteSelectedText() {
                    return @json(__('assets.delete_selected', ['count' => '__COUNT__'])).replace('__COUNT__', Number(this.selectionCount).toLocaleString());
                },
                get bulkHintText() {
                    return @json(__('assets.bulk_edit_hint', ['count' => '__COUNT__'])).replace('__COUNT__', Number(this.selectionCount).toLocaleString());
                },
                get deleteTitleText() {
                    return this.selectionCount > 1
                        ? @js(__('assets.delete_rows_title'))
                        : @js(__('assets.delete_row_title'));
                },
                get deleteConfirmText() {
                    if (this.selectionCount <= 1) return @js(__('assets.delete_row_confirm'));
                    return @json(__('assets.delete_rows_confirm', ['count' => '__COUNT__'])).replace('__COUNT__', Number(this.selectionCount).toLocaleString());
                },

                isSelected(rowId) { return this.selectedIds.includes(rowId); },

                toggleRow(rowId) {
                    const i = this.selectedIds.indexOf(rowId);
                    if (i === -1) this.selectedIds.push(rowId);
                    else this.selectedIds.splice(i, 1);
                },

                toggleAll() {
                    this.selectedIds = this.allSelected ? [] : [...this.pageRowIds];
                },

                clearSelection() { this.selectedIds = []; },

                // Escape unwinds one layer at a time: close the menu first, and
                // only drop the selection once there's no menu left to dismiss.
                onEscape() {
                    if (this.menuOpen) { this.closeRowMenu(); return; }
                    this.clearSelection();
                },

                // ── Context menu ─────────────────────────────────────────────
                openRowMenu(e, rowId) {
                    // Don't steal the browser's own menu from someone working inside a
                    // field — they're far more likely to want copy/paste than our menu.
                    // An input that isn't focused is fair game.
                    const t = e.target;
                    if (t.matches && t.matches('input, select, textarea') && document.activeElement === t) {
                        return;
                    }

                    e.preventDefault();

                    // Right-clicking outside the selection acts on that row alone,
                    // matching how file managers behave.
                    if (!this.isSelected(rowId)) this.selectedIds = [rowId];

                    // Keep the menu inside the viewport near the right/bottom edges.
                    this.menuX = Math.min(e.clientX, window.innerWidth - 230);
                    this.menuY = Math.min(e.clientY, window.innerHeight - 140);
                    this.menuOpen = true;
                },

                closeRowMenu() { this.menuOpen = false; },

                // ── Bulk column edit ─────────────────────────────────────────
                // One request for the whole selection. Fanning this out into a
                // per-row auto-save would rebuild the request-per-cell cost this
                // page was already refactored away from.
                async bulkUpdate(fieldName, newValue) {
                    if (this.selectionCount === 0) return;

                    try {
                        const response = await fetch('{{ route("assets.import.bulk-update-rows") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                row_ids: this.selectedIds,
                                field_name: fieldName,
                                new_value: newValue
                            })
                        });

                        if (!response.ok) {
                            throw new Error('Bulk update request failed');
                        }

                        const data = await response.json();
                        if (!data.success) {
                            throw new Error(data.message || 'Bulk update failed');
                        }

                        this.validCount = data.validCount;
                        this.invalidCount = data.invalidCount;
                        this.totalRows = data.totalCount;
                        this.invalidPages = data.invalidPages;

                        // Repaint each affected row's invalid highlighting in place. A
                        // reload would be simpler but would throw away the selection
                        // the user is still working with.
                        this.applyRowFlags(data.rowFlags || {});
                        this.syncVisibleCells(fieldName, newValue);
                    } catch (err) {
                        console.error('Bulk update failed:', err);
                        {{-- @js, not @json: @json splits on commas and would swallow
                             the ['message' => ...] argument. Same reasoning as below. --}}
                        const message = @js(__('assets.bulk_update_error', ['message' => '__MSG__']))
                            .replace('__MSG__', err.message);
                        alert(message);
                    }
                },

                // The server is the source of truth for is_invalid; push its answer
                // into each row's own Alpine scope via the DOM node we already tag
                // with data-row-id. Alpine.$data() is the supported way in — the
                // _x_dataStack property it wraps is internal and has moved before.
                applyRowFlags(rowFlags) {
                    Object.entries(rowFlags).forEach(([id, isInvalid]) => {
                        const tr = document.querySelector(`tr[data-row-id="${id}"]`);
                        if (!tr) return;
                        const scope = Alpine.$data(tr);
                        if (scope) scope.isInvalid = isInvalid;
                    });
                },

                // Mirror the committed value into the per-row inputs so the table
                // shows what was actually saved without a round trip.
                syncVisibleCells(fieldName, newValue) {
                    this.selectedIds.forEach((id) => {
                        const tr = document.querySelector(`tr[data-row-id="${id}"]`);
                        if (!tr) return;
                        const field = tr.querySelector(`[name$="[${fieldName}]"]`);
                        if (field) field.value = newValue;
                    });
                },

                // ── Delete ───────────────────────────────────────────────────
                // Opens delete_row_modal instead of the browser's native confirm() —
                // confirm() can't be styled or localized through Blade at all, which is
                // why it was stuck showing hardcoded Indonesian regardless of locale.
                requestDeleteSelected() {
                    if (this.selectionCount === 0) return;
                    this.closeRowMenu();
                    document.getElementById('delete_row_modal').showModal();
                },

                cancelDeleteRow() {
                    document.getElementById('delete_row_modal').close();
                },

                async confirmDeleteRow() {
                    document.getElementById('delete_row_modal').close();
                    const rowIds = [...this.selectedIds];
                    if (rowIds.length === 0) return;

                    try {
                        const response = await fetch('{{ route("assets.import.delete-rows") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                row_ids: rowIds
                            })
                        });

                        if (!response.ok) {
                            throw new Error('Delete request failed');
                        }

                        const data = await response.json();
                        if (data.success) {
                            this.validCount = data.validCount;
                            this.invalidCount = data.invalidCount;
                            this.totalRows = data.totalCount;
                            this.invalidPages = data.invalidPages;
                            this.clearSelection();

                            // Reload so the # column and pagination re-settle. Row identity
                            // no longer depends on this — every row carries its own id —
                            // but the displayed numbering does.
                            window.location.reload();
                        }
                    } catch (err) {
                        console.error('Delete failed:', err);
                        {{-- Blade comment (not a // one): a literal "@word(" in a JS comment
                             here would itself get parsed as a directive, since script blocks
                             aren't exempt from directive scanning. @js is used because @json
                             splits its expression on commas, which would swallow the
                             ['message' => ...] argument as the encoding-flags parameter. --}}
                        const template = rowIds.length > 1
                            ? @js(__('assets.delete_rows_error', ['message' => '__MSG__']))
                            : @js(__('assets.delete_row_error', ['message' => '__MSG__']));
                        alert(template.replace('__MSG__', err.message));
                    }
                },

                async autoSave(fieldName, newValue, trScope) {
                    try {
                        const response = await fetch('{{ route("assets.import.update-row") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                row_id: trScope.rowId,
                                field_name: fieldName,
                                new_value: newValue
                            })
                        });

                        if (!response.ok) {
                            throw new Error('Auto-save request failed');
                        }

                        const data = await response.json();
                        if (data.success) {
                            trScope.isInvalid = data.is_invalid;
                            this.invalidPages = data.invalidPages;
                            this.validCount = data.validCount;
                            this.invalidCount = data.invalidCount;
                        }
                    } catch (err) {
                        console.error('Auto-save failed:', err);
                    }
                },

                async triggerPreflight() {
                    if (this.isValidating) return;
                    this.isValidating = true;

                    // Serialize form data
                    const form = document.getElementById('review-form');
                    const formData = new FormData(form);

                    try {
                        const response = await fetch('{{ route("assets.import-calculate-validation") }}', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        });

                        if (!response.ok) {
                            throw new Error('Validation failed');
                        }

                        const data = await response.json();
                        if (data.success) {
                            this.validCount = data.validCount;
                            this.invalidCount = data.invalidCount;
                            document.getElementById('preflight_modal').showModal();
                        }
                    } catch (err) {
                        console.error('Pre-flight validation failed:', err);
                    } finally {
                        this.isValidating = false;
                    }
                },

                async saveAll() {
                    document.getElementById('preflight_modal').close();
                    document.getElementById('save_progress_modal').showModal();
                    await this.processSaving();
                },

                async processSaving() {
                    this.isSaving = true;
                    this.savePercentage = 0;
                    
                    let offset = 0;
                    const limit = 500;
                    let isCompleted = false;
                    const totalValidRows = parseInt(this.validCount) || 0;

                    if (totalValidRows === 0) {
                        this.savePercentage = 100;
                        setTimeout(() => {
                            window.location.href = '{{ route("assets.index") }}';
                        }, 500);
                        return;
                    }

                    const csrfToken = '{{ csrf_token() }}';

                    while (!isCompleted) {
                        try {
                            const response = await fetch('{{ route("assets.import-store-batch") }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({
                                    offset: offset,
                                    limit: limit
                                })
                            });

                            if (!response.ok) {
                                throw new Error('Failed to save batch at offset ' + offset);
                            }

                            const data = await response.json();
                            if (!data.success) {
                                throw new Error(data.message || 'Failed to save batch');
                            }

                            offset += limit;
                            this.savePercentage = Math.min(Math.round((offset / totalValidRows) * 100), 100);

                            isCompleted = data.is_completed;
                        } catch (err) {
                            console.error('Batch save failed:', err);
                            {{-- @js: same reasoning as the delete-row error above — @json
                                 would eat ['message' => ...] as its encoding-flags arg. --}}
                            const message = @js(__('assets.batch_save_error', ['message' => '__MSG__']))
                                .replace('__MSG__', err.message);
                            alert(message);
                            this.isSaving = false;
                            document.getElementById('save_progress_modal').close();
                            return;
                        }
                    }

                    this.savePercentage = 100;
                    setTimeout(() => {
                        window.location.href = '{{ route("assets.index") }}';
                    }, 500);
                }
            }));
        });
    </script>
</x-app-layout>
