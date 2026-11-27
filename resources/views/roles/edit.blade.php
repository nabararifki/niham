<x-app-layout>
<div class="py-4 sm:py-8">
        <div class="mx-auto max-w-4xl px-3 sm:px-6 lg:px-8">
            <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md rounded-xl border border-gray-200/50 dark:border-gray-700/50 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200/50 dark:border-gray-700/50">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        {{ __('messages.edit_role') ?? __('messages.edit_role') }}
                    </h2>
                </div>
                <div class="p-6">
                <form method="POST" action="{{ route('roles.update', $role) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <!-- Name -->
                    <div>
                        <x-input-label for="name" :value="__('messages.role_name')" />
                        <x-text-input
                            id="name"
                            name="name"
                            type="text"
                            class="mt-1 block w-full"
                            required
                            value="{{ old('name', $role->name) }}"
                        />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    @php
                        $options = [
                            'no access' => 'messages.no_access', 
                            'view only' => 'messages.view_only', 
                            'create' => 'messages.create', 
                            'update' => 'messages.update', 
                            'delete' => 'messages.delete', 
                            'create & update' => 'messages.create_update', 
                            'create & delete' => 'messages.create_delete', 
                            'update & delete' => 'messages.update_delete', 
                            'full access' => 'messages.full_access'
                        ];
                        $perms = ['perm_assets' => 'messages.assets', 'perm_users' => 'messages.users', 'perm_categories' => 'messages.categories', 'perm_departments' => 'messages.departments', 'perm_roles' => 'messages.roles'];
                    @endphp
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        @foreach($perms as $field => $label)
                        <div>
                            <x-input-label :for="$field" :value="__($label) . ' ' . __('messages.permissions')" />
                            <select id="{{ $field }}" name="{{ $field }}" class="block w-full mt-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach($options as $opt => $transKey)
                                    <option value="{{ $opt }}" {{ old($field, $role->$field ?? 'no access') === $opt ? 'selected' : '' }}>
                                        {{ ucwords(__($transKey)) }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get($field)" class="mt-2" />
                        </div>
                        @endforeach
                    </div>


                    <div class="mt-6 flex justify-between items-center">
                        <!-- Back Button -->
                        <div class="mt-6 flex justify-start">
                            <x-secondary-button onclick="window.history.back()">
                                <x-heroicon-s-arrow-left class="w-4 h-4 mr-2" />
                                {{ __('messages.back') }}                            </x-secondary-button>

                        </div>
                        <!-- Submit Button -->
                        <div class="mt-6 flex justify-end">
                            <x-primary-button>
                                <x-heroicon-s-bookmark class="w-4 h-4 mr-2" />
                                {{ __('messages.save') }}
                            </x-primary-button>
                        </div>
                    </div>
                </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
