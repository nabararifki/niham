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

    <div class="py-8" x-data="importReview({ validCount: @json($validCount), invalidCount: @json($invalidCount), invalidPages: @json($invalidPages) })">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- ─── Progress Stepper (Top) ─────────────────────────────────────── --}}
            @include('assets.import.partials.stepper', ['currentStep' => 4])

            {{-- ─── Main Card ───────────────────────────────────────────────────── --}}
            <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md shadow-xl sm:rounded-2xl border border-gray-200/50 dark:border-gray-700/50 overflow-hidden">
                <form action="{{ route('assets.import-store') }}" method="POST" id="review-form" @submit.prevent="triggerPreflight()">
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

                        {{-- ─── Data Table ─────────────────────────────────────────── --}}
                        <div class="overflow-x-auto w-full rounded-xl border border-gray-200/50 dark:border-gray-700/50">
                            <table class="min-w-full divide-y divide-gray-200/50 dark:divide-gray-700/50 text-sm">
                                <thead class="bg-gray-50/80 dark:bg-gray-900/80">
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider w-8">#</th>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.tag') }} *</th>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.name') }} *</th>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.category') }} *</th>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.department') }} *</th>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.status') }} *</th>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.model_brand') }}</th>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.serial_number') }}</th>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.purchase_date') }}</th>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.purchase_cost') }}</th>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.remarks') }}</th>
                                        <th class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('assets.action') }}</th>
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
                                        <tr x-data="{ isInvalid: {{ ($item['is_invalid'] ?? false) ? 'true' : 'false' }} }"
                                            :class="isInvalid ? 'bg-red-50 dark:bg-red-900/20 border-l-4 border-error' : 'hover:bg-gray-50/50 dark:hover:bg-gray-700/30'"
                                            class="transition-colors">
                                            {{-- Row number --}}
                                            <td class="px-3 py-2.5 text-xs text-gray-400 dark:text-gray-500 font-mono">
                                                {{ number_format($globalIndex + 1) }}
                                            </td>

                                            {{-- Tag --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <input type="text"
                                                       name="assets[{{ $localIndex }}][tag]"
                                                       value="{{ old('assets.'.$localIndex.'.tag', $item['tag'] ?? ('AST-' . strtoupper(\Str::random(6)))) }}"
                                                       required
                                                       @input.debounce.500ms="autoSave({{ $globalIndex }}, 'tag', $event.target.value, $data)"
                                                       class="block w-32 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                            {{-- Name --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <input type="text"
                                                       name="assets[{{ $localIndex }}][name]"
                                                       value="{{ old('assets.'.$localIndex.'.name', $item['name'] ?? '') }}"
                                                       required
                                                       @input.debounce.500ms="autoSave({{ $globalIndex }}, 'name', $event.target.value, $data)"
                                                       class="block w-40 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                            {{-- Category — uses pre-fetched $categories collection, ZERO DB queries --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <select name="assets[{{ $localIndex }}][category_id]"
                                                        @change="autoSave({{ $globalIndex }}, 'category_id', $event.target.value, $data)"
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
                                                        @change="autoSave({{ $globalIndex }}, 'department_id', $event.target.value, $data)"
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
                                                        @change="autoSave({{ $globalIndex }}, 'status', $event.target.value, $data)"
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
                                                       @input.debounce.500ms="autoSave({{ $globalIndex }}, 'model', $event.target.value, $data)"
                                                       class="block w-32 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                            {{-- Serial Number --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <input type="text"
                                                       name="assets[{{ $localIndex }}][serial_number]"
                                                       value="{{ old('assets.'.$localIndex.'.serial_number', $item['serial_number'] ?? '') }}"
                                                       @input.debounce.500ms="autoSave({{ $globalIndex }}, 'serial_number', $event.target.value, $data)"
                                                       class="block w-32 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                            {{-- Purchase Date --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <input type="date"
                                                       name="assets[{{ $localIndex }}][purchase_date]"
                                                       value="{{ old('assets.'.$localIndex.'.purchase_date', $item['purchase_date'] ?? '') }}"
                                                       @change="autoSave({{ $globalIndex }}, 'purchase_date', $event.target.value, $data)"
                                                       class="block w-36 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                            {{-- Purchase Cost --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <input type="number"
                                                       step="any"
                                                       name="assets[{{ $localIndex }}][purchase_cost]"
                                                       value="{{ old('assets.'.$localIndex.'.purchase_cost', $item['purchase_cost'] ?? '') }}"
                                                       @input.debounce.500ms="autoSave({{ $globalIndex }}, 'purchase_cost', $event.target.value, $data)"
                                                       class="block w-28 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                            {{-- Remarks --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap">
                                                <input type="text"
                                                       name="assets[{{ $localIndex }}][remarks]"
                                                       value="{{ old('assets.'.$localIndex.'.remarks', $item['remarks'] ?? '') }}"
                                                       @input.debounce.500ms="autoSave({{ $globalIndex }}, 'remarks', $event.target.value, $data)"
                                                       class="block w-40 border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-accent focus:border-accent text-xs dark:bg-gray-900/50 dark:text-white" />
                                            </td>

                                            {{-- Action (remove row from DOM only) --}}
                                            <td class="px-2 py-2.5 whitespace-nowrap text-center">
                                                <button type="button"
                                                        @click="$el.closest('tr').remove(); rows--;"
                                                        class="text-red-500 hover:text-red-700 dark:hover:text-red-400 transition-colors p-1.5 bg-red-50 dark:bg-red-900/20 rounded-lg"
                                                        title="{{ __('assets.action') }}">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="12" class="px-3 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
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
                                    {{ $paginatedData->links('assets.import-pagination', ['invalidPages' => $invalidPages]) }}
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
                                    <span class="text-gray-900 dark:text-gray-100 font-semibold ml-1">{{ number_format($total) }}</span>
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

                get validText() {
                    return @json(__('assets.preflight_valid_rows', ['count' => '__COUNT__'])).replace('__COUNT__', Number(this.validCount).toLocaleString());
                },

                get invalidText() {
                    return @json(__('assets.preflight_invalid_warning', ['count' => '__COUNT__'])).replace('__COUNT__', Number(this.invalidCount).toLocaleString());
                },

                async autoSave(absoluteIndex, fieldName, newValue, trScope) {
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
                                absolute_index: absoluteIndex,
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

                submitForm() {
                    document.getElementById('preflight_modal').close();
                    document.getElementById('review-form').submit();
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
                            alert('An error occurred during saving: ' + err.message);
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
