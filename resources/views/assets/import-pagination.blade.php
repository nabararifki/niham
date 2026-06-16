@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('messages.pagination_navigation') }}" class="flex items-center justify-between">
        <div class="flex justify-between flex-1 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-500 bg-white/90 backdrop-blur-md border border-gray-300 cursor-default leading-5 rounded-md dark:text-gray-600 dark:bg-gray-800/90 dark:border-gray-600">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white/90 backdrop-blur-md border border-gray-300 leading-5 rounded-md hover:text-gray-500 focus:outline-none focus:ring ring-gray-300 focus:border-blue-300 active:bg-gray-100 active:text-gray-700 transition ease-in-out duration-150 dark:bg-gray-800/90 dark:border-gray-600 dark:text-gray-300 dark:focus:border-blue-700 dark:active:bg-gray-700 dark:active:text-gray-300">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-gray-700 bg-white/90 backdrop-blur-md border border-gray-300 leading-5 rounded-md hover:text-gray-500 focus:outline-none focus:ring ring-gray-300 focus:border-blue-300 active:bg-gray-100 active:text-gray-700 transition ease-in-out duration-150 dark:bg-gray-800/90 dark:border-gray-600 dark:text-gray-300 dark:focus:border-blue-700 dark:active:bg-gray-700 dark:active:text-gray-300">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="relative inline-flex items-center px-4 py-2 ml-3 text-sm font-medium text-gray-500 bg-white/90 backdrop-blur-md border border-gray-300 cursor-default leading-5 rounded-md dark:text-gray-600 dark:bg-gray-800/90 dark:border-gray-600">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>

        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-700 leading-5 dark:text-gray-400">
                    {!! __('messages.showing') !!}
                    @if ($paginator->firstItem())
                        <span class="font-medium">{{ $paginator->firstItem() }}</span>
                        {!! __('messages.to') !!}
                        <span class="font-medium">{{ $paginator->lastItem() }}</span>
                    @else
                        {{ $paginator->count() }}
                    @endif
                    {!! __('messages.of') !!}
                    <span class="font-medium">{{ $paginator->total() }}</span>
                    {!! __('messages.results') !!}
                </p>
            </div>

            <div>
                <span class="relative z-0 inline-flex rtl:flex-row-reverse shadow-sm rounded-md">
                    {{-- Previous Page Link --}}
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                            <span class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-gray-500 bg-white/90 backdrop-blur-md border border-gray-300 cursor-default rounded-l-md leading-5 dark:bg-gray-800/90 dark:border-gray-600" aria-hidden="true">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="relative inline-flex items-center px-2 py-2 text-sm font-medium text-gray-500 bg-white/90 backdrop-blur-md border border-gray-300 rounded-l-md leading-5 hover:text-gray-400 focus:z-10 focus:outline-none focus:ring ring-gray-300 focus:border-blue-300 active:bg-gray-100 active:text-gray-500 transition ease-in-out duration-150 dark:bg-gray-800/90 dark:border-gray-600 dark:active:bg-gray-700 dark:focus:border-blue-800" aria-label="{{ __('pagination.previous') }}">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <span aria-disabled="true">
                                <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium text-gray-700 bg-white/90 backdrop-blur-md border border-gray-300 cursor-default leading-5 dark:bg-gray-800/90 dark:border-gray-600">{{ $element }}</span>
                            </span>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @php
                                    $isPageInvalid = in_array($page, $invalidPages ?? []);
                                @endphp
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page">
                                        <span class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium cursor-default leading-5 {{ $isPageInvalid ? 'text-red-600 dark:text-red-400 bg-red-50/50 dark:bg-red-950/20 border border-red-500/50 dark:border-red-800/50' : 'text-gray-500 bg-white/90 dark:text-gray-400 dark:bg-gray-800/90 border border-gray-300 dark:border-gray-600' }}"
                                              :class="invalidPages.includes({{ $page }}) ? 'text-red-600 dark:text-red-400 bg-red-50/50 dark:bg-red-950/20 border border-red-500/50 dark:border-red-800/50' : 'text-gray-500 bg-white/90 dark:text-gray-400 dark:bg-gray-800/90 border border-gray-300 dark:border-gray-600'">
                                            {{ $page }}
                                            <span x-show="invalidPages.includes({{ $page }})" class="absolute top-1 right-1 flex h-1.5 w-1.5" x-cloak>
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                                <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-red-500"></span>
                                            </span>
                                        </span>
                                    </span>
                                @else
                                    <a href="{{ $url }}" class="relative inline-flex items-center px-4 py-2 -ml-px text-sm font-medium leading-5 transition ease-in-out duration-150 {{ $isPageInvalid ? 'text-red-600 dark:text-red-400 bg-red-50/30 dark:bg-red-950/10 border border-red-300 dark:border-red-800/80 hover:bg-red-50 dark:hover:bg-red-950/30' : 'text-gray-700 bg-white/90 dark:text-gray-400 dark:bg-gray-800/90 border border-gray-300 dark:border-gray-600 hover:text-gray-500' }} focus:z-10 focus:outline-none focus:ring ring-gray-300 focus:border-blue-300 active:bg-gray-100 active:text-gray-700 dark:active:bg-gray-700 dark:focus:border-blue-800"
                                       :class="invalidPages.includes({{ $page }}) ? 'text-red-600 dark:text-red-400 bg-red-50/30 dark:bg-red-950/10 border border-red-300 dark:border-red-800/80 hover:bg-red-50 dark:hover:bg-red-950/30' : 'text-gray-700 bg-white/90 dark:text-gray-400 dark:bg-gray-800/90 border border-gray-300 dark:border-gray-600 hover:text-gray-500'"
                                       aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                        {{ $page }}
                                        <span x-show="invalidPages.includes({{ $page }})" class="absolute top-1 right-1 flex h-1.5 w-1.5" x-cloak>
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-red-500"></span>
                                        </span>
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="relative inline-flex items-center px-2 py-2 -ml-px text-sm font-medium text-gray-500 bg-white/90 backdrop-blur-md border border-gray-300 rounded-r-md leading-5 hover:text-gray-400 focus:z-10 focus:outline-none focus:ring ring-gray-300 focus:border-blue-300 active:bg-gray-100 active:text-gray-500 transition ease-in-out duration-150 dark:bg-gray-800/90 dark:border-gray-600 dark:active:bg-gray-700 dark:focus:border-blue-800" aria-label="{{ __('pagination.next') }}">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    @else
                        <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                            <span class="relative inline-flex items-center px-2 py-2 -ml-px text-sm font-medium text-gray-500 bg-white/90 backdrop-blur-md border border-gray-300 cursor-default rounded-r-md leading-5 dark:bg-gray-800/90 dark:border-gray-600" aria-hidden="true">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                </svg>
                            </span>
                        </span>
                    @endif
                </span>
            </div>
        </div>
    </nav>
@endif
