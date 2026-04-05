<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100 leading-tight">
                {{ __('messages.roles') }}
            </h2>
            <div>
                @can('create', App\Models\Role::class)
                <a href="{{ route('roles.create') }}"
                class="inline-flex items-center px-4 py-2 bg-accent border border-transparent rounded-md 
                        font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 
                        focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 transition">
                    <x-heroicon-s-plus class="w-4 h-4 mr-2" />
                    {{ __('messages.new_role') }}
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
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">{{ __('messages.no') }}</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">{{ __('messages.name') }}</th>
                            @if(Auth::user()->isSuperAdmin())
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">{{ __('messages.property') }}</th>
                            @endif
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-200 uppercase tracking-wider">{{ __('messages.permissions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200/50 dark:divide-gray-700/50">
                        @foreach($roles as $role)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <!-- No -->
                                <td class="px-4 py-3 text-center text-sm text-gray-700 dark:text-gray-200 whitespace-nowrap">
                                    {{ $roles->firstItem() + $loop->index }}
                                </td>

                                <!-- Role Name -->
                                <td class="px-4 py-3 text-center text-sm font-semibold text-accent hover:underline whitespace-nowrap">
                                    <a href="{{ route('roles.show', $role) }}">{{ ucwords($role->name) }}</a>
                                </td>

                                @if(Auth::user()->isSuperAdmin())
                                    <td class="px-4 py-3 text-center text-sm text-gray-700 dark:text-gray-200 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full text-white shadow-sm" style="background-color: {{ optional($role->property)->accent_color ?? '#6b7280' }}">
                                            {{ optional($role->property)->name ?? '-' }}
                                        </span>
                                    </td>
                                @endif

                                <!-- Grouped Permissions Badges -->
                                <td class="px-4 py-3 text-center">
                                    @php
                                        $modules = [
                                            'assets' => $role->perm_assets,
                                            'users' => $role->perm_users,
                                            'categories' => $role->perm_categories,
                                            'departments' => $role->perm_departments,
                                            'locations' => $role->perm_locations,
                                            'roles' => $role->perm_roles,
                                        ];
                                        $totalPerms = 0;
                                        $maxPerms = count($modules) * 4;
                                        $assignedList = [];

                                        foreach($modules as $key => $permStr) {
                                            $p = $permStr ?? 'no access';
                                            if ($p === 'no access') continue;
                                            
                                            $actions = [];
                                            $actions[] = __('messages.view') ?? 'View';
                                            $totalPerms++;
                                            if ($p === 'full access' || str_contains($p, 'create')) { $actions[] = __('messages.create') ?? 'Create'; $totalPerms++; }
                                            if ($p === 'full access' || str_contains($p, 'update')) { $actions[] = __('messages.edit') ?? 'Edit'; $totalPerms++; }
                                            if ($p === 'full access' || str_contains($p, 'delete')) { $actions[] = __('messages.delete') ?? 'Delete'; $totalPerms++; }
                                            
                                            $assignedList[] = ucfirst(__('messages.' . $key)) . ': ' . implode(', ', $actions);
                                        }
                                        $tooltipText = implode(' | ', $assignedList);
                                    @endphp

                                    @if($totalPerms === 0)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700 shadow-sm">
                                            {{ __('messages.no_access') }}
                                        </span>
                                    @elseif($totalPerms === $maxPerms)
                                        <span title="{{ $tooltipText }}" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-accent/10 text-accent border border-accent/20 dark:bg-accent/20 dark:text-indigo-300 shadow-sm cursor-help">
                                            <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                            {{ __('messages.full_access') ?? 'Full Access' }}
                                        </span>
                                    @else
                                        <span title="{{ $tooltipText }}" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800 shadow-sm cursor-help">
                                            {{ $totalPerms }} {{ __('messages.permissions_assigned') ?? 'Permissions Assigned' }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                </table>
            </div>
            <!-- Pagination -->
            <div class="mt-4">
                {{ $roles->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
