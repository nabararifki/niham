<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center text-gray-900 dark:text-gray-100">
            <h2 class="font-semibold text-xl leading-tight">
                {{ __('messages.users') ?? 'Users' }}
            </h2>
            <div>
                @can('create', App\Models\User::class)
                <a href="{{ route('users.create') }}"
                class="inline-flex items-center px-4 py-2 bg-accent border border-transparent rounded-md 
                        font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90 
                        focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 transition">
                    <x-heroicon-s-plus class="w-4 h-4 mr-2" />
                    {{ __('messages.new_user') }}
                </a>
                @endcan
            </div>
        </div>
    </x-slot>


    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-2">
            <!-- Filter Toggle & Panel -->
            @php
                $activeFilters = collect(['department', 'role'])
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
                        <a href="{{ route('users.index') }}" class="text-sm text-gray-500 hover:text-gray-700 underline">{{ __('messages.clear_all') }}</a>
                    @endif
                </div>

                <!-- Collapsible Filter Panel -->
                <div
                    x-show="filtersOpen"
                    x-collapse
                    x-cloak
                >
                    <form method="GET" action="{{ route('users.index') }}" class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md rounded-xl border border-gray-200/50 dark:border-gray-700/50 shadow-sm p-4 sm:p-5 mt-3">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <!-- Department -->
                            <div>
                                <label for="department" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">{{ __('messages.department') }}</label>
                                <select name="department" id="department" class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-100 rounded-lg shadow-sm text-sm focus:ring-accent focus:border-accent">
                                    <option value="">{{ __('messages.all') }}</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}" {{ request('department') == $department->id ? 'selected' : '' }}>
                                            {{ $department->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Role -->
                            <div>
                                <label for="role" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">{{ __('messages.role') }}</label>
                                <select name="role" id="role" class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-100 rounded-lg shadow-sm text-sm focus:ring-accent focus:border-accent">
                                    <option value="">{{ __('messages.all') }}</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" {{ request('role') == $role->id ? 'selected' : '' }}>
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Apply button inline with filters -->
                            <div class="flex items-end">
                                <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-accent border border-transparent rounded-lg
                                        font-semibold text-xs text-white uppercase tracking-widest hover:opacity-90
                                        focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 transition">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                    {{ __('messages.apply') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table -->
            @php
                $canUpdateUsers = Auth::user()->hasPermission('perm_users', 'update');
                $canDeleteUsers = Auth::user()->hasPermission('perm_users', 'delete');
                $canBulkManageUsers = $canUpdateUsers || $canDeleteUsers;
                $canReassignDepartment = Auth::user()->isSuperAdmin() || Auth::user()->isRole('admin') || Auth::user()->hasExecutiveOversight();

                $selectableUserIds = collect($users->items())
                    ->filter(fn ($u) => $u->id !== Auth::id() && ! $u->isSuperAdmin() && $u->name !== 'Admin')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values();

                $bulkDepartmentOptions = $departments->map(fn ($d) => ['id' => (int) $d->id, 'name' => $d->name])->values();
                $bulkRoleOptions = ($bulkRoles ?? $roles)->map(fn ($r) => ['id' => (int) $r->id, 'name' => ucwords($r->name)])->values();
                $emptyUserColspan = 4 + (Auth::user()->isSuperAdmin() ? 1 : 0);
            @endphp

            <div class="mt-6">
                <x-datagrid
                    :paginator="$users"
                    :selectable="$canBulkManageUsers"
                    :selectable-ids="$selectableUserIds"
                    :bulk-update-url="$canUpdateUsers ? route('users.bulk-update') : null"
                    :bulk-delete-url="$canDeleteUsers ? route('users.bulk-delete') : null"
                    :can-delete="$canDeleteUsers"
                    :options="['departments' => $bulkDepartmentOptions, 'roles' => $bulkRoleOptions]"
                >
                    <x-slot:head>
                        <x-datagrid.th :label="__('messages.name')" />
                        <x-datagrid.th
                            :label="__('messages.department')"
                            :field="$canUpdateUsers && $canReassignDepartment ? 'department_id' : null"
                            :bulk-type="$canUpdateUsers && $canReassignDepartment ? 'select' : null"
                            bulk-options="departments"
                            bulk-width="w-36"
                        />
                        <x-datagrid.th
                            :label="__('messages.role')"
                            :field="$canUpdateUsers ? 'role_id' : null"
                            :bulk-type="$canUpdateUsers ? 'select' : null"
                            bulk-options="roles"
                            bulk-width="w-36"
                        />
                        @if (Auth::user()->isSuperAdmin())
                            <x-datagrid.th :label="__('messages.property')" />
                        @endif
                    </x-slot:head>

                    @forelse($users as $user)
                        @php
                            $isProtectedUser = $user->id === Auth::id() || $user->isSuperAdmin() || $user->name === 'Admin';
                        @endphp
                        <x-datagrid.row
                            :row-id="$user->id"
                            :row-number="$users->firstItem() + $loop->index"
                            :selectable="$canBulkManageUsers"
                            :disabled-selection="$isProtectedUser"
                            :disabled-title="__('messages.protected_account') ?? 'Protected account (cannot bulk-select)'"
                        >
                            <td class="px-4 py-2 text-sm text-accent font-semibold hover:underline whitespace-nowrap">
                                <a href="{{ route('users.show',$user) }}">{{ $user->name }}</a>
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200 whitespace-nowrap">{{ optional($user->department)->name ?? '-' }}</td>
                            <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200 whitespace-nowrap">{{ optional($user->role)->name ?? '-' }}</td>
                            @if (Auth::user()->isSuperAdmin())
                                <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-200 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full text-white shadow-sm" style="background-color: {{ optional($user->property)->accent_color ?? '#6b7280' }}">
                                        {{ optional($user->property)->name ?? '-' }}
                                    </span>
                                </td>
                            @endif
                        </x-datagrid.row>
                    @empty
                        <tr>
                            <td colspan="{{ $emptyUserColspan }}" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                {{ __('messages.no_data_found') ?? 'No data found' }}
                            </td>
                        </tr>
                    @endforelse
                </x-datagrid>
            </div>
        </div>
    </div>
</x-app-layout>
