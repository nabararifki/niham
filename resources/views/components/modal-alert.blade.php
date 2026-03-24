{{-- x-teleport MUST be on a <template> element in Alpine v3 --}}
<template x-teleport="body">
<div
    x-data="{ open: false, title: 'Alert', message: '', type: 'info' }"
    @show-alert.window="open = true; title = $event.detail.title || 'Alert'; message = $event.detail.message; type = $event.detail.type || 'info'"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-[200] flex items-center justify-center bg-gray-900/40 dark:bg-gray-900/60 backdrop-blur-sm"
>
    <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md border border-gray-200/50 dark:border-gray-700/50 rounded-xl shadow-xl p-6 w-full max-w-sm relative"
         @click.away="open = false">
        
        <!-- Close button -->
        <button 
            @click="open = false" 
            class="absolute top-3 right-3 flex items-center justify-center
                w-7 h-7 rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200
                focus:outline-none focus:ring-2 focus:ring-accent transition"
        >
            <x-heroicon-s-x-mark class="w-4 h-4"/>
        </button>

        <!-- Message Content -->
        <div class="text-center pt-2">
            <template x-if="type === 'success'">
                <div class="mx-auto flex flex-shrink-0 items-center justify-center w-12 h-12 rounded-full bg-green-100 mb-4">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
            </template>
            <template x-if="type === 'error'">
                <div class="mx-auto flex flex-shrink-0 items-center justify-center w-12 h-12 rounded-full bg-red-100 mb-4">
                    <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
            </template>
            <template x-if="type === 'info'">
                <div class="mx-auto flex flex-shrink-0 items-center justify-center w-12 h-12 rounded-full bg-blue-100 mb-4">
                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                </div>
            </template>

            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200" x-text="title"></h3>
            <p class="mt-2 text-sm text-gray-600" x-text="message"></p>
        </div>

        <!-- Action -->
        <div class="mt-6 flex justify-center">
            <button 
                @click="open = false" 
                class="inline-flex items-center px-5 py-2.5 bg-accent border border-transparent 
                    rounded-xl font-semibold text-xs text-white uppercase tracking-widest 
                    hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-accent 
                    focus:ring-offset-2 transition"
                x-text="'OK'"
            >
            </button>
        </div>
    </div>
</div>
</template>
