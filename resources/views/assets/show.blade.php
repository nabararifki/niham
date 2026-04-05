<x-app-layout>
<div class="py-4 sm:py-8">
        <div class="mx-auto max-w-6xl px-3 sm:px-6 lg:px-8">
            <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md rounded-xl border border-gray-200/50 dark:border-gray-700/50 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200/50 dark:border-gray-700/50">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        {{ __('messages.asset_details') ?? __('messages.asset_details') }}
                    </h2>
                </div>
                <div class="p-6 md:p-8 space-y-6">

                <div class="flex flex-col lg:flex-row gap-8">
                    <!-- Left Column: Image & QR -->
                    <div class="w-full lg:w-1/3 flex flex-col items-center space-y-6">
                        <!-- Image Preview -->
                        @if ($asset->attachments)
                            <div class="w-full flex justify-center">
                                <img src="{{ asset('storage/' . $asset->attachments->path) }}"
                                     alt="Asset Image"
                                     class="w-full max-w-md lg:max-w-full rounded-xl shadow-lg border border-gray-200/50 dark:border-gray-700/50 object-contain bg-white/90 dark:bg-gray-800/90 backdrop-blur-md" />
                            </div>
                        @else
                            <div class="w-full max-w-md lg:max-w-full aspect-square bg-gray-100/50 dark:bg-gray-700/50 rounded-xl shadow-sm border border-gray-200/60 dark:border-gray-600 flex items-center justify-center">
                                <span class="text-gray-400 dark:text-gray-500">{{ __('messages.no_image_available') }}</span>
                            </div>
                        @endif

                        <div class="w-full flex justify-center">
                            {{-- QR --}}
                            <x-qr-modal :asset="$asset" />
                        </div>
                    </div>

                    <!-- Right Column: Details -->
                    <div class="w-full lg:w-2/3 space-y-6">
                        <!-- Asset Info Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4">
                            <div class="space-y-3">
                                <div><strong class="text-gray-900 dark:text-gray-100">{{ __('messages.tag') }}</strong> <span class="text-gray-700 dark:text-gray-300">{{ $asset->tag }}</span></div>
                                <div><strong class="text-gray-900 dark:text-gray-100">{{ __('messages.name') }}</strong> <span class="text-gray-700 dark:text-gray-300">{{ $asset->name }}</span></div>
                                <div><strong class="text-gray-900 dark:text-gray-100">{{ __('messages.category') }}</strong> <span class="text-gray-700 dark:text-gray-300">{{ $asset->category->name ?? '-' }}</span></div>
                                <div><strong class="text-gray-900 dark:text-gray-100">{{ __('messages.department') }}</strong> <span class="text-gray-700 dark:text-gray-300">{{ $asset->department->name ?? '-' }}</span></div>
                                <div><strong class="text-gray-900 dark:text-gray-100">{{ __('messages.locations') ?? 'Location' }}</strong> <span class="text-gray-700 dark:text-gray-300">{{ $asset->location?->name ?? '-' }}</span></div>
                                <div class="flex items-center gap-2">
                                    <strong class="text-gray-900 dark:text-gray-100">{{ __('messages.status') }}</strong> 
                                    <span class="text-gray-700 dark:text-gray-300">{{ ucfirst(str_replace('_', ' ', $asset->status)) }}</span>
                                    @can('update', $assetClass)
                                    <x-modal-update-status :asset="$asset">
                                        <x-slot name="trigger">
                                            <button type="button" class="text-accent hover:text-indigo-800 dark:hover:text-indigo-400 transition" title="Update Status">
                                                <x-heroicon-s-pencil-square class="w-4 h-4"/>
                                            </button>
                                        </x-slot>
                                    </x-modal-update-status>
                                    @endcan
                                </div>
                                <div><strong class="text-gray-900 dark:text-gray-100">{{ __('messages.serial_number') }}</strong> <span class="text-gray-700 dark:text-gray-300">{{ $asset->serial_number ?: '-' }}</span></div>
                            </div>

                            <div class="space-y-3 pt-4 md:pt-0 border-t md:border-none border-gray-200/50 dark:border-gray-700">
                                <div><strong class="text-gray-900 dark:text-gray-100">{{ __('messages.purchase_date') }}</strong> <span class="text-gray-700 dark:text-gray-300">{{ $asset->purchase_date?->format('d M Y') ?? '-' }}</span></div>
                                <div>
                                    <strong class="text-gray-900 dark:text-gray-100 flex items-center mb-1 md:inline-block md:mb-0 md:mr-2">{{ __('messages.warranty_status') }}</strong>
                                    @if ($asset->warranty_date)
                                        @php
                                            $expired = \Carbon\Carbon::parse($asset->warranty_date)->isPast();
                                        @endphp
                                        @if ($expired)
                                            <span class="px-2 py-1 text-xs font-semibold text-red-700 bg-red-100 dark:bg-red-900/40 dark:text-red-300 rounded shadow-sm">
                                                {{ __('messages.expired') }} ({{ \Carbon\Carbon::parse($asset->warranty_date)->format('d M Y') }})
                                            </span>
                                        @else
                                            <span class="px-2 py-1 text-xs font-semibold text-green-700 bg-green-100 dark:bg-green-900/40 dark:text-green-300 rounded shadow-sm">
                                                {{ __('messages.active_until') }} {{ \Carbon\Carbon::parse($asset->warranty_date)->format('d M Y') }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="px-2 py-1 text-xs font-semibold text-gray-600 bg-gray-100/80 dark:bg-gray-700 dark:text-gray-300 rounded shadow-sm">
                                            {{ __('messages.no_warranty') }}
                                        </span>
                                    @endif
                                </div>
                                <div><strong class="text-gray-900 dark:text-gray-100">{{ __('messages.purchase_cost') }}</strong> <span class="text-gray-700 dark:text-gray-300">{{ $asset->purchase_cost ? 'Rp ' . number_format($asset->purchase_cost, 0, ',', '.') : '-' }}</span></div>
                                <div><strong class="text-gray-900 dark:text-gray-100">{{ __('messages.vendor') }}</strong> <span class="text-gray-700 dark:text-gray-300">{{ $asset->vendor ?: '-' }}</span></div>
                                <div><strong class="text-gray-900 dark:text-gray-100">{{ __('messages.last_editor') }}</strong> <span class="text-gray-700 dark:text-gray-300">{{ $asset->editorUser?->name ?? __('messages.n_a') }}</span></div>
                            </div>
                        </div>

                        <!-- Recent History Timeline -->
                        <div class="pt-4 border-t border-gray-200/50 dark:border-gray-700">
                            <strong class="text-gray-900 dark:text-gray-100 block mb-4">{{ __('messages.recent_history') }}</strong>
                            
                            @if($recentHistories->isEmpty())
                                <div class="bg-gray-50/80 dark:bg-gray-700/50 p-6 rounded-xl border border-gray-200/40 dark:border-gray-600 flex flex-col items-center justify-center text-center shadow-sm">
                                    <svg class="w-8 h-8 text-gray-400 dark:text-gray-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">{{ __('messages.no_recent_history_available') }}</span>
                                </div>
                            @else
                                <div class="space-y-4 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-gray-200 dark:before:via-gray-600 before:to-transparent">
                                    @foreach($recentHistories as $history)
                                        <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
                                            <!-- Icon -->
                                            <div class="flex items-center justify-center w-10 h-10 rounded-full border border-gray-200/50 dark:border-gray-700/50 bg-white/90 dark:bg-gray-800/90 backdrop-blur-md text-gray-500 dark:text-gray-400 shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 z-10 transition-colors duration-200 group-hover:text-accent group-hover:border-accent">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </div>
                                            <!-- Card -->
                                            <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] bg-white/90 dark:bg-gray-800/90 backdrop-blur-md p-4 rounded-xl border border-gray-200/50 dark:border-gray-700/50 shadow-sm transition-all duration-200 hover:shadow-md">
                                                <div class="flex items-center justify-between mb-1">
                                                    <span class="font-semibold text-gray-900 dark:text-gray-100 text-sm">{{ $history->user->name ?? __('messages.system') }}</span>
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $history->created_at->diffForHumans() }}</span>
                                                </div>
                                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ ucfirst(str_replace('_', ' ', $history->action)) }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mt-6 text-center">
                                    <a href="{{ route('assets.history', $asset) }}" class="inline-flex items-center justify-center w-full md:w-auto px-6 py-2.5 text-sm font-medium text-accent bg-accent/10 hover:bg-accent/20 dark:bg-accent/20 dark:hover:bg-accent/30 rounded-lg transition-colors duration-200">
                                        {{ __('messages.view_full_history') }}
                                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </a>
                                </div>
                            @endif
                        </div>

                        <!-- Remarks Block -->
                        <div class="pt-4 border-t border-gray-200/50 dark:border-gray-700">
                            <strong class="text-gray-900 dark:text-gray-100 block mb-2">{{ __('messages.remarks') }}</strong>
                            <div class="bg-gray-50/80 dark:bg-gray-700/50 p-4 rounded-lg border border-gray-200/40 dark:border-gray-600 text-gray-700 dark:text-gray-300 whitespace-pre-line shadow-sm" style="overflow-wrap: anywhere;">
                                {{ $asset->remarks ?: __('messages.no_remarks_provided') }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between w-full mt-6">
                    <!-- Left Slot -->
                    <div>
                        <!-- Back Button -->
                        <a href="{{ route('assets.index') }}"
                        class="w-full sm:w-auto justify-center inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md 
                                font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 
                                focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition">
                            <x-heroicon-s-arrow-left class="w-4 h-4 mr-2" />
                            {{ __('messages.back') }}
                        </a>
                    </div>

                    <!-- Right Slot -->
                    <div class="flex space-x-3">
                        @can('update', $assetClass)
                            <!-- Edit Button -->
                            <a href="{{ route('assets.edit', $asset) }}"
                            class="w-full sm:w-auto justify-center inline-flex items-center px-4 py-2 bg-accent border border-transparent rounded-md 
                                    font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 
                                    focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 transition">
                                <x-heroicon-s-pencil class="w-4 h-4 mr-2" />
                                {{ __('messages.edit') }}
                            </a>
                        @endcan
                        @can('delete', $assetClass)
                            <!-- Delete Button & Modal -->
                            <div x-data="{ openDeleteModal: false }" class="inline-flex w-full sm:w-auto">
                                <button type="button" @click="openDeleteModal = true"
                                        class="w-full sm:w-auto justify-center inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md 
                                            font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 
                                            focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition">
                                    <x-heroicon-s-trash class="w-4 h-4 mr-2" />
                                    {{ __('messages.delete') }}
                                </button>

                                <template x-teleport="body">
                                    <div x-show="openDeleteModal"
                                        x-cloak
                                        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 dark:bg-gray-900/60 backdrop-blur-sm p-4">
                                        <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md border border-gray-200/50 dark:border-gray-700/50 rounded-xl shadow-xl w-full max-w-md p-6 relative" @click.outside="openDeleteModal = false">
                                            <button @click="openDeleteModal = false" class="absolute top-4 right-4 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
                                                <x-heroicon-s-x-mark class="w-5 h-5"/>
                                            </button>
                                            
                                            <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">{{ __('messages.delete_asset') }}</h2>
                                            <p class="text-sm text-gray-600 dark:text-gray-300 mb-6">{{ __('messages.are_you_sure_you_want_to_delete_this_asset_this_ac') }}</p>
                                            
                                            <form action="{{ route('assets.destroy', $asset) }}" method="POST">
                                                @csrf
                                                @method('DELETE')
                                                <div class="flex justify-end gap-3">
                                                    <x-secondary-button type="button" @click="openDeleteModal = false">{{ __('messages.cancel') }}</x-secondary-button>
                                                    <x-danger-button type="submit">{{ __('messages.yes_delete') }}</x-danger-button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        @endcan
                    </div>
                </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
