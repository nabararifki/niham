<nav x-data="{ open: false }"
    class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md transition-colors duration-200 rounded-none z-30">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo (Mobile Only) -->
                <div class="shrink-0 flex items-center md:hidden">
                    <a href="{{ route('dashboard') }}">
                        @if(isset($activeProperty) && $activeProperty->logo_path)
                            <img src="{{ asset('storage/' . $activeProperty->logo_path) }}"
                                alt="{{ $activeProperty->name }} Logo" class="block h-10 w-auto object-contain">
                        @else
                            <x-application-logo class="block h-9 w-auto fill-current text-accent" />
                        @endif
                    </a>
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6 sm:gap-4">
                {{-- Property indicator --}}
                @php
                    $currentProperty = null;
                    if (Auth::user()->isSuperAdmin()) {
                        $activeId = session('active_property_id');
                        $currentProperty = $activeId ? \App\Models\Property::find($activeId) : null;
                    } else {
                        $currentProperty = Auth::user()->property;
                    }
                @endphp

                @if (Auth::user()->isSuperAdmin())
                    {{-- Property Switcher for Super Admin --}}
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button
                                class="inline-flex items-center px-3 py-2 border border-accent text-sm leading-4 font-medium rounded-md text-accent bg-accent/10 hover:bg-accent/20 focus:outline-none transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                    </path>
                                </svg>
                                {{ $currentProperty ? $currentProperty->name : __('messages.all_properties') }}
                                <svg class="fill-current h-4 w-4 ms-1" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            {{-- All Properties option --}}
                            <form method="POST" action="{{ route('properties.switch') }}">
                                @csrf
                                <input type="hidden" name="property_id" value="">
                                <button type="submit"
                                    class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-700 transition duration-150 ease-in-out {{ !$currentProperty ? 'font-bold text-indigo-600 dark:text-indigo-400 bg-accent/10 dark:bg-accent/20' : '' }}">
                                    {{ __('messages.all_properties') }}
                                </button>
                            </form>

                            @foreach (\App\Models\Property::orderBy('name')->get() as $prop)
                                <form method="POST" action="{{ route('properties.switch') }}">
                                    @csrf
                                    <input type="hidden" name="property_id" value="{{ $prop->id }}">
                                    <button type="submit"
                                        class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-700 transition duration-150 ease-in-out {{ $currentProperty && $currentProperty->id === $prop->id ? 'font-bold text-indigo-600 dark:text-indigo-400 bg-accent/10 dark:bg-accent/20' : '' }}">
                                        {{ $prop->name }}
                                    </button>
                                </form>
                            @endforeach
                        </x-slot>
                    </x-dropdown>
                @elseif ($currentProperty)
                    {{-- Property label for normal users --}}
                    <span
                        class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                            </path>
                        </svg>
                        {{ $currentProperty->name }}
                    </span>
                @endif

                {{-- Property Settings Button for Super Admins or Property Admins --}}
                @if (Auth::user()->isSuperAdmin() || strtolower(optional(Auth::user()->role)->name) === 'admin')
                    @php
                        $settingsRoute = Auth::user()->isSuperAdmin()
                            ? route('properties.index')
                            : ($currentProperty ? route('properties.edit', $currentProperty) : '#');
                    @endphp
                    @if($settingsRoute !== '#')
                        <a href="{{ $settingsRoute }}"
                            class="inline-flex items-center p-2 border border-transparent text-sm font-medium rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none transition ease-in-out duration-150"
                            aria-label="Property Settings" title="Property Settings">
                            <x-heroicon-s-cog-6-tooth class="w-5 h-5" />
                        </a>
                    @endif
                @endif

                <!-- Language Switcher -->
                <x-dropdown align="right" width="32">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none transition ease-in-out duration-150"
                            aria-label="Language options">
                            <div>{{ strtoupper(app()->getLocale()) }}</div>
                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('lang.switch', 'en')">🇬🇧 English</x-dropdown-link>
                        <x-dropdown-link :href="route('lang.switch', 'id')">🇮🇩 Indonesia</x-dropdown-link>
                    </x-slot>
                </x-dropdown>

                <!-- Theme Switcher -->
                <x-dropdown align="right" width="32">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center px-2 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none transition ease-in-out duration-150"
                            aria-label="Theme options">
                            <template x-if="theme === 'light'"><x-heroicon-s-sun class="w-5 h-5" /></template>
                            <template x-if="theme === 'dark'"><x-heroicon-s-moon class="w-5 h-5" /></template>
                            <template x-if="theme === 'system'"><x-heroicon-s-computer-desktop
                                    class="w-5 h-5" /></template>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <button @click="theme = 'light'"
                            class="block w-full px-4 py-2 text-left text-sm leading-5 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition">{{ __('messages.light') }}</button>
                        <button @click="theme = 'dark'"
                            class="block w-full px-4 py-2 text-left text-sm leading-5 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition">{{ __('messages.dark') }}</button>
                        <button @click="theme = 'system'"
                            class="block w-full px-4 py-2 text-left text-sm leading-5 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800 transition">{{ __('messages.system') }}</button>
                    </x-slot>
                </x-dropdown>

                <!-- Notification Bell -->
                <x-dropdown align="right" width="96">
                    <x-slot name="trigger">
                        <button
                            class="relative inline-flex items-center px-2 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none transition ease-in-out duration-150"
                            aria-label="Notifications">
                            <x-heroicon-s-bell class="w-5 h-5" />
                            @if(auth()->user()->unreadNotifications->count() > 0)
                                <span
                                    class="absolute top-1 right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white transform bg-red-600 rounded-full">
                                    {{ auth()->user()->unreadNotifications->count() }}
                                </span>
                            @endif
                        </button>
                    </x-slot>
                    <x-slot name="content" x-data="{ showSettings: false }">
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 font-semibold text-sm text-gray-700 dark:text-gray-300 flex justify-between items-center"
                            @click.stop>
                            <div class="flex items-center gap-2">
                                <span>{{ __('messages.notifications') }}</span>
                                <button type="button" @click.prevent="$dispatch('open-modal', 'notification-settings')"
                                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 focus:outline-none">
                                    <x-heroicon-s-cog-6-tooth class="w-4 h-4" />
                                </button>
                            </div>
                            <div class="flex items-center gap-3">
                                @if(auth()->user()->unreadNotifications->count() > 0)
                                    <form method="POST" action="{{ route('notifications.markAllAsRead') }}" class="inline">
                                        @csrf
                                        <button type="submit"
                                            class="text-xs text-accent hover:underline focus:outline-none">
                                            {{ __('messages.mark_read') }}
                                        </button>
                                    </form>
                                @endif
                                @if(auth()->user()->notifications()->count() > 0)
                                    <form method="POST" action="{{ route('notifications.clearAll') }}" class="inline">
                                        @csrf
                                        <button type="submit"
                                            class="text-xs text-red-500 dark:text-red-400 hover:underline focus:outline-none">
                                            {{ __('messages.clear_all') }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        <!-- Embedded Settings Collapse (Removed) -->

                        <div class="max-h-96 w-96 overflow-y-auto" @click.stop>
                            @forelse (auth()->user()->notifications as $notification)
                                <div
                                    class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 {{ $notification->read_at ? 'opacity-75' : 'bg-accent/5 dark:bg-accent/10' }}">
                                    <div class="text-sm text-gray-800 dark:text-gray-200 font-medium">
                                        {{ $notification->data['message'] ?? 'Notification' }}
                                    </div>
                                    <div class="text-xs text-gray-400 mt-1 flex justify-between">
                                        <span>{{ $notification->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                            @empty
                                <div class="px-4 py-8 text-sm text-gray-500 dark:text-gray-400 text-center">
                                    {{ __('messages.no_notifications_found') }}
                                </div>
                            @endforelse
                        </div>
                    </x-slot>
                </x-dropdown>

                <!-- Settings Dropdown -->
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('messages.profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('messages.log_out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open"
                    class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-500/10 focus:outline-none focus:bg-gray-500/10 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex"
                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round"
                            stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}"
        class="hidden sm:hidden bg-white/90 dark:bg-gray-800/90 backdrop-blur-md">
        <div class="pt-2 pb-3 space-y-1 border-b border-gray-200 dark:border-gray-700">
            <!-- Mobile Language Switch -->
            <div class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400">
                {{ __('messages.language') }}:
                <a href="{{ route('lang.switch', 'en') }}"
                    class="{{ app()->getLocale() == 'en' ? 'font-bold' : '' }}">EN</a> |
                <a href="{{ route('lang.switch', 'id') }}"
                    class="{{ app()->getLocale() == 'id' ? 'font-bold' : '' }}">ID</a>
            </div>
            <!-- Mobile Theme Switch -->
            <div class="px-4 py-2 flex gap-4 text-gray-600 dark:text-gray-400">
                <button @click="theme = 'light'" :class="theme === 'light' ? 'text-accent' : ''"><x-heroicon-s-sun
                        class="w-5 h-5" /></button>
                <button @click="theme = 'dark'" :class="theme === 'dark' ? 'text-accent' : ''"><x-heroicon-s-moon
                        class="w-5 h-5" /></button>
                <button @click="theme = 'system'"
                    :class="theme === 'system' ? 'text-accent' : ''"><x-heroicon-s-computer-desktop
                        class="w-5 h-5" /></button>
            </div>
        </div>

        @can('viewAny', App\Models\Asset::class)
            <div class="pt-2 pb-3 space-y-1">
                <x-responsive-nav-link :href="route('assets.index')" :active="request()->routeIs('assets.index')">
                    {{ __('messages.assets') }}
                </x-responsive-nav-link>
            </div>
        @endcan

        @can('viewAny', App\Models\User::class)
            <div class="pt-2 pb-3 space-y-1">
                <x-responsive-nav-link :href="route('users.index')" :active="request()->routeIs('users.index')">
                    {{ __('messages.users') }}
                </x-responsive-nav-link>
            </div>
        @endcan

        @can('viewAny', App\Models\Category::class)
            <div class="pt-2 pb-3 space-y-1">
                <x-responsive-nav-link :href="route('categories.index')" :active="request()->routeIs('categories.index')">
                    {{ __('messages.categories') }}
                </x-responsive-nav-link>
            </div>
        @endcan

        @can('viewAny', App\Models\Department::class)
            <div class="pt-2 pb-3 space-y-1">
                <x-responsive-nav-link :href="route('departments.index')" :active="request()->routeIs('departments.index')">
                    {{ __('messages.departments') }}
                </x-responsive-nav-link>
            </div>
        @endcan

        @can('viewAny', App\Models\Role::class)
            <div class="pt-2 pb-3 space-y-1">
                <x-responsive-nav-link :href="route('roles.index')" :active="request()->routeIs('roles.index')">
                    {{ __('messages.roles') }}
                </x-responsive-nav-link>
            </div>
        @endcan

        @if (Auth::user()->isSuperAdmin())
            <div class="pt-2 pb-3 space-y-1">
                <x-responsive-nav-link :href="route('properties.index')" :active="request()->routeIs('properties.*')">
                    {{ __('messages.properties') }}
                </x-responsive-nav-link>
            </div>

            {{-- Mobile Property Switcher (collapsible) --}}
            <div class="pt-2 pb-3 border-t border-gray-200" x-data="{ switcherOpen: false }">
                <button @click="switcherOpen = !switcherOpen" type="button"
                    class="w-full flex items-center justify-between px-4 py-2 text-sm font-semibold text-gray-700">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                            </path>
                        </svg>
                        <span>{{ $currentProperty ? $currentProperty->name : __('messages.all_properties') }}</span>
                    </span>
                    <svg class="w-4 h-4 transition-transform duration-200" :class="switcherOpen ? 'rotate-180' : ''"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="switcherOpen" x-collapse x-cloak class="space-y-1 mt-1">
                    <form method="POST" action="{{ route('properties.switch') }}">
                        @csrf
                        <input type="hidden" name="property_id" value="">
                        <button type="submit"
                            class="block w-full text-start px-8 py-2 text-sm {{ !$currentProperty ? 'font-bold text-indigo-600 dark:text-indigo-400 bg-accent/10 dark:bg-accent/20' : 'text-gray-600 dark:text-gray-300' }} hover:bg-gray-100 dark:hover:bg-gray-700 transition duration-150 ease-in-out">
                            {{ __('messages.all_properties') }}
                        </button>
                    </form>
                    @foreach (\App\Models\Property::orderBy('name')->get() as $prop)
                        <form method="POST" action="{{ route('properties.switch') }}">
                            @csrf
                            <input type="hidden" name="property_id" value="{{ $prop->id }}">
                            <button type="submit"
                                class="block w-full text-start px-8 py-2 text-sm {{ $currentProperty && $currentProperty->id === $prop->id ? 'font-bold text-indigo-600 dark:text-indigo-400 bg-accent/10 dark:bg-accent/20' : 'text-gray-600 dark:text-gray-300' }} hover:bg-gray-100 dark:hover:bg-gray-700 transition duration-150 ease-in-out">
                                {{ $prop->name }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                @if ($currentProperty)
                    <div class="font-medium text-xs text-accent mt-1">{{ $currentProperty->name }}</div>
                @endif
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('messages.profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')" onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('messages.log_out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>

<!-- Notification Settings Modal -->
<x-modal name="notification-settings" focusable x-cloak>
    <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
            {{ __('messages.notification_settings') }}
        </h2>

        <form method="post" action="{{ route('profile.update.notifications') }}" class="space-y-4">
            @csrf
            @method('patch')
            <div
                x-data="{ allProp: {{ auth()->user()->notify_all_properties ? 'true' : 'false' }}, dept: {{ auth()->user()->notify_department ? 'true' : 'false' }} }">
                <!-- All Properties -->
                <div class="flex items-start gap-3">
                    <input type="hidden" name="notify_all_properties" value="0">
                    <input type="checkbox" id="modal_notify_all_properties" name="notify_all_properties" value="1"
                        x-model="allProp"
                        class="mt-1 rounded border-gray-300 text-accent focus:ring-accent dark:bg-gray-900/50 dark:border-gray-700">
                    <label for="modal_notify_all_properties"
                        class="text-sm font-medium text-gray-700 dark:text-gray-300 leading-tight">
                        {{ __('messages.all_properties_notifications') }}
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-normal">
                            {{ __('messages.receive_events_across_all_managed') }}
                        </p>
                    </label>
                </div>
                <!-- Department Notifications -->
                <div class="flex items-start gap-3 mt-4">
                    <input type="hidden" name="notify_department" value="0">
                    <input type="checkbox" id="modal_notify_department" name="notify_department" value="1"
                        x-model="dept" x-bind:disabled="allProp"
                        class="mt-1 rounded border-gray-300 text-accent focus:ring-accent dark:bg-gray-900/50 dark:border-gray-700 disabled:opacity-50">
                    <label for="modal_notify_department"
                        class="text-sm font-medium text-gray-700 dark:text-gray-300 leading-tight"
                        :class="allProp ? 'opacity-50' : ''">
                        {{ __('messages.department_notifications') }}
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-normal">
                            {{ __('messages.receive_updates_for_assets_belonging_strictly_to_y') }}
                        </p>
                    </label>
                </div>
                <!-- Email Digest -->
                <div class="flex items-start gap-3 mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <input type="hidden" name="notify_email" value="0">
                    <input type="checkbox" id="modal_notify_email" name="notify_email" value="1" {{ auth()->user()->notify_email ? 'checked' : '' }}
                        class="mt-1 rounded border-gray-300 text-accent focus:ring-accent dark:bg-gray-900/50 dark:border-gray-700">
                    <label for="modal_notify_email"
                        class="text-sm font-medium text-gray-700 dark:text-gray-300 leading-tight">
                        {{ __('messages.email_notifications') }}
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-normal">
                            {{ __('messages.deliver_these_alerts_to_your_primary_inbox') }}
                        </p>
                    </label>
                </div>
                <!-- Delivery Frequency -->
                <div class="flex flex-col gap-2 mt-4 pl-7">
                    <label for="modal_email_frequency" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ __('messages.email_delivery_frequency') }}
                    </label>
                    <select id="modal_email_frequency" name="email_frequency"
                        class="block w-full sm:w-64 text-sm rounded-md shadow-sm border-gray-300 dark:bg-gray-900/50 dark:border-gray-700 dark:text-gray-100 focus:ring-accent focus:border-accent">
                        <option value="immediate" {{ auth()->user()->email_frequency === 'immediate' ? 'selected' : '' }}>
                            {{ __('messages.immediate_real_time') }}
                        </option>
                        <option value="hourly" {{ auth()->user()->email_frequency === 'hourly' ? 'selected' : '' }}>
                            {{ __('messages.hourly_summary') }}
                        </option>
                        <option value="daily" {{ auth()->user()->email_frequency === 'daily' ? 'selected' : '' }}>
                            {{ __('messages.daily_summary_digest') }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('messages.cancel') }}
                </x-secondary-button>
                <x-primary-button>
                    {{ __('messages.save_changes') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-modal>