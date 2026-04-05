<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center text-gray-900 dark:text-gray-100">
            <h2 class="font-semibold text-xl leading-tight">
                {{ __('messages.assets') ?? 'Assets' }}
            </h2>
            <div>
                @can('create', App\Models\Asset::class)
                <button type="button" @click="$dispatch('open-add-asset-modal')"
                class="inline-flex items-center px-4 py-2 bg-accent border border-transparent rounded-md 
                        font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 
                        focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 transition">
                    <x-heroicon-s-plus class="w-4 h-4 mr-2" />
                    {{ __('messages.new_asset') }}
                </button>
                @endcan
            </div>
        </div>
    </x-slot>


    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-2">
            <!-- Filter Toggle & Panel -->
            @php
                $activeFilters = collect(['category', 'department', 'status', 'sort', 'search'])
                    ->filter(fn($f) => request($f))->count();
            @endphp
            <div x-data="{ filtersOpen: {{ $activeFilters > 0 ? 'true' : 'false' }} }" class="mb-4">
                <!-- Toggle Button -->
                <div class="flex items-center gap-2">
                    <button
                        @click="filtersOpen = !filtersOpen"
                        type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-white/90 dark:bg-gray-800/90 backdrop-blur-md border border-gray-200/50 dark:border-gray-700/50 shadow-sm text-sm font-medium text-gray-700 dark:text-gray-100 transition rounded-xl"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        <span>{{ __('messages.filters') }}</span>
                        @if($activeFilters > 0)
                            <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-accent rounded-full">{{ $activeFilters }}</span>
                        @endif
                        <svg class="w-4 h-4 transition-transform duration-200" :class="filtersOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    @if($activeFilters > 0)
                        <a href="{{ route('assets.index') }}" class="text-sm text-gray-500 hover:text-gray-700 underline">{{ __('messages.clear_all') }}</a>
                    @endif
                </div>

                <!-- Collapsible Filter Panel -->
                <div
                    x-show="filtersOpen"
                    x-collapse
                    x-cloak
                >
                    <form id="filter-form" method="GET" action="{{ route('assets.index') }}" class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md rounded-xl border border-gray-200/50 dark:border-gray-700/50 shadow-sm p-4 sm:p-5 mt-3">
                        <input type="hidden" name="format" id="export-format" value="">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                            <!-- Category -->
                            <div>
                                <label for="category" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">{{ __('messages.category') }}</label>
                                <select name="category" id="category" class="block w-full border-gray-300 dark:border-gray-700 rounded-lg shadow-sm text-sm focus:ring-accent focus:border-accent dark:bg-gray-900/50 dark:text-gray-100">
                                    <option value="">{{ __('messages.all') }}</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Department -->
                            @if (Auth::user()->hasExecutiveOversight() || Auth::user()->isRole('admin') || Auth::user()->isSuperAdmin())
                                <div>
                                    <label for="department" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">{{ __('messages.department') }}</label>
                                    <select name="department" id="department" class="block w-full border-gray-300 dark:border-gray-700 rounded-lg shadow-sm text-sm focus:ring-accent focus:border-accent dark:bg-gray-900/50 dark:text-gray-100">
                                        <option value="">{{ __('messages.all') }}</option>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}" {{ request('department') == $department->id ? 'selected' : '' }}>
                                                {{ $department->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <!-- Status -->
                            <div>
                                <label for="status" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">{{ __('messages.status') }}</label>
                                <select name="status" id="status" class="block w-full border-gray-300 dark:border-gray-700 rounded-lg shadow-sm text-sm focus:ring-accent focus:border-accent dark:bg-gray-900/50 dark:text-gray-100">
                                    <option value="">{{ __('messages.all') }}</option>
                                    <option value="in_service" {{ request('status') == 'in_service' ? 'selected' : '' }}>{{ __('messages.in_service') }}</option>
                                    <option value="out_of_service" {{ request('status') == 'out_of_service' ? 'selected' : '' }}>{{ __('messages.out_of_service') }}</option>
                                    <option value="disposed" {{ request('status') == 'disposed' ? 'selected' : '' }}>{{ __('messages.disposed') }}</option>
                                </select>
                            </div>

                            <!-- Sort -->
                            <div>
                                <label for="sort" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">{{ __('messages.sort_by') }}</label>
                                <select name="sort" id="sort" class="block w-full border-gray-300 dark:border-gray-700 rounded-lg shadow-sm text-sm focus:ring-accent focus:border-accent dark:bg-gray-900/50 dark:text-gray-100">
                                    <option value="name" {{ request('sort') == 'name' ? 'selected' : '' }}>{{ __('messages.name') }}</option>
                                    <option value="tag" {{ request('sort') == 'tag' ? 'selected' : '' }}>{{ __('messages.tag') }}</option>
                                    <option value="status" {{ request('sort') == 'status' ? 'selected' : '' }}>{{ __('messages.status') }}</option>
                                </select>
                            </div>

                            <!-- Search -->
                            <div>
                                <label for="search" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">{{ __('messages.search') }}</label>
                                <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="{{ __('messages.search_placeholder') }}"
                                    class="block w-full border-gray-300 dark:border-gray-700 rounded-lg shadow-sm text-sm focus:ring-accent focus:border-accent dark:bg-gray-900/50 dark:text-gray-100" />
                            </div>
                        </div>

                        <!-- Action buttons -->
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 mt-4 pt-3 border-t border-gray-200/50">
                            <button type="submit"
                                class="w-full sm:w-auto justify-center inline-flex items-center px-4 py-2 bg-accent border border-transparent rounded-lg
                                    font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90
                                    focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 transition">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                {{ __('messages.apply') }}
                            </button>

                                <x-modal-export route="{{ route('assets.export') }}" />
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md rounded-xl border border-gray-200/50 dark:border-gray-700/50 shadow-sm overflow-x-auto mt-6">
                <table class="min-w-full divide-y divide-gray-200/50 dark:divide-gray-700/50">
                    <thead class="bg-gray-50/50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">{{ __('messages.no') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">{{ __('messages.tag') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">{{ __('messages.name') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">{{ __('messages.category') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">{{ __('messages.department') }}</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">{{ __('messages.locations') ?? 'Location' }}</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">{{ __('messages.status') }}</th>
                            @if(Auth::user()->isSuperAdmin())
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">{{ __('messages.property') }}</th>
                            @endif
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">{{ __('messages.qr') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200/50 dark:divide-gray-700/50">
                        @forelse($assets as $a)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200">{{ $assets->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200">{{ $a->tag }}</td>
                                <td class="px-4 py-2 text-sm text-accent font-semibold relative">
                                    <x-hover-card :asset="$a">
                                        <a href="{{ route('assets.show',$a) }}" class="transition-colors hover:underline" :class="hovering ? 'text-red-500 font-bold' : ''">{{ $a->name }}</a>
                                    </x-hover-card>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200">{{ $a->category->name }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200">{{ optional($a->department)->name ?? '-' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200">{{ optional($a->location)->name ?? '-' }}</td>
                                <td class="px-4 py-2 text-sm">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full 
                                        @if ($a->status == 'in_service')
                                            bg-green-100 text-green-800
                                        @elseif ($a->status == 'out_of_service')
                                            bg-red-100 text-red-800
                                        @else
                                            bg-gray-100 text-gray-800 dark:text-gray-200
                                        @endif
                                    ">
                                        {{ ucfirst(str_replace('_', ' ', $a->status)) }}
                                    </span>
                                </td>
                                @if(Auth::user()->isSuperAdmin())
                                    <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full text-white shadow-sm" style="background-color: {{ optional($a->property)->accent_color ?? '#6b7280' }}">
                                            {{ optional($a->property)->name ?? '-' }}
                                        </span>
                                    </td>
                                @endif
                                {{-- QR --}}
                                <td class="px-4 py-2">
                                    <x-qr-modal :asset="$a"/>
                                </td>


                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    {{ __('messages.no_data_found') ?? 'No data found' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-4">
                {{ $assets->links() }}
            </div>

            @if (Auth::user()->isRole('admin') || Auth::user()->isSuperAdmin())

                {{-- ─── Backup & Restore Card ─────────────────────────────────── --}}
                <div class="mt-8 bg-white/90 dark:bg-gray-800 backdrop-blur-md rounded-xl border border-gray-200/50 dark:border-gray-700/50 shadow-sm overflow-hidden">

                    {{-- Card header --}}
                    <div class="px-6 py-4 border-b border-gray-200/50 dark:border-gray-700/50 flex items-center gap-3">
                        <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-accent/10">
                            <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('messages.backup_restore') }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('messages.backup_restore_desc') }}</p>
                        </div>
                    </div>

                    @if (Auth::user()->isSuperAdmin() && !session('active_property_id'))
                        <div class="p-6">
                            <div class="flex items-start gap-3 p-4 bg-gray-50/80 dark:bg-gray-800 border border-gray-200/60 dark:border-gray-600/50 rounded-lg">
                                <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                </svg>
                                <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                                    {{ __('messages.backup_select_property_warning') }}
                                </p>
                            </div>
                        </div>
                    @else
                        <div class="p-6 space-y-4">
                            @if ($errors->has('backup'))
                                <div class="flex items-start gap-3 p-4 bg-red-50/80 dark:bg-red-900/20 border border-red-300/60 dark:border-red-800/50 rounded-lg">
                                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-red-500 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <div>
                                        <p class="text-sm font-medium text-red-800 dark:text-red-200">{{ __('messages.restore_failed') }}</p>
                                        <p class="text-xs text-red-600 dark:text-red-300 mt-1">{{ $errors->first('backup') }}</p>
                                    </div>
                                </div>
                            @endif

                            @if (session('ok'))
                                <div class="flex items-start gap-3 p-4 bg-green-50/80 dark:bg-green-900/20 border border-green-300/60 dark:border-green-800/50 rounded-lg">
                                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-green-500 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ session('ok') }}</p>
                                </div>
                            @endif

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="p-5 bg-gray-50/80 dark:bg-gray-800 rounded-lg border border-gray-200/60 dark:border-gray-600/50">
                                    <div class="flex items-center gap-2.5 mb-2">
                                        <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('messages.download_backup') }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4 leading-relaxed">{{ __('messages.backup_download_desc') }}</p>
                                    <form action="{{ route('backup.download') }}" method="POST">
                                        @csrf
                                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-accent hover:opacity-90 text-white text-xs font-semibold uppercase tracking-widest rounded-lg transition-all duration-200 shadow-sm hover:shadow-md">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                            {{ __('messages.download_backup') }}
                                        </button>
                                    </form>
                                </div>

                                <div class="p-5 bg-gray-50/80 dark:bg-gray-800 rounded-lg border border-gray-200/60 dark:border-gray-600/50">
                                    <div class="flex items-center gap-2.5 mb-2">
                                        <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('messages.restore_data') }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-4 leading-relaxed">{{ __('messages.restore_data_desc') }}</p>
                                    <button type="button" x-data @click="$dispatch('open-restore-modal')"
                                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-600 hover:bg-gray-500 dark:bg-gray-600 dark:hover:bg-gray-500 text-white text-xs font-semibold uppercase tracking-widest rounded-lg transition-all duration-200 shadow-sm hover:shadow-md">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m4-8l-4-4m0 0L13 8m4-4v12" />
                                        </svg>
                                        {{ __('messages.restore_data') }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Restore Confirmation Modal --}}
                        <template x-teleport="body">
                            <div x-data="{ open: false }" x-on:open-restore-modal.window="open = true" x-show="open" x-cloak
                                class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 dark:bg-gray-900/60 backdrop-blur-sm p-4">
                                <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md border border-gray-200/50 dark:border-gray-700/50 rounded-xl shadow-2xl w-full max-w-lg overflow-hidden" @click.outside="open = false">
                                    <div class="px-6 pt-5 pb-4 border-b border-red-200/40 dark:border-red-900/30 bg-red-50/40 dark:bg-red-900/10">
                                        <div class="flex items-center gap-3">
                                            <div class="flex items-center justify-center w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/40">
                                                <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                                </svg>
                                            </div>
                                            <div>
                                                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('messages.restore_backup') }}</h2>
                                                <p class="text-xs text-red-600 dark:text-red-400 font-medium">{{ __('messages.destructive_action') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="px-6 py-5">
                                        <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">{!! __('messages.restoring_a_backup_will_strong_replace_all_current') !!}</p>
                                        <form action="{{ route('backup.restore') }}" method="POST" enctype="multipart/form-data" class="mt-5 space-y-5">
                                            @csrf
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('messages.select_backup_file') }}</label>
                                                <input type="file" name="backup" accept=".zip" required
                                                    class="block w-full text-sm text-gray-700 dark:text-gray-200 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:uppercase file:tracking-wider file:bg-gray-100 file:dark:bg-gray-700 file:text-gray-700 file:dark:text-gray-200 hover:file:bg-gray-200 dark:hover:file:bg-gray-600 file:cursor-pointer file:transition-colors border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900/50 focus:outline-none focus:ring-2 focus:ring-accent focus:border-accent" />
                                                <p class="mt-1.5 text-xs text-gray-400 dark:text-gray-500">{{ __('messages.accepted_format') }}</p>
                                            </div>
                                            <div class="flex justify-end gap-3 pt-3 border-t border-gray-200/50 dark:border-gray-700/50">
                                                <x-secondary-button type="button" @click="open = false">{{ __('messages.cancel') }}</x-secondary-button>
                                                <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold uppercase tracking-widest rounded-lg transition-all duration-200 shadow-sm hover:shadow-md">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                    </svg>
                                                    {{ __('messages.restore_now') }}
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </template>
                    @endif
                </div>
            @endif


        </div>
    </div>

    @include('assets.partials.add-asset-modal')
</x-app-layout>
