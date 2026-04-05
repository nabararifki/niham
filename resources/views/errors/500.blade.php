<x-guest-layout>
    <div class="text-center">
        <h1 class="text-6xl font-bold text-accent mb-4">500</h1>
        <h2 class="text-2xl font-semibold text-gray-800 dark:text-gray-200 mb-2">{{ __('messages.server_error') }}</h2>
        <p class="text-gray-600 dark:text-gray-400 mb-8">
            {{ __('messages.sorry_an_error_occurred_on_the_server_our_team_has') }}</p>

        <button onclick="window.history.back()"
            class="inline-flex items-center px-6 py-3 bg-accent border border-transparent rounded-xl font-semibold text-sm text-gray-800 dark:text-gray-200 uppercase tracking-widest hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-accent focus:ring-offset-2 transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            {{ __('messages.go_back') }}
        </button>
    </div>
</x-guest-layout>