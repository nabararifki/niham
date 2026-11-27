<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100 leading-tight">
                {{ __('messages.departments') }}
            </h2>
            <div>
                @can('create', App\Models\Department::class)
                <a href="{{ route('departments.create') }}"
                class="inline-flex items-center px-4 py-2 bg-accent border border-transparent rounded-md 
                        font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 
                        focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 transition">
                    <x-heroicon-s-plus class="w-4 h-4 mr-2" />
                    {{ __('messages.new_department') }}
                </a>
                @endcan
            </div>
        </div>
    </x-slot>


    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-2">

            @php
                $canDeleteDepartments = Auth::user()->can('delete', App\Models\Department::class);
            @endphp

            <!-- Table -->
            <x-datagrid
                :paginator="$departments"
                :selectable="true"
                :can-delete="$canDeleteDepartments"
                :bulk-delete-url="$canDeleteDepartments ? route('departments.bulk-delete') : null"
            >
                <x-slot:head>
                    <x-datagrid.th :label="__('messages.name')" />
                    <x-datagrid.th :label="__('messages.code')" />
                    <x-datagrid.th :label="__('messages.oversight')" align="center" />
                    <x-datagrid.th :label="__('messages.notes')" />
                    @if(Auth::user()->isSuperAdmin())
                        <x-datagrid.th :label="__('messages.property')" />
                    @endif
                </x-slot:head>

                @forelse($departments as $department)
                    <x-datagrid.row
                        :row-id="$department->id"
                        :row-number="$departments->firstItem() + $loop->index"
                        :selectable="true"
                    >
                        <td class="px-4 py-2 text-sm font-semibold text-accent hover:underline whitespace-nowrap">
                            <a href="{{ route('departments.show',$department) }}">{{ $department->name }}</a>
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200 whitespace-nowrap">{{ $department->code }}</td>
                        <td class="px-4 py-2 text-center text-sm whitespace-nowrap">
                            @if($department->is_executive_oversight)
                                <x-heroicon-s-check-circle class="w-5 h-5 text-green-500 mx-auto" />
                            @else
                                -
                            @endif
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200">{{ $department->notes??'_' }}</td>
                        @if(Auth::user()->isSuperAdmin())
                            <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full text-white shadow-sm" style="background-color: {{ optional($department->property)->accent_color ?? '#6b7280' }}">
                                    {{ optional($department->property)->name ?? '-' }}
                                </span>
                            </td>
                        @endif
                    </x-datagrid.row>
                @empty
                    <tr>
                        <td colspan="{{ Auth::user()->isSuperAdmin() ? 6 : 5 }}" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            {{ __('messages.no_data_found') ?? 'No data found' }}
                        </td>
                    </tr>
                @endforelse
            </x-datagrid>
        </div>
    </div>
</x-app-layout>
