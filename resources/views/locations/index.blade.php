<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100 leading-tight">
                {{ __('messages.locations') ?? 'Lokasi' }}
            </h2>
            <div>
                @can('create', App\Models\Location::class)
                <a href="{{ route('locations.create') }}"
                class="inline-flex items-center px-4 py-2 bg-accent border border-transparent rounded-md 
                        font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 
                        focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 transition">
                    <x-heroicon-s-plus class="w-4 h-4 mr-2" />
                    {{ __('messages.new_location') ?? 'New Location' }}
                </a>
                @endcan
            </div>
        </div>
    </x-slot>


    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-2">

            <!-- Table -->
            <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md rounded-xl border border-gray-200/50 dark:border-gray-700/50 shadow-sm overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200/50 dark:divide-gray-700/50">
                    <thead class="bg-gray-50/50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">{{ __('messages.no') ?? 'No' }}</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">{{ __('messages.name') ?? 'Name' }}</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">{{ __('messages.code') ?? 'Code' }}</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">{{ __('messages.notes') ?? 'Notes' }}</th>
                            @if(Auth::user()->isSuperAdmin())
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">{{ __('messages.property') ?? 'Property' }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200/50 dark:divide-gray-700/50">
                        @foreach($locations as $location)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200">{{ $locations->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-2 text-sm font-semibold text-accent hover:underline">
                                    <a href="{{ route('locations.show',$location) }}">{{ $location->name }}</a>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200">{{ $location->code }}</td>
                                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200">{{ $location->notes??'_' }}</td>
                                @if(Auth::user()->isSuperAdmin())
                                    <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full text-white shadow-sm" style="background-color: {{ optional($location->property)->accent_color ?? '#6b7280' }}">
                                            {{ optional($location->property)->name ?? '-' }}
                                        </span>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-4">
                {{ $locations->links() }}
            </div>
        </div>
    </div>
</x-app-layout>