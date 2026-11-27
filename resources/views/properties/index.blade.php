<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center text-gray-900 dark:text-gray-100">
            <h2 class="font-semibold text-xl leading-tight">
                {{ __('messages.properties') }}
            </h2>
            <div>
                @can('create', App\Models\Property::class)
                <a href="{{ route('properties.create') }}"
                class="inline-flex items-center px-4 py-2 bg-accent border border-transparent rounded-md 
                        font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 
                        focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 transition">
                    <x-heroicon-s-plus class="w-4 h-4 mr-2" />
                    {{ __('messages.new_property') }}
                </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-2">
            <!-- Table -->
            <div class="mt-6">
                <x-datagrid :paginator="$properties" :selectable="true" :can-delete="false">
                    <x-slot:head>
                        <x-datagrid.th :label="__('messages.name')" />
                        <x-datagrid.th :label="__('messages.code')" />
                        <x-datagrid.th :label="__('messages.address')" />
                        <x-datagrid.th :label="__('messages.users')" />
                        <x-datagrid.th :label="__('messages.assets')" />
                        <x-datagrid.th :label="__('messages.departments')" />
                    </x-slot:head>

                    @forelse($properties as $property)
                        <x-datagrid.row
                            :row-id="$property->id"
                            :row-number="$properties->firstItem() + $loop->index"
                            :selectable="true"
                        >
                            <td class="px-4 py-2 text-sm text-accent font-semibold hover:underline whitespace-nowrap">
                                <a href="{{ route('properties.show', $property) }}">{{ $property->name }}</a>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200 whitespace-nowrap">{{ $property->code }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200">{{ $property->address ?? '-' }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200 whitespace-nowrap">{{ $property->users_count }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200 whitespace-nowrap">{{ $property->assets_count }}</td>
                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-200 whitespace-nowrap">{{ $property->departments_count }}</td>
                        </x-datagrid.row>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                {{ __('messages.no_data_found') ?? 'No data found' }}
                            </td>
                        </tr>
                    @endforelse
                </x-datagrid>
            </div>
        </div>
    </div>
</x-app-layout>
