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

        // The bulk-edit header selects render from Alpine state, not from Blade.
        // They live inside <template x-if="selectionCount > 0">, and Alpine re-clones
        // an x-if body from the pristine template every time the condition flips —
        // so an <option> appended to the live DOM is discarded the next time the
        // selection is cleared and remade, and when nothing is selected the <select>
        // is not in the document to append to at all. Driving them from an array
        // quick-add can push into is the only thing that survives both.
        //
        // A locked user gets an empty department payload: the only consumer is the
        // bulk header select, which is replaced by a disabled one for them. Sending
        // the list anyway would publish every department in the property to a page
        // that offers none of them.
        $entityOptions = [
            'category'   => $categories->map(fn ($c) => ['id' => (int) $c->id, 'name' => $c->name])->values(),
            'department' => $lockedDepartmentId !== null
                ? collect()
                : $departments->map(fn ($d) => ['id' => (int) $d->id, 'name' => $d->name])->values(),
        ];
    @endphp

    {{-- @js, not @json: these happen to be integers today, but @json emits literal
         double quotes for any string value, which would terminate this attribute. --}}
    <div class="py-8"
         @datagrid-bulk-updated="onGridBulkUpdated($event.detail)"
         @datagrid-bulk-deleted="onGridBulkDeleted($event.detail)"
         x-data="importReview({ validCount: @js($validCount), invalidCount: @js($invalidCount), invalidPages: @js($invalidPages), total: @js($total), pageRowIds: @js($pageRowIds), categories: @js($entityOptions['category']), departments: @js($entityOptions['department']) })">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- ─── Progress Stepper (Top) ─────────────────────────────────────── --}}
            @include('assets.import.partials.stepper', ['currentStep' => 4])

            {{-- ─── Main Card ───────────────────────────────────────────────────── --}}
            <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md shadow-xl sm:rounded-2xl border border-gray-200/50 dark:border-gray-700/50 overflow-hidden">
                {{-- No action: this form is never submitted natively. Submit is always
                     intercepted by triggerPreflight(), and saving goes through
                     saveAll() → storeBatch(). It exists only to group the fields
                     that triggerPreflight() serialises via FormData.

                     autocomplete="off" is not about autofill suggestions. Browsers
                     restore *dirty* control values across a reload, matching them by
                     form + name + index — and deleting rows reloads this page. Every
                     cell the user edits is dirty, and so is every cell a bulk edit
                     writes through (setting .value sets the dirty flag), so without
                     this the pre-delete values get painted back onto whichever rows
                     moved up into their positions. The id-keyed names below are the
                     other half of the same fix. --}}
                <form method="POST" id="review-form" autocomplete="off" @submit.prevent="triggerPreflight()">
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

                        {{-- ─── Standardized DataGrid ──────────────────────────────── --}}
                        @php
                            $reviewStatuses = [
                                ['id' => 'in_service', 'name' => __('assets.in_service')],
                                ['id' => 'out_of_service', 'name' => __('assets.out_of_service')],
                                ['id' => 'disposed', 'name' => __('assets.disposed')],
                            ];
                        @endphp

                        <x-datagrid
                            :paginator="$paginatedData"
                            :selectable="true"
                            :page-row-ids="$pageRowIds"
                            :bulk-update-url="route('assets.import.bulk-update-rows')"
                            :bulk-delete-url="route('assets.import.delete-rows')"
                            :reload-on-bulk-update="false"
                            :reload-on-bulk-delete="true"
                            dialog-id="delete_row_modal"
                            tbody-id="review-tbody"
                            :options="[
                                'categories'  => $entityOptions['category'],
                                'departments' => $entityOptions['department'],
                                'statuses'    => $reviewStatuses,
                            ]"
                            pagination-view="assets.import.partials.pagination"
                            :pagination-data="['invalidPages' => $invalidPages]"
                        >
                            <x-slot:head>
                                <x-datagrid.th :label="__('assets.tag')" :required="true" field="tag" bulk-type="text" bulk-width="w-32" />
                                <x-datagrid.th :label="__('assets.name')" :required="true" field="name" bulk-type="text" bulk-width="w-40" />

                                <x-datagrid.th :label="__('assets.category')" :required="true" field="category_id" bulk-type="select" bulk-options="categories" bulk-width="w-36">
                                    <x-slot:labelExtra>
                                        @include('assets.import.partials.quick-add-trigger', [
                                            'entityType'  => 'category',
                                            'modelClass'  => \App\Models\Category::class,
                                            'tooltipText' => __('assets.quick_add_category'),
                                        ])
                                    </x-slot:labelExtra>
                                </x-datagrid.th>

                                <x-datagrid.th
                                    :label="__('assets.department')"
                                    :required="true"
                                    field="department_id"
                                    bulk-type="select"
                                    bulk-options="departments"
                                    bulk-width="w-36"
                                    :locked="$lockedDepartmentId !== null"
                                    :locked-label="$lockedDepartmentName"
                                    :locked-title="__('assets.auto_assigned_department_warning')"
                                >
                                    <x-slot:labelExtra>
                                        @include('assets.import.partials.quick-add-trigger', [
                                            'entityType'  => 'department',
                                            'modelClass'  => \App\Models\Department::class,
                                            'tooltipText' => __('assets.quick_add_department'),
                                        ])
                                    </x-slot:labelExtra>
                                </x-datagrid.th>

                                <x-datagrid.th :label="__('messages.locations') ?? 'Location'" field="location" bulk-type="text" bulk-width="w-32" />
                                <x-datagrid.th :label="__('assets.status')" field="status" bulk-type="select" bulk-options="statuses" bulk-width="w-32" />
                                <x-datagrid.th :label="__('assets.model_brand')" field="model" bulk-type="text" bulk-width="w-32" />
                                <x-datagrid.th :label="__('assets.serial_number')" field="serial_number" bulk-type="text" bulk-width="w-32" />
                                <x-datagrid.th :label="__('assets.purchase_date')" field="purchase_date" bulk-type="date" bulk-width="w-36" />
                                <x-datagrid.th :label="__('assets.purchase_cost')" field="purchase_cost" bulk-type="number" bulk-width="w-28" />
                                <x-datagrid.th :label="__('assets.remarks')" field="remarks" bulk-type="text" bulk-width="w-40" />
                            </x-slot:head>

                                    {{-- ─────────────────────────────────────────────────────────────
                                         N+1 ELIMINATED: $categories and $departments are fetched
                                         ONCE in the controller and passed as collections.
                                         The Blade loop below NEVER touches the database.
                                    ──────────────────────────────────────────────────────────────── --}}
                                    @forelse($paginatedData as $localIndex => $item)
                                        @php
                                            $globalIndex = $pageOffset + $localIndex;
                                            $rowKey      = $item['id'];
                                            $combined    = trim(($item['brand'] ?? '') . ' ' . ($item['model'] ?? ''));
                                        @endphp
                                        <x-datagrid.row
                                            :row-id="$item['id']"
                                            :row-number="$globalIndex + 1"
                                            :selectable="true"
                                            :invalid="(bool) ($item['is_invalid'] ?? false)"
                                        >
                                            @if (!empty($coercionNotes[$item['id']] ?? []))
                                                <x-slot:rowMeta>
                                                    <x-tooltip width="w-72" class="ml-1 align-middle" @click.stop>
                                                        <span data-coercion-warning="{{ $item['id'] }}"
                                                              aria-label="{{ __('assets.coercion_row_warning') }}"
                                                              class="text-amber-500 group-hover:text-amber-600 focus:text-amber-600 transition-colors">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                            </svg>
                                                        </span>
                                                        <x-slot:content>
                                                            <p class="font-semibold mb-1">{{ __('assets.coercion_row_warning') }}</p>
                                                            @foreach ($coercionNotes[$item['id']] as $field => $rawValue)
                                                                <p>{{ __('assets.coercion_note_' . ($field === 'purchase_date' ? 'date' : 'cost'), ['value' => $rawValue]) }}</p>
                                                            @endforeach
                                                        </x-slot:content>
                                                    </x-tooltip>
                                                </x-slot:rowMeta>
                                            @endif

                                            {{-- Tag --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <input type="text"
                                                       name="assets[{{ $rowKey }}][tag]"
                                                       value="{{ old('assets.'.$rowKey.'.tag', $item['tag'] ?? ('AST-' . strtoupper(\Str::random(6)))) }}"
                                                       required
                                                       @input.debounce.500ms="autoSave('tag', $event.target.value, $data)"
                                                       class="block w-32 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                            {{-- Name --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <input type="text"
                                                       name="assets[{{ $rowKey }}][name]"
                                                       value="{{ old('assets.'.$rowKey.'.name', $item['name'] ?? '') }}"
                                                       required
                                                       @input.debounce.500ms="autoSave('name', $event.target.value, $data)"
                                                       class="block w-40 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                            {{-- Category — uses pre-fetched $categories collection, ZERO DB queries --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <select name="assets[{{ $rowKey }}][category_id]"
                                                        @change="autoSave('category_id', $event.target.value, $data)"
                                                        data-entity-select="category"
                                                        class="block w-36 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white">
                                                    <option value="">{{ __('assets.select_placeholder') }}</option>
                                                    @foreach($categories as $cat)
                                                        <option value="{{ $cat->id }}"
                                                            {{ old('assets.'.$rowKey.'.category_id', $item['category_id'] ?? '') == $cat->id ? 'selected' : '' }}>
                                                            {{ $cat->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>

                                            {{-- Department — uses pre-fetched $departments collection, ZERO DB queries.
                                                 Locked to the user's own for staff without executive oversight, the
                                                 same treatment bulk-manual.blade.php and assets/create.blade.php give
                                                 it. The locked branch carries no name and no data-entity-select: the
                                                 form is never submitted natively, and a select the user cannot use
                                                 must not grow options when quick-add creates a department. --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                @if ($lockedDepartmentId !== null)
                                                    <select disabled
                                                            data-department-locked
                                                            title="{{ __('assets.auto_assigned_department_warning') }}"
                                                            class="block w-36 border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-xs bg-gray-100 dark:bg-gray-900/70 text-gray-500 dark:text-gray-400 cursor-not-allowed">
                                                        <option>{{ $lockedDepartmentName }}</option>
                                                    </select>
                                                @else
                                                    <select name="assets[{{ $rowKey }}][department_id]"
                                                            @change="autoSave('department_id', $event.target.value, $data)"
                                                            data-entity-select="department"
                                                            class="block w-36 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white">
                                                        <option value="">{{ __('assets.select_placeholder') }}</option>
                                                        @foreach($departments as $dept)
                                                            <option value="{{ $dept->id }}"
                                                                {{ old('assets.'.$rowKey.'.department_id', $item['department_id'] ?? '') == $dept->id ? 'selected' : '' }}>
                                                                {{ $dept->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @endif
                                            </td>

                                            {{-- Location --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <input type="text"
                                                       name="assets[{{ $rowKey }}][location]"
                                                       value="{{ old('assets.'.$rowKey.'.location', $item['location'] ?? '') }}"
                                                       @input.debounce.500ms="autoSave('location', $event.target.value, $data)"
                                                       class="block w-32 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                            {{-- Status --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <select name="assets[{{ $rowKey }}][status]"
                                                        required
                                                        @change="autoSave('status', $event.target.value, $data)"
                                                        class="block w-32 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white">
                                                    <option value="in_service"    {{ old('assets.'.$rowKey.'.status', $item['status'] ?? '') == 'in_service'    ? 'selected' : '' }}>{{ __('assets.in_service') }}</option>
                                                    <option value="out_of_service"{{ old('assets.'.$rowKey.'.status', $item['status'] ?? '') == 'out_of_service' ? 'selected' : '' }}>{{ __('assets.out_of_service') }}</option>
                                                    <option value="disposed"      {{ old('assets.'.$rowKey.'.status', $item['status'] ?? '') == 'disposed'       ? 'selected' : '' }}>{{ __('assets.disposed') }}</option>
                                                </select>
                                            </td>

                                            {{-- Model/Brand --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <input type="text"
                                                       name="assets[{{ $rowKey }}][model]"
                                                       value="{{ old('assets.'.$rowKey.'.model', $combined) }}"
                                                       @input.debounce.500ms="autoSave('model', $event.target.value, $data)"
                                                       class="block w-32 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                            {{-- Serial Number --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <input type="text"
                                                       name="assets[{{ $rowKey }}][serial_number]"
                                                       value="{{ old('assets.'.$rowKey.'.serial_number', $item['serial_number'] ?? '') }}"
                                                       @input.debounce.500ms="autoSave('serial_number', $event.target.value, $data)"
                                                       class="block w-32 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                            {{-- Purchase Date --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <input type="date"
                                                       name="assets[{{ $rowKey }}][purchase_date]"
                                                       value="{{ old('assets.'.$rowKey.'.purchase_date', $item['purchase_date'] ?? '') }}"
                                                       @change="autoSave('purchase_date', $event.target.value, $data)"
                                                       class="block w-36 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                            {{-- Purchase Cost --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <input type="number"
                                                       step="any"
                                                       name="assets[{{ $rowKey }}][purchase_cost]"
                                                       value="{{ old('assets.'.$rowKey.'.purchase_cost', $item['purchase_cost'] ?? '') }}"
                                                       @input.debounce.500ms="autoSave('purchase_cost', $event.target.value, $data)"
                                                       class="block w-28 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                            {{-- Remarks --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <input type="text"
                                                       name="assets[{{ $rowKey }}][remarks]"
                                                       value="{{ old('assets.'.$rowKey.'.remarks', $item['remarks'] ?? '') }}"
                                                       @input.debounce.500ms="autoSave('remarks', $event.target.value, $data)"
                                                       class="block w-40 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                        </x-datagrid.row>
                                    @empty
                                        <tr>
                                            <td colspan="12" class="px-3 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                                {{ __('assets.no_data_extracted') }}
                                            </td>
                                        </tr>
                                    @endforelse
                        </x-datagrid>

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
            {{-- Delete confirmation modal is rendered by <x-datagrid> (<x-confirm-dialog id="delete_row_modal" />) --}}

            {{-- Quick Add Category/Department Modal.

                 Deliberately not a <form>: the review table is already wrapped in
                 #review-form, and while this dialog sits outside it, a nested form
                 here would be one more thing that can accidentally submit the page.
                 Fields are plain inputs bound with x-model and posted with fetch,
                 the same way every other write on this page works.

                 Both entity types share one dialog — they differ only by title and
                 the Executive Oversight checkbox, which no category has. --}}
            <dialog id="quick_add_entity_modal" class="modal modal-bottom sm:modal-middle backdrop-blur-sm">
                <div class="modal-box bg-white/95 dark:bg-gray-800/95 backdrop-blur-md border border-gray-200/50 dark:border-gray-700/50 shadow-2xl rounded-2xl p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex justify-between items-center pb-4 border-b border-gray-200/50 dark:border-gray-700/50 mb-4">
                        <h3 class="font-bold text-lg text-gray-900 dark:text-white" x-text="quickAddTitleText"></h3>
                        <form method="dialog">
                            <button @click="closeQuickAdd()" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </form>
                    </div>

                    <div class="space-y-4">
                        {{-- Name --}}
                        <div>
                            <label for="quick_add_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ __('messages.name') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="quick_add_name" x-model="quickAddName" maxlength="255"
                                   @keydown.enter.prevent="submitQuickAdd()"
                                   class="mt-1 block w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-sm dark:bg-gray-900/50 dark:text-white" />
                            <template x-for="msg in (quickAddErrors.name || [])" :key="msg">
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400" x-text="msg"></p>
                            </template>
                        </div>

                        {{-- Code — optional. Blank means the server generates one, the
                             same way Rapid-Add does for entities it creates. --}}
                        <div>
                            <label for="quick_add_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ __('messages.code') }}
                            </label>
                            <input type="text" id="quick_add_code" x-model="quickAddCode" maxlength="255"
                                   @keydown.enter.prevent="submitQuickAdd()"
                                   class="mt-1 block w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-sm dark:bg-gray-900/50 dark:text-white" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('assets.quick_add_code_hint') }}</p>
                            <template x-for="msg in (quickAddErrors.code || [])" :key="msg">
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400" x-text="msg"></p>
                            </template>
                        </div>

                        {{-- Notes --}}
                        <div>
                            <label for="quick_add_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ __('messages.notes') }}
                            </label>
                            <textarea id="quick_add_notes" x-model="quickAddNotes" maxlength="200" rows="2"
                                      placeholder="{{ __('messages.add_note_placeholder') }}"
                                      class="mt-1 block w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-sm dark:bg-gray-900/50 dark:text-white"></textarea>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('messages.max_200_chars') }}</p>
                            <template x-for="msg in (quickAddErrors.notes || [])" :key="msg">
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400" x-text="msg"></p>
                            </template>
                        </div>

                        {{-- Executive Oversight — departments only; the column does not
                             exist on categories and is not fillable there. --}}
                        <div x-show="quickAddType === 'department'" x-cloak>
                            <label class="flex items-center">
                                <input type="checkbox" x-model="quickAddOversight"
                                       class="h-4 w-4 text-accent border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 rounded focus:ring-accent">
                                <span class="ml-2 text-sm text-gray-600 dark:text-gray-300">{{ __('messages.executive_oversight') }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="modal-action border-t border-gray-200/50 dark:border-gray-700/50 pt-4 mt-4 flex justify-end gap-3">
                        <button type="button" @click="closeQuickAdd()"
                                class="btn btn-sm btn-ghost border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                            {{ __('messages.cancel') }}
                        </button>
                        <button type="button" @click="submitQuickAdd()" :disabled="quickAddSaving"
                                class="btn btn-sm bg-accent border-transparent text-white hover:opacity-90 rounded-lg disabled:opacity-50">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            {{ __('messages.save') }}
                        </button>
                    </div>
                </div>
            </dialog>

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
                categories: config.categories || [],
                departments: config.departments || [],

                quickAddType: 'category',
                quickAddName: '',
                quickAddCode: '',
                quickAddNotes: '',
                quickAddOversight: false,
                quickAddErrors: {},
                quickAddSaving: false,

                get validText() {
                    return @json(__('assets.preflight_valid_rows', ['count' => '__COUNT__'])).replace('__COUNT__', Number(this.validCount).toLocaleString());
                },

                get invalidText() {
                    return @json(__('assets.preflight_invalid_warning', ['count' => '__COUNT__'])).replace('__COUNT__', Number(this.invalidCount).toLocaleString());
                },

                get quickAddTitleText() {
                    return this.quickAddType === 'department'
                        ? @js(__('messages.add_new_department'))
                        : @js(__('messages.add_new_category'));
                },

                onGridBulkUpdated(detail) {
                    const data = detail?.data || {};
                    this.validCount = data.validCount ?? this.validCount;
                    this.invalidCount = data.invalidCount ?? this.invalidCount;
                    this.totalRows = data.totalCount ?? this.totalRows;
                    this.invalidPages = data.invalidPages ?? this.invalidPages;
                },

                onGridBulkDeleted(detail) {
                    const data = detail?.data || {};
                    this.validCount = data.validCount ?? this.validCount;
                    this.invalidCount = data.invalidCount ?? this.invalidCount;
                    this.totalRows = data.totalCount ?? this.totalRows;
                    this.invalidPages = data.invalidPages ?? this.invalidPages;
                },

                // ── Quick add category / department ──────────────────────────
                openQuickAdd(entityType) {
                    this.quickAddType      = entityType;
                    this.quickAddName      = '';
                    this.quickAddCode      = '';
                    this.quickAddNotes     = '';
                    this.quickAddOversight = false;
                    this.quickAddErrors    = {};
                    document.getElementById('quick_add_entity_modal').showModal();
                },

                closeQuickAdd() {
                    document.getElementById('quick_add_entity_modal').close();
                },

                async submitQuickAdd() {
                    if (this.quickAddSaving) return;
                    this.quickAddSaving = true;
                    this.quickAddErrors = {};

                    try {
                        const response = await fetch('{{ route("assets.import.quick-add-entity") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                entity_type: this.quickAddType,
                                name: this.quickAddName,
                                code: this.quickAddCode,
                                notes: this.quickAddNotes,
                                // Only meaningful for a department; the server ignores
                                // it outright for a category.
                                is_executive_oversight: this.quickAddOversight
                            })
                        });

                        // 422 is the normal "you left the name blank" path, so it stays
                        // inside the modal next to the field rather than becoming an alert.
                        if (response.status === 422) {
                            const payload = await response.json();
                            this.quickAddErrors = payload.errors || {};
                            if (!payload.errors && payload.message) {
                                this.quickAddErrors = { name: [payload.message] };
                            }
                            return;
                        }

                        if (!response.ok) {
                            throw new Error(response.status === 403
                                ? @js(__('messages.unauthorized'))
                                : 'Request failed');
                        }

                        const data = await response.json();
                        if (!data.success) {
                            throw new Error(data.message || 'Request failed');
                        }

                        this.addEntityOption(data.entity);
                        this.closeQuickAdd();
                    } catch (err) {
                        console.error('Quick add failed:', err);
                        {{-- @js, not @json: @json splits its expression on commas and
                             would swallow the ['message' => ...] argument. --}}
                        const message = @js(__('assets.quick_add_error', ['message' => '__MSG__']))
                            .replace('__MSG__', err.message);
                        alert(message);
                    } finally {
                        this.quickAddSaving = false;
                    }
                },

                addEntityOption(entity) {
                    const listKey = entity.type === 'department' ? 'departments' : 'categories';
                    const list = this[listKey];
                    const at = list.findIndex((option) => option.name.localeCompare(entity.name) > 0);
                    list.splice(at === -1 ? list.length : at, 0, { id: entity.id, name: entity.name });

                    this.$dispatch('datagrid-option-added', {
                        listKey,
                        option: { id: entity.id, name: entity.name },
                    });

                    document.querySelectorAll(`select[data-entity-select="${entity.type}"]`).forEach((select) => {
                        const option = new Option(entity.name, entity.id);
                        const before = [...select.options].find(
                            (o) => o.value !== '' && o.text.trim().localeCompare(entity.name) > 0
                        );
                        select.add(option, before || null);
                    });
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
