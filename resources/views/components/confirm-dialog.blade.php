@props([
    'id' => 'datagrid_confirm_dialog',
    'title' => '',
    'message' => '',
    'alpineTitle' => null,
    'alpineMessage' => null,
    'confirmLabel' => __('messages.yes_delete'),
    'cancelLabel' => __('messages.cancel'),
    'confirmAction' => 'confirmDeleteRow()',
    'cancelAction' => 'cancelDeleteRow()',
    'danger' => true,
])

<dialog id="{{ $id }}" {{ $attributes->merge(['class' => 'modal modal-bottom sm:modal-middle backdrop-blur-sm']) }}>
    <div class="modal-box bg-white/95 dark:bg-gray-800/95 backdrop-blur-md border border-gray-200/50 dark:border-gray-700/50 shadow-2xl rounded-2xl p-6 text-gray-900 dark:text-gray-100">
        <div class="flex justify-between items-center pb-4 border-b border-gray-200/50 dark:border-gray-700/50 mb-4">
            <h3 class="font-bold text-lg text-gray-900 dark:text-white"
                @if($alpineTitle) x-text="{{ $alpineTitle }}" @endif>
                {{ $alpineTitle ? '' : $title }}
            </h3>
            <form method="dialog">
                <button type="submit" @click="{{ $cancelAction }}" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </form>
        </div>

        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed my-4"
           @if($alpineMessage) x-text="{{ $alpineMessage }}" @endif>
            {{ $alpineMessage ? '' : $message }}
        </p>

        <div class="modal-action border-t border-gray-200/50 dark:border-gray-700/50 pt-4 flex justify-end gap-3">
            <button type="button" @click="{{ $cancelAction }}"
                    class="btn btn-sm btn-ghost border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                {{ $cancelLabel }}
            </button>
            <button type="button" @click="{{ $confirmAction }}"
                    class="btn btn-sm {{ $danger ? 'btn-error' : 'bg-accent' }} border-transparent text-white hover:opacity-90 rounded-lg">
                @if ($danger)
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                @endif
                {{ $confirmLabel }}
            </button>
        </div>
    </div>
</dialog>
