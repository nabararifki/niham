{{--
    Bulk Add Manual — standalone page, NOT part of the Smart Import wizard.

    Deliberately absent from this file: the import stepper, any fetch() call, and
    any reference to the staging endpoints (store-batch, update-row, delete-row,
    calculate-validation). This form submits natively to assets.bulk-manual.store.
    See BulkAssetEntryController for why that matters.
--}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('assets.bulk_add_manual') }}
        </h2>
    </x-slot>

    {{-- @js, not @json: @json emits literal double quotes, which terminate this
         attribute at the first one and leave Alpine with a SyntaxError. --}}
    <div class="py-8"
         x-data="bulkManualGrid({
            rows: @js($rows),
            lockedDepartmentId: @js($lockedDepartmentId)
         })">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md shadow-xl sm:rounded-2xl border border-gray-200/50 dark:border-gray-700/50 overflow-hidden">

                {{-- Two distinct blockers, two distinct messages. An empty $categories
                     alone cannot tell them apart: a correctly-selected property that simply
                     has no categories yet would otherwise be reported as "no property". --}}
                @if ($propertyId === null || $categories->isEmpty())
                    <div class="p-6">
                        <div class="bg-amber-100/60 dark:bg-amber-900/30 border border-amber-400/50 dark:border-amber-600/50 text-amber-800 dark:text-amber-200 px-4 py-3 rounded-lg flex items-start gap-3" role="alert">
                            <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span class="text-sm">
                                {{ $propertyId === null ? __('assets.no_active_property') : __('assets.no_categories_yet') }}
                            </span>
                        </div>
                        <div class="mt-6 flex flex-wrap items-center gap-3">
                            <a href="{{ route('assets.index') }}"
                               class="inline-flex items-center gap-2 px-4 py-2.5 bg-white/90 dark:bg-gray-800/90 border border-gray-200/50 dark:border-gray-700/50 rounded-xl font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700 transition-all shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                {{ __('assets.back_to_assets') }}
                            </a>

                            {{-- Actionable way out of the "no categories" dead end --}}
                            @if ($propertyId !== null)
                                @can('create', App\Models\Category::class)
                                    <a href="{{ route('categories.create') }}"
                                       class="inline-flex items-center gap-2 px-4 py-2.5 bg-accent border border-transparent rounded-xl font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 transition-all shadow-sm shadow-accent/30">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        {{ __('assets.create_category') }}
                                    </a>
                                @endcan
                            @endif
                        </div>
                    </div>
                @else
                    {{-- No `.prevent` on @submit: onSubmit() only calls preventDefault() when
                         validation fails, so the happy path is a plain native POST. --}}
                    <form method="POST"
                          action="{{ route('assets.bulk-manual.store') }}"
                          x-ref="form"
                          @submit="onSubmit($event)">
                        @csrf

                        {{-- Idempotency token, claimed once by store() (see controller). --}}
                        <input type="hidden" name="_form_id" value="{{ (string) Str::uuid() }}">

                        {{-- ─── Card Header ──────────────────────────────────────────── --}}
                        <div class="p-6 border-b border-gray-200/50 dark:border-gray-700/50 bg-gray-50/50 dark:bg-gray-900/50 flex flex-wrap justify-between items-center gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('assets.bulk_add_title') }}</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __('assets.bulk_add_manual_desc') }}</p>
                            </div>
                            <button type="submit"
                                    :disabled="submitting"
                                    class="inline-flex items-center px-5 py-2.5 bg-accent border border-transparent rounded-xl font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 transition-all shadow-sm shadow-accent/30 disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg x-show="!submitting" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <svg x-show="submitting" x-cloak class="animate-spin h-4 w-4 mr-2 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span x-show="!submitting">{{ __('assets.save_all_data') }}</span>
                                <span x-show="submitting" x-cloak>{{ __('assets.saving') }}</span>
                            </button>
                        </div>

                        <div class="p-4">

                            {{-- Server-side validation errors (summary — row indices may be
                                 re-numbered on re-render, so per-cell anchoring would mislead) --}}
                            @if ($errors->any())
                                <div class="mb-4 bg-red-100/50 border border-red-400/50 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg" role="alert">
                                    <strong class="font-bold">{{ __('assets.validation_error_title') }}</strong>
                                    <ul class="mt-2 list-disc list-inside text-sm">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            {{-- Client-side validation summary --}}
                            <div x-show="globalError" x-cloak
                                 class="mb-4 bg-red-100/50 border border-red-400/50 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg text-sm"
                                 x-text="globalError" role="alert"></div>

                            {{-- ─── Data Grid ──────────────────────────────────────────── --}}
                            <div class="overflow-x-auto w-full rounded-xl border border-gray-200/50 dark:border-gray-700/50">
                                <table class="min-w-full divide-y divide-gray-200/50 dark:divide-gray-700/50 text-sm">
                                    <thead class="bg-gray-50/80 dark:bg-gray-900/80">
                                        <tr>
                                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider w-8">#</th>
                                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.tag') }} *</th>
                                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.name') }} *</th>
                                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.category') }} *</th>
                                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.department') }}</th>
                                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.status') }} *</th>
                                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.model_brand') }}</th>
                                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.serial_number') }}</th>
                                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.purchase_date') }}</th>
                                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.purchase_cost') }}</th>
                                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.remarks') }}</th>
                                            <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.action') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200/50 dark:divide-gray-700/50 bg-white dark:bg-gray-800">
                                        {{-- Rows are client-side only. Fields are never disabled during
                                             editing — onSubmit() strips `name` from wholly-untouched rows
                                             right before the native POST, so "required" rejects genuinely
                                             invalid rows but not blank starter rows the user never opened. --}}
                                        <template x-for="(row, index) in rows" :key="index">
                                            <tr :data-row-index="index"
                                                :class="Object.prototype.hasOwnProperty.call(rowErrors, index)
                                                        ? 'bg-red-50 dark:bg-red-900/20 border-l-4 border-error'
                                                        : 'hover:bg-gray-50/50 dark:hover:bg-gray-700/30'"
                                                class="transition-colors">

                                                <td class="px-3 py-2.5 text-xs text-gray-400 dark:text-gray-500 font-mono" x-text="index + 1"></td>

                                                {{-- Tag --}}
                                                <td class="px-2 py-2.5 whitespace-nowrap">
                                                    <input type="text"
                                                           x-model="row.tag"
                                                           :name="`assets[${index}][tag]`"
                                                           :class="hasError(index, 'tag') ? 'border-red-500' : 'border-gray-300 dark:border-gray-600'"
                                                           class="block w-32 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                                </td>

                                                {{-- Name --}}
                                                <td class="px-2 py-2.5 whitespace-nowrap">
                                                    <input type="text"
                                                           x-model="row.name"
                                                           :name="`assets[${index}][name]`"
                                                           :class="hasError(index, 'name') ? 'border-red-500' : 'border-gray-300 dark:border-gray-600'"
                                                           class="block w-40 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                                </td>

                                                {{-- Category — options rendered once by Blade, cloned per row --}}
                                                <td class="px-2 py-2.5 whitespace-nowrap">
                                                    <select x-model="row.category_id"
                                                            :name="`assets[${index}][category_id]`"
                                                            :class="hasError(index, 'category_id') ? 'border-red-500' : 'border-gray-300 dark:border-gray-600'"
                                                            class="block w-36 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white">
                                                        <option value="">{{ __('assets.select_placeholder') }}</option>
                                                        @foreach($categories as $cat)
                                                            <option value="{{ $cat->id }}">
                                                                {{ $cat->name }}{{ Auth::user()->isSuperAdmin() && $cat->property ? ' - ' . $cat->property->name : '' }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>

                                                {{-- Department — locked to the user's own unless they have
                                                     executive oversight (mirrors assets/create.blade.php) --}}
                                                <td class="px-2 py-2.5 whitespace-nowrap">
                                                    <template x-if="lockedDepartmentId === null">
                                                        <select x-model="row.department_id"
                                                                :name="`assets[${index}][department_id]`"
                                                                class="block w-36 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white">
                                                            <option value="">{{ __('assets.select_placeholder') }}</option>
                                                            @foreach($departments as $dept)
                                                                <option value="{{ $dept->id }}">
                                                                    {{ $dept->name }}{{ Auth::user()->isSuperAdmin() && $dept->property ? ' - ' . $dept->property->name : '' }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </template>
                                                    <template x-if="lockedDepartmentId !== null">
                                                        <div>
                                                            <select disabled
                                                                    class="block w-36 border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-xs bg-gray-100 dark:bg-gray-900/70 text-gray-500 dark:text-gray-400 cursor-not-allowed">
                                                                <option>{{ optional(Auth::user()->department)->name }}</option>
                                                            </select>
                                                            <input type="hidden"
                                                                   :name="`assets[${index}][department_id]`"
                                                                   :value="lockedDepartmentId"
                                                                   />
                                                        </div>
                                                    </template>
                                                </td>

                                                {{-- Status --}}
                                                <td class="px-2 py-2.5 whitespace-nowrap">
                                                    <select x-model="row.status"
                                                            :name="`assets[${index}][status]`"
                                                            class="block w-32 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white">
                                                        <option value="in_service">{{ __('assets.in_service') }}</option>
                                                        <option value="out_of_service">{{ __('assets.out_of_service') }}</option>
                                                        <option value="disposed">{{ __('assets.disposed') }}</option>
                                                    </select>
                                                </td>

                                                {{-- Model/Brand --}}
                                                <td class="px-2 py-2.5 whitespace-nowrap">
                                                    <input type="text"
                                                           x-model="row.model"
                                                           :name="`assets[${index}][model]`"
                                                           class="block w-32 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                                </td>

                                                {{-- Serial Number --}}
                                                <td class="px-2 py-2.5 whitespace-nowrap">
                                                    <input type="text"
                                                           x-model="row.serial_number"
                                                           :name="`assets[${index}][serial_number]`"
                                                           class="block w-32 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                                </td>

                                                {{-- Purchase Date --}}
                                                <td class="px-2 py-2.5 whitespace-nowrap">
                                                    <input type="date"
                                                           x-model="row.purchase_date"
                                                           :name="`assets[${index}][purchase_date]`"
                                                           class="block w-36 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                                </td>

                                                {{-- Purchase Cost --}}
                                                <td class="px-2 py-2.5 whitespace-nowrap">
                                                    <input type="number" step="any"
                                                           x-model="row.purchase_cost"
                                                           :name="`assets[${index}][purchase_cost]`"
                                                           class="block w-28 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                                </td>

                                                {{-- Remarks --}}
                                                <td class="px-2 py-2.5 whitespace-nowrap">
                                                    <input type="text" maxlength="120"
                                                           x-model="row.remarks"
                                                           :name="`assets[${index}][remarks]`"
                                                           class="block w-40 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                                </td>

                                                {{-- Remove row (client-side only — nothing is persisted yet) --}}
                                                <td class="px-2 py-2.5 whitespace-nowrap text-center">
                                                    <button type="button"
                                                            @click="removeRow(index)"
                                                            class="text-red-500 hover:text-red-700 dark:hover:text-red-400 transition-colors p-1.5 bg-red-50 dark:bg-red-900/20 rounded-lg"
                                                            title="{{ __('assets.remove_row') }}">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            {{-- ─── Bottom Action Bar ───────────────────────────────────── --}}
                            <div class="mt-6 pt-4 border-t border-gray-200/50 dark:border-gray-700/50 flex flex-wrap justify-between items-center gap-4">

                                <div class="flex items-center gap-3">
                                    <a href="{{ route('assets.index') }}"
                                       class="inline-flex items-center gap-2 px-4 py-2.5 bg-white/90 dark:bg-gray-800/90 backdrop-blur-md border border-gray-200/50 dark:border-gray-700/50 rounded-xl font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700 transition-all shadow-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                                        {{ __('assets.back_to_assets') }}
                                    </a>

                                    <button type="button"
                                            @click="addRow()"
                                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-white/90 dark:bg-gray-800/90 backdrop-blur-md border border-gray-200/50 dark:border-gray-700/50 rounded-xl font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-700 transition-all shadow-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        {{ __('assets.add_row') }}
                                    </button>
                                </div>

                                <div class="flex items-center gap-4">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {{ __('assets.total_rows') }}
                                        <span class="text-gray-900 dark:text-gray-100 font-semibold ml-1" x-text="rows.length"></span>
                                    </p>

                                    <button type="submit"
                                            :disabled="submitting"
                                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-accent border border-transparent rounded-xl font-semibold text-sm text-white uppercase tracking-widest hover:opacity-90 shadow-lg shadow-accent/30 focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                        <svg x-show="!submitting" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <svg x-show="submitting" x-cloak class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span x-show="!submitting">{{ __('assets.save_all_data') }}</span>
                                        <span x-show="submitting" x-cloak>{{ __('assets.saving') }}</span>
                                    </button>
                                </div>
                            </div>

                        </div>{{-- /p-4 --}}
                    </form>
                @endif
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('bulkManualGrid', (config) => ({
                rows: config.rows,
                lockedDepartmentId: config.lockedDepartmentId,
                rowErrors: {},
                globalError: '',
                submitting: false,

                blankRow() {
                    return {
                        tag: '', name: '', category_id: '',
                        department_id: this.lockedDepartmentId ?? '',
                        status: 'in_service', model: '', serial_number: '',
                        purchase_date: '', purchase_cost: '', remarks: '',
                    };
                },

                addRow() {
                    this.rows.push(this.blankRow());
                },

                removeRow(index) {
                    this.rows.splice(index, 1);
                    if (!this.rows.length) this.addRow();
                },

                // A row the user never touched. onSubmit() uses this to exclude such
                // rows from the payload — see the note there on why that can't be done
                // by disabling the fields themselves.
                isEmptyRow(row) {
                    return !String(row.tag ?? '').trim()
                        && !String(row.name ?? '').trim()
                        && !row.category_id;
                },

                hasError(index, field) {
                    return (this.rowErrors[index] ?? []).includes(field);
                },

                validate() {
                    this.rowErrors = {};
                    this.globalError = '';

                    const filled = this.rows
                        .map((row, index) => ({ row, index }))
                        .filter(({ row }) => !this.isEmptyRow(row));

                    if (!filled.length) {
                        this.globalError = @js(__('assets.bulk_manual_no_rows'));
                        return false;
                    }

                    for (const { row, index } of filled) {
                        const missing = ['tag', 'name', 'category_id']
                            .filter(field => !String(row[field] ?? '').trim());
                        if (missing.length) this.rowErrors[index] = missing;
                    }

                    const firstBadIndex = Object.keys(this.rowErrors)[0];
                    if (firstBadIndex !== undefined) {
                        {{-- @js: Blade's @json splits its expression on commas, so the
                             second __() argument would be eaten as the flags parameter. --}}
                        this.globalError = @js(__('assets.bulk_manual_row_invalid', ['row' => '__ROW__']))
                            .replace('__ROW__', Number(firstBadIndex) + 1);
                        return false;
                    }

                    return true;
                },

                onSubmit(event) {
                    // Synchronous re-entrancy guard. Alpine flushes reactive DOM
                    // updates on a microtask, but the browser starts navigating as
                    // soon as this handler returns — so a plain flag, checked
                    // synchronously, is what reliably blocks a second click.
                    if (this.submitting) {
                        event.preventDefault();
                        return;
                    }
                    if (!this.validate()) {
                        event.preventDefault();
                        return;
                    }

                    // Exclude wholly-untouched starter rows from the native FormData.
                    // This used to be done with :disabled on every field, which is
                    // wrong: a field that starts disabled can never receive the
                    // keystroke that would make it non-empty, so nothing in the grid
                    // could ever be typed into. Stripping `name` here — synchronously,
                    // right before the browser serializes the form on this same submit
                    // event — excludes the row without ever blocking interaction.
                    this.$refs.form.querySelectorAll('tr[data-row-index]').forEach((tr) => {
                        const index = Number(tr.dataset.rowIndex);
                        if (this.isEmptyRow(this.rows[index])) {
                            tr.querySelectorAll('[name]').forEach((el) => el.removeAttribute('name'));
                        }
                    });

                    this.submitting = true;
                },
            }));
        });
    </script>
</x-app-layout>
