<x-app-layout>
<div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md rounded-xl border border-gray-200/50 dark:border-gray-700/50 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200/50 dark:border-gray-700/50">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        {{ __('messages.location_details') ?? 'Location Details' }}
                    </h2>
                </div>
                <div class="p-6 md:p-8 space-y-8">

                {{-- Responsive Two-Column --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Location Details -->
                    <div class="space-y-3">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 border-b border-gray-200/50 pb-2">{{ __('messages.location_details') ?? 'Location Details' }}</h3>
                        <p class="text-lg text-gray-700 dark:text-gray-300"><strong class="text-gray-900 dark:text-gray-100">{{ __('messages.location_name') ?? 'Name' }}:</strong> {{ $location->name }}</p>
                        <p class="text-lg text-gray-700 dark:text-gray-300"><strong class="text-gray-900 dark:text-gray-100">{{ __('messages.code') ?? 'Code' }}:</strong> {{ $location->code }}</p>
                    </div>

                    <!-- Notes -->
                    <div class="space-y-3 pt-4 md:pt-0 border-t md:border-none border-gray-200/50">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 border-b border-gray-200/50 pb-2">{{ __('messages.notes') ?? 'Notes' }}</h3>
                        <div class="bg-gray-50/80 dark:bg-gray-700/50 p-4 rounded-lg border border-gray-200/40 dark:border-gray-600 text-gray-700 dark:text-gray-300 whitespace-pre-line shadow-sm" style="overflow-wrap: anywhere;">
                            {{ $location->notes ?: (__('messages.no_notes_provided') ?? 'No notes provided.') }}
                        </div>
                    </div>
                </div>

                {{-- Responsive Data Grids --}}
                <div class="grid grid-cols-1 gap-8 pt-4">
                    <!-- Assigned Assets -->
                    <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md rounded-xl p-5 border border-gray-200/50 dark:border-gray-700/50 shadow-sm">
                        <h4 class="text-md font-bold text-gray-900 dark:text-gray-100 mb-4 border-b border-gray-200/50 pb-2">{{ __('messages.assigned_assets') ?? 'Assigned Assets' }}</h4>
                        @if($location->assets->isNotEmpty())
                            <div class="overflow-x-auto rounded-lg border border-gray-200/60">
                                <table class="min-w-full divide-y divide-gray-200/50 dark:divide-gray-700/50 text-sm">
                                    <thead class="bg-gray-50/50 dark:bg-gray-800/50">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-200">{{ __('messages.tag') ?? 'Tag' }}</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-200">{{ __('messages.name') ?? 'Name' }}</th>
                                            <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-200">{{ __('messages.category') ?? 'Category' }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200/50 dark:divide-gray-700/50">
                                        @foreach($assets as $asset)
                                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                <td class="px-4 py-3 text-gray-900 dark:text-gray-200 font-medium">{{ $asset->tag }}</td>
                                                <td class="px-4 py-3 text-accent hover:underline"><a href="{{ route('assets.show', $asset) }}">{{ $asset->name }}</a></td>
                                                <td class="px-4 py-3 text-gray-900 dark:text-gray-200">{{ optional($asset->category)->name }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <!-- Pagination -->
                            <div class="mt-4">
                                {{ $assets->links() }}
                            </div>
                        @else
                            <p class="text-sm text-gray-500 italic">{{ __('messages.no_assets_assigned') ?? 'No assets assigned.' }}</p>
                        @endif
                    </div>
                </div>


                <div class="mt-6 flex justify-between items-center">
                    <!-- Back Button -->
                    <a href="{{ route('locations.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md 
                            font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 
                            focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition">
                        <x-heroicon-s-arrow-left class="w-4 h-4 mr-2" />
                        {{ __('messages.back_to_locations') ?? 'Back to Locations' }}
                    </a>

                    <div class="inline-flex">
                        @can('update', $location)
                        <!-- Edit Button -->
                        <a href="{{ route('locations.edit', $location) }}"
                        class="inline-flex items-center px-4 py-2 bg-accent border border-transparent rounded-md 
                                font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 
                                focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 transition">
                            <x-heroicon-s-pencil class="w-4 h-4 mr-2" />
                            {{ __('messages.edit') ?? 'Edit' }}
                        </a>
                        @endcan

                        @can('delete', $location)
                        <!-- Delete Button -->
                        <div x-data="{ openDeleteModal: false }" class="inline-flex">
                            <button type="button" @click="openDeleteModal = true"
                                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md 
                                        font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 
                                        focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ml-1">
                                <x-heroicon-s-trash class="w-4 h-4 mr-2" />
                                {{ __('messages.delete') ?? 'Delete' }}
                            </button>

                            <template x-teleport="body">
                                <div x-show="openDeleteModal"
                                    x-cloak
                                    class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 dark:bg-gray-900/60 backdrop-blur-sm">
                                    <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md rounded-xl border border-gray-200/50 dark:border-gray-700/50 shadow-xl w-full max-w-md p-6 relative" @click.outside="openDeleteModal = false">
                                        <button @click="openDeleteModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                                            <x-heroicon-s-x-mark class="w-5 h-5"/>
                                        </button>

                                        <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">{{ __('messages.delete_location') ?? 'Delete Location' }}</h2>
                                        <p class="text-sm text-gray-600 dark:text-gray-300 mb-6">{{ __('messages.delete_location_confirm') ?? 'Are you sure you want to delete this location?' }}</p>

                                        <form action="{{ route('locations.destroy', $location) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <div class="flex justify-end gap-3">
                                                <x-secondary-button type="button" @click="openDeleteModal = false">{{ __('messages.cancel') ?? 'Cancel' }}</x-secondary-button>
                                                <x-danger-button type="submit">{{ __('messages.yes_delete') ?? 'Yes, Delete' }}</x-danger-button>
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