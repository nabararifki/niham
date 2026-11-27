<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center text-gray-900 dark:text-gray-100">
            <h2 class="font-semibold text-xl leading-tight">
                {{ __('messages.categories') }}
            </h2>
            <div>
                @can('create', App\Models\Category::class)
                <a href="{{ route('categories.create') }}"
                class="inline-flex items-center px-4 py-2 bg-accent border border-transparent rounded-md 
                        font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 
                        focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 transition">
                    <x-heroicon-s-plus class="w-4 h-4 mr-2" />
                    {{ __('messages.new_category') }}
                </a>
                @endcan
            </div>
        </div>
    </x-slot>


    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-2">

            @php
                $canDeleteCategories = Auth::user()->can('delete', App\Models\Category::class);
            @endphp

            <!-- Table -->
            <x-datagrid
                :paginator="$categories"
                :selectable="true"
                :can-delete="$canDeleteCategories"
                :bulk-delete-url="$canDeleteCategories ? route('categories.bulk-delete') : null"
            >
                <x-slot:head>
                    <x-datagrid.th :label="__('messages.name')" />
                    <x-datagrid.th :label="__('messages.code')" />
                    <x-datagrid.th :label="__('messages.notes')" />
                    @if(Auth::user()->isSuperAdmin())
                        <x-datagrid.th :label="__('messages.property')" />
                    @endif
                </x-slot:head>

                @forelse($categories as $category)
                    <x-datagrid.row
                        :row-id="$category->id"
                        :row-number="$categories->firstItem() + $loop->index"
                        :selectable="true"
                    >
                        <td class="px-4 py-2 text-sm font-semibold text-accent hover:underline whitespace-nowrap">
                            <a href="{{ route('categories.show',$category) }}">{{ $category->name }}</a>
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200 whitespace-nowrap">{{ $category->code }}</td>
                        <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200">{{ $category->notes??'_' }}</td>
                        @if(Auth::user()->isSuperAdmin())
                            <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full text-white shadow-sm" style="background-color: {{ optional($category->property)->accent_color ?? '#6b7280' }}">
                                    {{ optional($category->property)->name ?? '-' }}
                                </span>
                            </td>
                        @endif
                    </x-datagrid.row>
                @empty
                    <tr>
                        <td colspan="{{ Auth::user()->isSuperAdmin() ? 5 : 4 }}" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            {{ __('messages.no_data_found') ?? 'No data found' }}
                        </td>
                    </tr>
                @endforelse
            </x-datagrid>
        </div>
    </div>
</x-app-layout>
