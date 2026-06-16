<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center text-gray-900 dark:text-gray-100 gap-4"
             x-data="{
                sheets: window.importPayload?.sheets || [],
                selectedSheet: {{ request()->query('sheet', 0) }},
                loadSheet() {
                    window.location.href = window.location.pathname + '?sheet=' + encodeURIComponent(this.selectedSheet);
                }
             }">
            <h2 class="font-semibold text-xl leading-tight">
                {{ __('assets.column_mapping') ?? 'Column Mapping' }}
            </h2>

            <!-- Sheet Selector (in header, right-aligned) -->
            <template x-if="sheets.length > 1">
                <div class="flex items-center gap-2">
                    <label for="sheetSelector" class="text-sm font-medium text-gray-600 dark:text-gray-300 whitespace-nowrap">
                        {{ __('assets.select_sheet') ?? 'Select Excel Sheet' }}
                    </label>
                    <select id="sheetSelector"
                            x-model="selectedSheet"
                            class="select select-bordered select-sm bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-100 border-gray-300 dark:border-gray-600 focus:ring-accent focus:border-accent rounded-lg text-sm min-w-[160px]">
                        <template x-for="(sheet, idx) in sheets" :key="idx">
                            <option :value="idx" x-text="sheet" :selected="idx == selectedSheet"></option>
                        </template>
                    </select>
                    <button type="button"
                            @click="loadSheet()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-accent/10 hover:bg-accent/20 text-accent border border-accent/30 rounded-lg text-sm font-medium transition-all duration-200"
                            title="{{ __('assets.load_sheet') ?? 'Load Sheet' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span class="hidden sm:inline">{{ __('assets.load_sheet') ?? 'Load' }}</span>
                    </button>
                </div>
            </template>
        </div>
    </x-slot>

    <!-- Safe Hydration Block -->
    <script>
        window.importPayload = {
            trueHeader: @json($true_header ?? []),
            previewDataRaw: @json($preview_data ?? []),
            proposals: @json($mapping_proposals ?? []),
            tempFilePath: @json($temp_file_path ?? ''),
            sheets: @json($sheets ?? []),
            isExecutive: @json(auth()->check() ? auth()->user()->hasExecutiveOversight() : false),
            userDepartmentName: @json(auth()->check() && auth()->user()->department ? auth()->user()->department->name : '')
        };
    </script>

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
        progress.progress-primary:indeterminate {
            background-color: #e5e7eb !important;
            background-image: linear-gradient(
                90deg,
                var(--property-accent, #4f46e5) 0%,
                #111827 50%,
                var(--property-accent, #4f46e5) 100%
            ) !important;
        }
        .dark progress.progress-primary:indeterminate {
            background-color: #374151 !important;
            background-image: linear-gradient(
                90deg,
                var(--property-accent, #4f46e5) 0%,
                #ffffff 50%,
                var(--property-accent, #4f46e5) 100%
            ) !important;
        }
        .import-drag-item {
            border-left-color: var(--property-accent, #4f46e5) !important;
        }
        .import-drag-item:hover {
            background-color: var(--property-accent-transparent, rgba(79, 70, 229, 0.15)) !important;
        }
    </style>

    <div class="py-6" 
         x-data="columnMapping()" 
         x-cloak>
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-2 space-y-6">

            @include('assets.import.partials.stepper', ['currentStep' => 2])

            <!-- Database Dropzones -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <template x-for="field in dbFields" :key="field.id">
                    <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md rounded-xl border border-gray-200/50 dark:border-gray-700/50 shadow-sm p-4 flex flex-col relative">
                        
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 flex items-center gap-1">
                                <span x-text="field.label"></span>
                                <template x-if="field.required">
                                    <span class="text-red-500">*</span>
                                </template>
                            </h3>

                        </div>
                        
                        <div class="flex-1 min-h-[80px] p-3 bg-gray-50/50 dark:bg-gray-900/50 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg flex flex-col gap-2 transition-colors"
                             :class="{
                                'border-accent bg-accent/5': isDragOver(field.id)
                             }"
                             @dragover.prevent="dragOver(field.id)"
                             @dragleave.prevent="dragLeave(field.id)"
                             @drop.prevent="drop(field.id)">
                             
                             <div x-show="mapping[field.id].columns.length === 0"
                                  class="text-xs text-gray-400 text-center m-auto flex-1 flex items-center justify-center">{{ __('assets.drop_column_here') ?? 'Drop column here' }}</div>

                             <div class="flex flex-wrap gap-2">
                                 <template x-for="col in mapping[field.id].columns" :key="col">
                                    <div draggable="true" 
                                         @dragstart="dragStart(col)"
                                         class="pl-2 pr-2.5 py-1.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 border-l-4 import-drag-item rounded-r-lg rounded-l-sm shadow-sm hover:shadow-md text-xs font-medium cursor-grab active:cursor-grabbing flex items-center gap-1.5 transition-all duration-200 text-gray-800 dark:text-gray-200 select-none">
                                         <svg class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                             <path d="M7 2a2 2 0 11.001 3.999A2 2 0 017 2zm0 6a2 2 0 11.001 3.999A2 2 0 017 8zm0 6a2 2 0 11.001 3.999A2 2 0 017 14zm6-12a2 2 0 11.001 3.999A2 2 0 0113 2zm0 6a2 2 0 11.001 3.999A2 2 0 0113 8zm0 6a2 2 0 11.001 3.999A2 2 0 0113 14z"></path>
                                         </svg>
                                         <span x-text="col" class="truncate"></span>
                                    </div>
                                 </template>
                             </div>
                        </div>

                        <!-- Merge Separator Dropdown (only visible if > 1 card) -->
                        <template x-if="mapping[field.id].columns.length > 1">
                            <div class="mt-2 text-xs flex justify-between items-center">
                                <label class="text-gray-500 dark:text-gray-400">{{ __('assets.separator') ?? 'Separator:' }}</label>
                                <select x-model="mapping[field.id].separator" class="text-xs border-gray-300 dark:border-gray-700 rounded py-0.5 px-2 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-200 focus:ring-accent focus:border-accent">
                                    <option value=" ">{{ __('assets.space') ?? 'Space' }}</option>
                                    <option value=",">{{ __('assets.comma') ?? 'Comma' }}</option>
                                    <option value=";">{{ __('assets.semicolon') ?? 'Semicolon' }}</option>
                                    <option value="-">-</option>
                                </select>
                            </div>
                        </template>

                    </div>
                </template>

                <!-- Ignored Zone (Bottom of grid, spanning 2 columns) -->
                <div class="md:col-span-2 bg-white/90 dark:bg-gray-800/90 backdrop-blur-md rounded-xl border border-gray-200/50 dark:border-gray-700/50 shadow-sm p-5">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                        </svg>
                        {{ __('assets.ignored_columns') ?? 'Ignored / Not Imported' }}
                    </h3>
                    <div class="min-h-[60px] p-4 bg-gray-50/50 dark:bg-gray-900/50 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg flex flex-wrap gap-2 items-start transition-colors"
                         :class="{'border-accent bg-accent/5': isDragOver('ignored')}"
                         @dragover.prevent="dragOver('ignored')"
                         @dragleave.prevent="dragLeave('ignored')"
                         @drop.prevent="drop('ignored')">
                        
                        <div x-show="mapping.ignored.columns.length === 0"
                             class="text-xs text-gray-400 w-full text-center py-2">{{ __('assets.drop_here_to_ignore') ?? 'Drop columns here to ignore' }}</div>

                        <template x-for="col in mapping.ignored.columns" :key="col">
                            <div draggable="true" 
                                 @dragstart="dragStart(col)"
                                 class="pl-3 pr-3.5 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 border-l-4 import-drag-item rounded-r-lg rounded-l-sm shadow-sm hover:shadow-md text-sm font-medium cursor-grab active:cursor-grabbing flex items-center gap-2.5 transition-all duration-200 text-gray-800 dark:text-gray-100 select-none">
                                <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M7 2a2 2 0 11.001 3.999A2 2 0 017 2zm0 6a2 2 0 11.001 3.999A2 2 0 017 8zm0 6a2 2 0 11.001 3.999A2 2 0 017 14zm6-12a2 2 0 11.001 3.999A2 2 0 0113 2zm0 6a2 2 0 11.001 3.999A2 2 0 0113 8zm0 6a2 2 0 11.001 3.999A2 2 0 0113 14z"></path>
                                </svg>
                                <span x-text="col" class="truncate"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Live Preview -->
            <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md rounded-xl border border-gray-200/50 dark:border-gray-700/50 shadow-sm overflow-hidden mt-6">
                <div class="px-5 py-4 border-b border-gray-200/50 dark:border-gray-700/50 flex items-center justify-between bg-gray-50/50 dark:bg-gray-800/50">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ __('assets.live_preview') ?? 'Live Preview' }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200/50 dark:divide-gray-700/50 text-sm">
                        <thead class="bg-gray-50/80 dark:bg-gray-900/80">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">#</th>
                                <template x-for="field in dbFields" :key="field.id">
                                    <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap" x-text="field.label"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200/50 dark:divide-gray-700/50 bg-white dark:bg-gray-800">
                            <template x-for="(row, index) in previewDataRaw" :key="index">
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                    <td class="px-4 py-2 text-gray-400 dark:text-gray-500" x-text="index + 1"></td>
                                    <template x-for="field in dbFields" :key="field.id">
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-200 whitespace-nowrap overflow-hidden text-ellipsis max-w-[250px]">
                                            <template x-if="field.id === 'department' && !isExecutive">
                                                <div class="inline-flex items-center gap-1.5 text-amber-600 dark:text-amber-400 font-semibold bg-amber-50 dark:bg-amber-950/30 px-2.5 py-1 rounded-lg border border-amber-200/50 dark:border-amber-900/50 text-xs shadow-sm cursor-help"
                                                     title="{{ __('assets.auto_assigned_department_warning') ?? 'Locked to your department by policy' }}">
                                                    <svg class="w-3.5 h-3.5 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                    </svg>
                                                    <span x-text="getCombinedValue(row, field.id)"></span>
                                                    <span class="text-[9px] uppercase font-extrabold tracking-wider opacity-90 border border-amber-300 dark:border-amber-800 bg-amber-100 dark:bg-amber-900 px-1 py-0.2 rounded">{{ __('assets.fixed') ?? 'Fixed' }}</span>
                                                </div>
                                            </template>
                                            <template x-if="!(field.id === 'department' && !isExecutive)">
                                                <span x-text="getCombinedValue(row, field.id)" :title="getCombinedValue(row, field.id)"></span>
                                            </template>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Action Button -->
            <div class="flex justify-end pt-4">
                <meta name="csrf-token" content="{{ csrf_token() }}">
                <button type="button" @click="submit"
                        class="inline-flex items-center px-6 py-3 bg-accent border border-transparent rounded-xl
                            font-semibold text-sm text-white uppercase tracking-widest hover:opacity-90 shadow-lg shadow-accent/30
                            focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 transition-all">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {{ __('assets.apply_mapping_continue') ?? 'Terapkan Pemetaan & Lanjutkan' }}
                </button>
            </div>

        </div>
    </div>

    <!-- Validation Error Modal -->
    <dialog id="validation_error_modal" class="modal modal-bottom sm:modal-middle">
        <div class="modal-box bg-white/95 dark:bg-gray-800/95 backdrop-blur-md border border-gray-200/50 dark:border-gray-700/50 shadow-2xl rounded-2xl p-6 text-gray-900 dark:text-gray-100">
            <div class="flex items-center gap-4 mb-4">
                <div class="p-3 bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400 rounded-full">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <h3 class="font-bold text-lg text-gray-900 dark:text-white" id="validation_error_title">
                    {{ __('assets.validation_error_title') ?? 'Validation Error' }}
                </h3>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-300" id="validation_error_message">
                {{ __('assets.mapping_required_alert') }}
            </p>
            <div class="modal-action">
                <form method="dialog">
                    <button class="btn btn-sm btn-ghost border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                        {{ __('assets.close') ?? 'Close' }}
                    </button>
                </form>
            </div>
        </div>
    </dialog>

    <!-- File Expired Modal -->
    <dialog id="file_expired_modal" class="modal modal-bottom sm:modal-middle backdrop-blur-sm">
        <div class="modal-box bg-white/95 dark:bg-gray-800/95 backdrop-blur-md border border-gray-200/50 dark:border-gray-700/50 shadow-2xl rounded-2xl p-6 text-gray-900 dark:text-gray-100">
            <div class="flex items-center gap-4 mb-4">
                <div class="p-3 bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-full">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <h3 class="font-bold text-lg text-gray-900 dark:text-white">
                    {{ __('assets.file_expired_title') }}
                </h3>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                {{ __('assets.file_expired_message') }}
            </p>
            <div class="modal-action">
                <a href="{{ route('assets.index', ['open_modal' => 'true']) }}" class="btn btn-sm bg-accent border-transparent text-white hover:opacity-90 rounded-lg">
                    {{ __('assets.file_expired_action') }}
                </a>
            </div>
        </div>
    </dialog>

    <!-- Glassmorphic Progress Tracker Modal -->
    <dialog id="progress_modal" class="modal backdrop-blur-sm">
        <div class="modal-box bg-white/95 dark:bg-gray-800/95 backdrop-blur-md border border-gray-200/50 dark:border-gray-700/50 shadow-2xl rounded-2xl p-6 text-gray-900 dark:text-gray-100" x-data="importProgress()" x-ref="progressRoot">

            <!-- Item 1: Top (Loader and Title) -->
            <div class="flex flex-col items-center text-center mb-6">
                <div class="w-16 h-16 rounded-2xl mb-4 flex items-center justify-center transition-all duration-500 shadow-lg"
                     :class="{
                        'bg-gradient-to-br from-accent/20 to-accent/5 text-accent ring-4 ring-accent/10': status === 'processing' || status === 'pending',
                        'bg-gradient-to-br from-emerald-100 to-emerald-50 dark:from-emerald-900/40 dark:to-emerald-900/20 text-emerald-600 dark:text-emerald-400 ring-4 ring-emerald-100 dark:ring-emerald-900/30': status === 'completed',
                        'bg-gradient-to-br from-red-100 to-red-50 dark:from-red-900/40 dark:to-red-900/20 text-red-600 dark:text-red-400 ring-4 ring-red-100 dark:ring-red-900/30': status === 'failed' || status === 'cancelled'
                     }">
                    <!-- Processing spinner -->
                    <template x-if="status === 'processing' || status === 'pending'">
                        <svg class="w-8 h-8 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25 stroke-gray-200 dark:stroke-gray-700" cx="12" cy="12" r="10" stroke-width="4"></circle>
                            <path class="opacity-75 fill-current text-accent" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </template>
                    <!-- Completed check -->
                    <template x-if="status === 'completed'">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                    </template>
                    <!-- Failed / Cancelled X -->
                    <template x-if="status === 'failed' || status === 'cancelled'">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </template>
                </div>

                <h3 class="font-bold text-lg tracking-tight" x-text="title"></h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1" x-text="subtitle"></p>
            </div>

            <!-- Item 2: Middle - Text -->
            <div class="text-sm text-gray-600 dark:text-gray-300 mb-2" x-text="percentage + '% - Memproses ' + processed + ' dari ' + total + ' baris'"></div>

            <!-- Item 3: Middle - Bar -->
            <progress class="progress progress-primary w-full" :value="percentage" max="100"></progress>

            <!-- Error Message -->
            <template x-if="(status === 'failed' || status === 'cancelled') && errorMsg">
                <div class="mt-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-sm text-red-700 dark:text-red-300 flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span x-text="errorMsg"></span>
                </div>
            </template>

            <!-- Item 4: Bottom - Button -->
            <template x-if="status === 'processing' || status === 'pending'">
                <button type="button" 
                        @click="cancelImport()" 
                        class="w-full mt-6 bg-red-600 hover:bg-red-700 active:bg-red-800 text-white font-semibold py-2.5 px-4 rounded-xl border border-red-600 hover:border-red-700 shadow-md transition-all duration-200 cursor-pointer text-center outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                    {{ __('assets.cancel_abort') }}
                </button>
            </template>

            <!-- Close on failure -->
            <template x-if="status === 'failed'">
                <div class="modal-action mt-4">
                    <button @click="closeModal()" class="btn btn-sm btn-ghost border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl w-full">
                        {{ __('assets.close') }}
                    </button>
                </div>
            </template>
        </div>
    </dialog>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('columnMapping', () => ({
                trueHeader: window.importPayload.trueHeader || [],
                previewDataRaw: window.importPayload.previewDataRaw || [],
                proposals: window.importPayload.proposals || {},
                tempFilePath: window.importPayload.tempFilePath || '',
                sheets: window.importPayload.sheets || [],
                selectedSheet: {{ request()->query('sheet', 0) }},
                isExecutive: !!window.importPayload.isExecutive,
                userDepartmentName: window.importPayload.userDepartmentName || '',
                
                draggedCol: null,
                hoveredZone: null,

                dbFields: [
                    { id: 'tag', label: '{{ __('assets.tag') ?? "Tag" }}', required: true },
                    { id: 'name', label: '{{ __('assets.name') ?? "Name" }}', required: true },
                    { id: 'category', label: '{{ __('assets.category') ?? "Category" }}', required: true },
                    { id: 'department', label: '{{ __('assets.department') ?? "Department" }}', required: true },
                    { id: 'status', label: '{{ __('assets.status') ?? "Status" }}', required: true },
                    { id: 'model', label: '{{ __('assets.model') ?? "Model" }}', required: false },
                    { id: 'serial_number', label: '{{ __('assets.serial_number') ?? "Serial Number" }}', required: false },
                    { id: 'purchase_date', label: '{{ __('assets.purchase_date') ?? "Purchase Date" }}', required: false },
                    { id: 'purchase_cost', label: '{{ __('assets.purchase_cost') ?? "Purchase Cost" }}', required: false },
                    { id: 'remarks', label: '{{ __('assets.remarks') ?? "Remarks" }}', required: false },
                ],

                mapping: {
                    tag: { columns: [], separator: ' ' },
                    name: { columns: [], separator: ' ' },
                    category: { columns: [], separator: ' ' },
                    department: { columns: [], separator: ' ' },
                    status: { columns: [], separator: ' ' },
                    model: { columns: [], separator: ' ' },
                    serial_number: { columns: [], separator: ' ' },
                    purchase_date: { columns: [], separator: ' ' },
                    purchase_cost: { columns: [], separator: ' ' },
                    remarks: { columns: [], separator: ' ' },
                    ignored: { columns: [], separator: '' }
                },

                unmappedCols: [],
                dbCols: [],

                init() {
                    try {
                        // Strictly enforce that mapping.ignored.columns is a pure JavaScript Array
                        const initialIgnored = this.mapping.ignored?.columns || [];
                        const ignoredArray = Array.isArray(initialIgnored) ? initialIgnored : Object.values(initialIgnored || {});
                        this.mapping.ignored = { columns: ignoredArray, separator: '' };

                        let usedCols = new Set();
                        
                        // Populate based on proposals
                        for (let dbKey in this.proposals) {
                            if (this.mapping[dbKey]) {
                                let proposedCols = Array.isArray(this.proposals[dbKey]) 
                                    ? this.proposals[dbKey] 
                                    : [this.proposals[dbKey]];
                                
                                proposedCols.forEach(col => {
                                    if(col) {
                                        this.mapping[dbKey].columns.push(col);
                                        usedCols.add(col);
                                    }
                                });
                            }
                        }

                        // Enforce that trueHeader is a pure JavaScript Array to prevent iteration bugs
                        const headers = Array.isArray(this.trueHeader) ? this.trueHeader : Object.values(this.trueHeader || {});

                        // Populate ignored columns via splice+push to preserve
                        // Alpine's reactive proxy on the array.
                        this.mapping.ignored.columns.splice(0, this.mapping.ignored.columns.length);
                        for (let col of headers) {
                            if (!usedCols.has(col)) {
                                this.mapping.ignored.columns.push(col);
                            }
                        }

                        // Setup helper properties & log hydrated state
                        this.unmappedCols = this.mapping.ignored.columns;
                        this.dbCols = this.dbFields;
                        console.log('HYDRATED STATE:', JSON.parse(JSON.stringify(this.mapping.ignored.columns)));

                    } catch (e) {
                        console.error("Initialization failed:", e);
                    }
                },

                dragStart(col) {
                    this.draggedCol = col;
                },
                dragOver(zone) {
                    this.hoveredZone = zone;
                },
                dragLeave(zone) {
                    if (this.hoveredZone === zone) {
                        this.hoveredZone = null;
                    }
                },
                isDragOver(zone) {
                    return this.hoveredZone === zone;
                },
                drop(targetZoneId) {
                    this.hoveredZone = null;
                    if (!this.draggedCol) return;

                    const col = this.draggedCol;
                    this.draggedCol = null;

                    // Remove from whichever zone currently holds this column
                    let sourceKey = null;
                    for (let key in this.mapping) {
                        const idx = this.mapping[key].columns.indexOf(col);
                        if (idx !== -1) {
                            sourceKey = key;
                            this.mapping[key].columns.splice(idx, 1);
                            break;
                        }
                    }

                    // Push to target zone
                    this.mapping[targetZoneId].columns.push(col);

                    // Debug trace for Ignored zone
                    if (targetZoneId === 'ignored') {
                        console.log('DROPPED IN IGNORED:', JSON.parse(JSON.stringify(this.mapping.ignored.columns)));
                    }
                },

                getCombinedValue(row, fieldId) {
                    if (fieldId === 'department' && !this.isExecutive) {
                        return this.userDepartmentName || '-';
                    }

                    const mapInfo = this.mapping[fieldId];
                    if (!mapInfo || mapInfo.columns.length === 0) return '-';
                    
                    let vals = mapInfo.columns.map(c => {
                        let val = row[c];
                        return (val !== null && val !== undefined) ? String(val).trim() : '';
                    }).filter(v => v !== '');

                    if (vals.length === 0) return '-';
                    return vals.join(mapInfo.separator);
                },

                submit() {
                    const requiredFields = this.dbFields.filter(f => f.required).map(f => f.id);
                    const hasMappedRequired = requiredFields.some(id => this.mapping[id] && this.mapping[id].columns.length > 0);
                    if (!hasMappedRequired) {
                        document.getElementById('validation_error_modal').showModal();
                        return;
                    }

                    const alignedMapping = {};
                    for (let key in this.mapping) {
                        if (this.mapping[key] && this.mapping[key].columns && this.mapping[key].columns.length > 0) {
                            alignedMapping[key] = {
                                columns: this.mapping[key].columns,
                                separator: this.mapping[key].separator
                            };
                        }
                    }

                    const payload = {
                        mapping: alignedMapping,
                        temp_file_path: this.tempFilePath,
                        selected_sheet: this.selectedSheet
                    };

                    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

                    // ── Resilient fetch: read raw text first, then parse JSON ──
                    // This prevents "unexpected character" errors when the server
                    // returns HTML (413 Payload Too Large, 504 Gateway Timeout, etc.)
                    fetch('{{ route("assets.import.process-mapping") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ payload: JSON.stringify(payload) })
                    })
                    .then(async (res) => {
                        const text = await res.text();

                        if (!res.ok) {
                            // Server returned an error status (413, 500, 504, etc.)
                            let errorMsg = '{{ __("assets.unknown_error") }}';
                            try {
                                const errData = JSON.parse(text);
                                errorMsg = errData.message || errorMsg;
                            } catch (_) {
                                // Response was HTML (nginx/PHP error page), not JSON
                                if (res.status === 413) {
                                    errorMsg = '{{ __("assets.large_file_warning") }}';
                                } else if (res.status === 504) {
                                    errorMsg = 'Server timeout. The file may be too large.';
                                } else {
                                    errorMsg = 'Server error (' + res.status + '). Check server logs.';
                                }
                                console.error('Non-JSON error response:', text.substring(0, 500));
                            }
                            if (errorMsg.includes('missing') || errorMsg.includes('expired') || errorMsg.includes('tidak ditemukan') || errorMsg.includes('kedaluwarsa') || errorMsg.includes('Expired') || errorMsg.includes('Missing') || errorMsg.includes('hilang') || errorMsg.includes('Kedaluwarsa')) {
                                document.getElementById('file_expired_modal').showModal();
                            } else {
                                document.getElementById('validation_error_title').textContent = '{{ __("assets.validation_error_title") ?? "Validation Error" }}';
                                document.getElementById('validation_error_message').textContent = errorMsg;
                                document.getElementById('validation_error_modal').showModal();
                            }
                            return;
                        }

                        let data;
                        try {
                            data = JSON.parse(text);
                        } catch (e) {
                            console.error('JSON parse failed. Raw:', text.substring(0, 500));
                            document.getElementById('validation_error_title').textContent = '{{ __("assets.validation_error_title") ?? "Validation Error" }}';
                            document.getElementById('validation_error_message').textContent = '{{ __("assets.network_error") }}';
                            document.getElementById('validation_error_modal').showModal();
                            return;
                        }

                        if (data.success) {
                            window.__importStatusUrl = data.status_url;
                            const overlay = document.getElementById('progress_modal_overlay');
                            if (overlay) overlay.style.display = 'block';
                            document.getElementById('progress_modal').showModal();
                        } else {
                            const msg = data.message || '{{ __("assets.unknown_error") }}';
                            if (msg.includes('missing') || msg.includes('expired') || msg.includes('tidak ditemukan') || msg.includes('kedaluwarsa') || msg.includes('Expired') || msg.includes('Missing') || msg.includes('hilang') || msg.includes('Kedaluwarsa')) {
                                document.getElementById('file_expired_modal').showModal();
                            } else {
                                document.getElementById('validation_error_title').textContent = '{{ __("assets.validation_error_title") ?? "Validation Error" }}';
                                document.getElementById('validation_error_message').textContent = msg;
                                document.getElementById('validation_error_modal').showModal();
                            }
                        }
                    })
                    .catch((err) => {
                        console.error('Fetch error:', err);
                        document.getElementById('validation_error_title').textContent = '{{ __("assets.validation_error_title") ?? "Validation Error" }}';
                        document.getElementById('validation_error_message').textContent = '{{ __("assets.network_error") }}';
                        document.getElementById('validation_error_modal').showModal();
                    });
                }
            }));

            Alpine.data('importProgress', () => ({
                status: 'pending',
                percentage: 0,
                processed: 0,
                total: 0,
                errorMsg: '',
                pollTimer: null,
                pollingStarted: false,       // Guard against duplicate startPolling calls
                pollTimeoutTimer: null,      // Safety timeout
                title: '{{ __("assets.import_progress_title") }}',
                subtitle: '{{ __("assets.import_progress_subtitle") }}',
                rowText: '',
                redirectUrl: '{{ route("assets.import-rapid-add") }}',

                init() {
                    const dialog = document.getElementById('progress_modal');
                    const self = this;
                    const observer = new MutationObserver(() => {
                        // Guard: only start polling once, even if observer fires multiple times
                        if (dialog.open && !self.pollingStarted) {
                            self.pollingStarted = true;
                            self.startPolling();
                        }
                    });
                    observer.observe(dialog, { attributes: true });
                },

                startPolling() {
                    const url = window.__importStatusUrl || '{{ route("assets.import-status") }}';
                    const self = this;

                    async function poll() {
                        // If polling was already stopped (e.g. completed/failed), skip
                        if (!self.pollingStarted) return;

                        try {
                            const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                            const text = await response.text();

                            if (!response.ok) {
                                console.warn('Poll HTTP error:', response.status, text.substring(0, 200));
                                return;
                            }

                            let data;
                            try {
                                data = JSON.parse(text);
                            } catch (e) {
                                console.error('Poll JSON parse error. Raw:', text.substring(0, 300));
                                return;
                            }

                            self.status = data.status;
                            self.percentage = data.percentage || 0;
                            self.processed = data.processed || 0;
                            self.total = data.total || 0;
                            self.errorMsg = data.error || '';

                            self.rowText = self.formatRowText(self.processed, self.total);
                            self.updateTitles();

                            if (data.status === 'completed') {
                                self.stopPolling();
                                self.hideOverlay();
                                self.title = '{{ __("assets.import_completed_title") }}';
                                self.subtitle = '{{ __("assets.import_completed_subtitle") }}';
                                setTimeout(() => { window.location.href = self.redirectUrl; }, 800);
                            } else if (data.status === 'failed') {
                                self.stopPolling();
                                self.hideOverlay();
                                self.title = '{{ __("assets.import_failed_title") }}';
                                self.subtitle = '';
                            }
                        } catch (e) {
                            console.error('Poll network error:', e);
                        }
                    }

                    // ── Set the interval FIRST (synchronously), then fire immediate poll ──
                    // This guarantees pollTimer is assigned before the async poll() can
                    // resolve and call stopPolling(). Prevents the race condition where
                    // poll() completes before setInterval assigns the timer ID.
                    this.pollTimer = setInterval(poll, 800);

                    // Immediate first poll (catches sub-second completions for small files)
                    poll();

                    // ── Safety timeout: 5 minutes max polling ──
                    // If the job never completes (worker down, etc.), stop polling and
                    // show an error rather than spinning forever.
                    this.pollTimeoutTimer = setTimeout(() => {
                        if (self.pollingStarted && self.status !== 'completed' && self.status !== 'failed') {
                            self.stopPolling();
                            self.status = 'failed';
                            self.errorMsg = 'Import timed out. Please check if the queue worker is running.';
                            self.title = '{{ __("assets.import_failed_title") }}';
                            self.subtitle = '';
                        }
                    }, 5 * 60 * 1000);
                },

                stopPolling() {
                    if (this.pollTimer) {
                        clearInterval(this.pollTimer);
                        this.pollTimer = null;
                    }
                    if (this.pollTimeoutTimer) {
                        clearTimeout(this.pollTimeoutTimer);
                        this.pollTimeoutTimer = null;
                    }
                    this.pollingStarted = false;
                },

                formatRowText(processed, total) {
                    const fmt = n => n.toLocaleString();
                    if (total > 0) {
                        return '{{ __("assets.import_row_progress") }}'.replace(':processed', fmt(processed)).replace(':total', fmt(total));
                    }
                    return '{{ __("assets.import_row_counting") }}';
                },

                updateTitles() {
                    if (this.status === 'processing') {
                        this.title = '{{ __("assets.import_progress_title") }}';
                        this.subtitle = '{{ __("assets.import_progress_subtitle") }}';
                    }
                },

                hideOverlay() {
                    const overlay = document.getElementById('progress_modal_overlay');
                    if (overlay) overlay.style.display = 'none';
                },

                closeModal() {
                    this.stopPolling();
                    this.hideOverlay();
                    document.getElementById('progress_modal').close();
                },

                cancelImport() {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                    fetch('{{ route("assets.import.cancel") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        }
                    })
                    .then(() => {
                        this.stopPolling();
                        this.hideOverlay();
                        document.getElementById('progress_modal').close();
                        window.location.reload();
                    })
                    .catch((err) => {
                        console.error('Cancel failed:', err);
                        this.stopPolling();
                        this.hideOverlay();
                        document.getElementById('progress_modal').close();
                        window.location.reload();
                    });
                }
            }));
        });
    </script>
</x-app-layout>