<x-app-layout>
<div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md rounded-xl border border-gray-200/50 dark:border-gray-700/50 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200/50 dark:border-gray-700/50">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        {{ __('messages.role_details') ?? __('messages.role_details') }}
                    </h2>
                </div>
                <div class="p-6 md:p-8 space-y-8">
                
                {{-- Responsive Two-Column --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Role Details -->
                    <div class="space-y-3">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 border-b border-gray-200/50 pb-2">{{ __('messages.role_details') }}</h3>
                        <p class="text-lg text-gray-700 dark:text-gray-300"><strong class="text-gray-900 dark:text-gray-100">{{ __('messages.role_name') }}:</strong> {{ ucwords($role->name) }}</p>
                    </div>

                    <!-- Permissions -->
                    <div class="space-y-3 pt-4 md:pt-0 border-t md:border-none border-gray-200/50">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 border-b border-gray-200/50 pb-2">{{ __('messages.role_permissions') }}</h3>
                        <div class="bg-gray-50/80 dark:bg-gray-700/50 rounded-lg border border-gray-200/40 dark:border-gray-600 shadow-sm overflow-x-auto">
                            <table class="min-w-full text-sm text-left">
                                <thead class="bg-gray-100/50 dark:bg-gray-800/50 text-gray-600 dark:text-gray-300 font-semibold border-b border-gray-200/50 dark:border-gray-600">
                                    <tr>
                                        <th class="py-2 px-3">{{ __('messages.module') ?? 'Module' }}</th>
                                        <th class="py-2 px-3 text-center">{{ __('messages.view') ?? 'View' }}</th>
                                        <th class="py-2 px-3 text-center">{{ __('messages.create') ?? 'Create' }}</th>
                                        <th class="py-2 px-3 text-center">{{ __('messages.update') ?? 'Update' }}</th>
                                        <th class="py-2 px-3 text-center">{{ __('messages.delete') ?? 'Delete' }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200/50 dark:divide-gray-600/50">
                                    @php
                                        $modules = [
                                            'assets' => $role->perm_assets,
                                            'users' => $role->perm_users,
                                            'categories' => $role->perm_categories,
                                            'departments' => $role->perm_departments,
                                            'locations' => $role->perm_locations,
                                            'roles' => $role->perm_roles,
                                        ];
                                        $check = '<svg class="w-5 h-5 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
                                        $cross = '<svg class="w-5 h-5 text-red-500 mx-auto opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>';
                                    @endphp
                                    @foreach($modules as $key => $permStr)
                                        @php
                                            $p = $permStr ?? 'no access';
                                            $canView = $p !== 'no access';
                                            $canCreate = $p === 'full access' || str_contains($p, 'create');
                                            $canUpdate = $p === 'full access' || str_contains($p, 'update');
                                            $canDelete = $p === 'full access' || str_contains($p, 'delete');
                                        @endphp
                                        <tr class="hover:bg-white dark:hover:bg-gray-800 transition">
                                            <td class="py-2 px-3 font-medium text-gray-900 dark:text-gray-100 capitalize">{{ __('messages.' . $key) }}</td>
                                            <td class="py-2 px-3 text-center">{!! $canView ? $check : $cross !!}</td>
                                            <td class="py-2 px-3 text-center">{!! $canCreate ? $check : $cross !!}</td>
                                            <td class="py-2 px-3 text-center">{!! $canUpdate ? $check : $cross !!}</td>
                                            <td class="py-2 px-3 text-center">{!! $canDelete ? $check : $cross !!}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Assigned Users -->
                <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md rounded-xl p-5 border border-gray-200/50 dark:border-gray-700/50 shadow-sm">
                    <h4 class="text-md font-bold text-gray-900 dark:text-gray-100 mb-4 border-b border-gray-200/50 pb-2">{{ __('messages.assigned_users') }}</h4>
                    @if($role->users->isNotEmpty())
                        <div class="overflow-x-auto rounded-lg border border-gray-200/60">
                            <table class="min-w-full divide-y divide-gray-200/50 dark:divide-gray-700/50 text-sm">
                                <thead class="bg-gray-50/50 dark:bg-gray-800/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-200">{{ __('messages.name') }}</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-200">{{ __('messages.department') }}</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-200">{{ __('messages.joined') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200/50 dark:divide-gray-700/50">
                                    @foreach($users as $user)
                                        <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="px-4 py-3 text-gray-900 dark:text-gray-200">{{ $user->name }}</td>
                                            <td class="px-4 py-3 text-gray-900 dark:text-gray-200">{{ $user->department->name }}</td>
                                            <td class="px-4 py-3 text-gray-700 dark:text-gray-400 whitespace-nowrap">{{ $user->created_at->format('d M Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination -->
                        <div class="mt-4">
                            {{ $users->links() }}
                        </div>
                    @else
                        <p class="text-sm text-gray-500 italic">{{ __('messages.no_users_assigned_to_role') }}</p>
                    @endif
                </div>

                <div class="mt-6 flex justify-between items-center">
                    <!-- Back Button -->
                    <a href="{{ route('roles.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md 
                            font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 
                            focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition">
                        <x-heroicon-s-arrow-left class="w-4 h-4 mr-2" />
                        {{ __('messages.back_to_roles') }}
                    </a>

                    <div class="inline-flex">
                        @can('update', $role)
                        <!-- Edit Button -->
                        <a href="{{ route('roles.edit', $role) }}"
                        class="inline-flex items-center px-4 py-2 bg-accent border border-transparent rounded-md 
                                font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 
                                focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 transition">
                            <x-heroicon-s-pencil class="w-4 h-4 mr-2" />
                            {{ __('messages.edit') }}
                        </a>
                        @endcan
                        @can('delete', $role)
                        <!-- Delete Button -->
                        <div x-data="{ openDeleteModal: false }" class="inline-flex">
                            <button type="button" @click="openDeleteModal = true"
                                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md 
                                        font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500 
                                        focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ml-1">
                                <x-heroicon-s-trash class="w-4 h-4 mr-2" />
                                {{ __('messages.delete') }}
                            </button>

                            <template x-teleport="body">
                                <div x-show="openDeleteModal"
                                    x-cloak
                                    class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 dark:bg-gray-900/60 backdrop-blur-sm">
                                    <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md rounded-xl border border-gray-200/50 dark:border-gray-700/50 shadow-xl w-full max-w-md p-6 relative" @click.outside="openDeleteModal = false">
                                        <button @click="openDeleteModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                                            <x-heroicon-s-x-mark class="w-5 h-5"/>
                                        </button>
                                        
                                        <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">{{ __('messages.delete_role') }}</h2>
                                        <p class="text-sm text-gray-600 dark:text-gray-300 mb-6">{{ __('messages.delete_role_confirm') }}</p>
                                        
                                        <form action="{{ route('roles.destroy', $role) }}" method="POST">
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