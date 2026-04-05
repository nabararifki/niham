<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    x-data="{ theme: localStorage.getItem('theme') || 'light' }"
    x-init="$watch('theme', val => localStorage.setItem('theme', val))"
    x-bind:class="{ 'dark': theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) }">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'NIHAM') }}</title>

    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('niham-logo-cr-rd.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link href="https://unpkg.com/tailwindcss@1.9.6/dist/tailwind.min.css" rel="stylesheet">
    <style>
        [x-cloak] {
            display: none !important;
        }

        :root {
            --accent-color:
                {{ $activeProperty->accent_color ?? '#4f46e5' }}
            ;
            --property-accent:
                {{ $activeProperty->accent_color ?? '#4f46e5' }}
            ;
            --property-accent-transparent:
                {{ $activeProperty->accent_color ?? '#4f46e5' }}
                33;
        }

        .bg-accent {
            background-color: var(--accent-color) !important;
        }

        .text-accent {
            color: var(--accent-color) !important;
        }

        .border-accent {
            border-color: var(--accent-color) !important;
        }

        .ring-accent:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.4);
            border-color: var(--accent-color) !important;
        }
    </style>
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $bgImage = isset($activeProperty) && $activeProperty->background_image_path
        ? asset('storage/' . $activeProperty->background_image_path)
        : asset('global-background.png');
@endphp

<body
    class="font-sans antialiased bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 transition-colors duration-200 h-screen overflow-hidden">

    <!-- Full-viewport locked layout -->
    <div class="flex h-full">

        <aside
            class="hidden md:flex flex-col w-64 flex-shrink-0 bg-white/90 dark:bg-gray-800/90 backdrop-blur-md h-full z-20">

            <div class="flex items-center justify-center h-16 border-gray-200/50 dark:border-gray-800/50 px-4">
                <a href="{{ route('dashboard') }}" class="flex items-center">
                    @if(isset($activeProperty) && $activeProperty->logo_path)
                        <img src="{{ asset('storage/' . $activeProperty->logo_path) }}"
                            alt="{{ $activeProperty->name }} Logo" class="block h-10 w-auto object-contain">
                    @else
                        <x-application-logo class="block h-10 w-auto fill-current text-accent" />
                    @endif
                </a>
            </div>

            <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto w-full">
                @can('viewAny', App\Models\Asset::class)
                    <a href="{{ route('assets.index') }}"
                        class="flex items-center px-4 py-2.5 text-sm font-medium transition-colors rounded-lg w-full {{ request()->routeIs('assets.*') ? 'text-white' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                        {!! request()->routeIs('assets.*') ? 'style="background-color: var(--property-accent);"' : '' !!}>
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <span class="truncate">{{ __('messages.assets') ?? 'Assets' }}</span>
                    </a>
                @endcan

                @can('viewAny', App\Models\User::class)
                    <a href="{{ route('users.index') }}"
                        class="flex items-center px-4 py-2.5 text-sm font-medium transition-colors rounded-lg w-full {{ request()->routeIs('users.*') ? 'text-white' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                        {!! request()->routeIs('users.*') ? 'style="background-color: var(--property-accent);"' : '' !!}>
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <span class="truncate">{{ __('messages.users') ?? 'Users' }}</span>
                    </a>
                @endcan

                @can('viewAny', App\Models\Category::class)
                    <a href="{{ route('categories.index') }}"
                        class="flex items-center px-4 py-2.5 text-sm font-medium transition-colors rounded-lg w-full {{ request()->routeIs('categories.*') ? 'text-white' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                        {!! request()->routeIs('categories.*') ? 'style="background-color: var(--property-accent);"' : '' !!}>
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                        <span class="truncate">{{ __('messages.categories') ?? 'Categories' }}</span>
                    </a>
                @endcan

                @can('viewAny', App\Models\Department::class)
                    <a href="{{ route('departments.index') }}"
                        class="flex items-center px-4 py-2.5 text-sm font-medium transition-colors rounded-lg w-full {{ request()->routeIs('departments.*') ? 'text-white' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                        {!! request()->routeIs('departments.*') ? 'style="background-color: var(--property-accent);"' : '' !!}>
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <span class="truncate">{{ __('messages.departments') ?? 'Departments' }}</span>
                    </a>
                @endcan

                @can('viewAny', App\Models\Location::class)
                <a href="{{ route('locations.index') }}"
                    class="flex items-center px-4 py-2.5 text-sm font-medium transition-colors rounded-lg w-full {{ request()->routeIs('locations.*') ? 'text-white' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                    {!! request()->routeIs('locations.*') ? 'style="background-color: var(--property-accent);"' : '' !!}>
                    <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="truncate">{{ __('messages.locations') ?? 'Lokasi' }}</span>
                </a>
                @endcan

                @can('viewAny', App\Models\Role::class)
                    <a href="{{ route('roles.index') }}"
                        class="flex items-center px-4 py-2.5 text-sm font-medium transition-colors rounded-lg w-full {{ request()->routeIs('roles.*') ? 'text-white' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                        {!! request()->routeIs('roles.*') ? 'style="background-color: var(--property-accent);"' : '' !!}>
                        <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <span class="truncate">{{ __('messages.roles') ?? 'Roles' }}</span>
                    </a>
                @endcan
            </nav>
        </aside>

        <!-- Right column: nav fixed at top, image only in scroll area -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

            <!-- Top bar: same bg as sidebar, always visible -->
            <div class="flex-shrink-0 bg-white/90 dark:bg-gray-800/90 backdrop-blur-md z-20">
                @include('layouts.navigation')
            </div>

            <!-- Padded image frame: STATIC — bg image never moves -->
            <div class="flex-1 p-3 h-0">
                <div class="h-full rounded-xl overflow-hidden border-gray-200 dark:border-gray-700 border"
                    style="background-image: url('{{ $bgImage }}'); background-size: cover; background-position: center;">
                    <!-- Content scrolls inside the image -->
                    <div class="h-full overflow-y-auto bg-white/10 dark:bg-black/40">

                        @isset($header)
                            <header class="bg-transparent">
                                <div class="max-w-7xl mx-auto pt-6 px-2 sm:px-6 lg:px-8">
                                    <div
                                        class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md border border-gray-200/50 dark:border-gray-700/50 shadow-sm rounded-xl py-6 px-4 sm:px-6 lg:px-8">
                                        {{ $header }}
                                    </div>
                                </div>
                            </header>
                        @endisset

                        <main>
                            {{ $slot }}
                        </main>

                    </div><!-- /scroll -->
                </div><!-- /image container -->
            </div><!-- /padded frame -->

        </div><!-- /right column -->

    </div><!-- /layout -->

    {{-- Success modal --}}
    @if(session('ok'))
        <div x-data="{ open: true }" x-show="open" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 dark:bg-gray-900/60 backdrop-blur-sm">
            <div
                class="bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200/50 dark:border-gray-700/50 p-6 w-full max-w-sm relative">
                <!-- Close button -->
                <button @click="open = false" aria-label="Close"
                    class="absolute top-3 right-3 flex items-center justify-center
                                                                        w-7 h-7 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600
                                                                        focus:outline-none focus:ring-2 focus:ring-accent transition">
                    <x-heroicon-s-x-mark class="w-4 h-4" />
                </button>

                <!-- Message -->
                <div class="text-center pt-2">
                    <div
                        class="mx-auto flex items-center justify-center w-12 h-12 rounded-full bg-green-100 dark:bg-green-900/50 mb-4">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">{{ __('messages.success') }}</h3>
                    <p class="mt-2 text-gray-600 dark:text-gray-300">{{ session('ok') }}</p>
                </div>

                <!-- Action -->
                <div class="mt-5 text-center">
                    <button @click="open = false" class="inline-flex items-center px-5 py-2.5 bg-accent border border-transparent 
                                                                            rounded-xl font-semibold text-xs text-white uppercase tracking-widest 
                                                                            hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-accent 
                                                                            focus:ring-offset-2 transition">
                        {{ __('messages.ok') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Warning modal --}}
    @if(session('warning'))
        <div x-data="{ open: true }" x-show="open" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 dark:bg-gray-900/60 backdrop-blur-sm">
            <div
                class="bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200/50 dark:border-gray-700/50 p-6 w-full max-w-sm relative">
                <!-- Close button -->
                <button @click="open = false" aria-label="Close"
                    class="absolute top-3 right-3 flex items-center justify-center
                                                                        w-7 h-7 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600
                                                                        focus:outline-none focus:ring-2 focus:ring-accent transition">
                    <x-heroicon-s-x-mark class="w-4 h-4" />
                </button>

                <!-- Message -->
                <div class="text-center pt-2">
                    <div
                        class="mx-auto flex items-center justify-center w-12 h-12 rounded-full bg-amber-100 dark:bg-amber-900/50 mb-4">
                        <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">
                        {{ __('messages.warning') ?? 'Warning' }}
                    </h3>
                    <p class="mt-2 text-gray-600 dark:text-gray-300">{{ session('warning') }}</p>
                </div>

                <!-- Action -->
                <div class="mt-5 text-center">
                    <button @click="open = false" class="inline-flex items-center px-5 py-2.5 bg-amber-500 border border-transparent 
                                                                            rounded-xl font-semibold text-xs text-white uppercase tracking-widest 
                                                                            hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-amber-500 
                                                                            focus:ring-offset-2 transition">
                        {{ __('messages.ok') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <x-modal-alert />
</body>

</html>