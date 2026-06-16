<div x-data="{ openAssetModal: new URLSearchParams(window.location.search).has('import') || new URLSearchParams(window.location.search).get('action') === 'add_asset' || new URLSearchParams(window.location.search).get('open_modal') === 'true' || new URLSearchParams(window.location.search).has('open_modal') }" @open-add-asset-modal.window="openAssetModal = true">
    <!-- The actual modal body -->
    <template x-teleport="body">
        <div x-show="openAssetModal" 
             x-cloak
             class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/40 dark:bg-gray-900/60 backdrop-blur-sm p-4">
            
            <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md border border-gray-200/50 dark:border-gray-700/50 rounded-xl shadow-2xl w-full max-w-lg overflow-hidden" 
                 @click.outside="openAssetModal = false"
                 x-transition:enter="transition ease-out duration-300 transform"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200 transform"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95">
                
                <div class="px-6 pt-5 pb-4 border-b border-gray-200/50 dark:border-gray-700/50 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ __('assets.add_asset_options') }}
                    </h2>
                    <button @click="openAssetModal = false" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <!-- Option 1: Single Add -->
                    <a href="{{ route('assets.create') }}" 
                       class="flex items-start gap-4 p-4 rounded-xl border border-gray-200/50 dark:border-gray-700/50 bg-gray-50/50 dark:bg-gray-900/50 hover:bg-white dark:hover:bg-gray-800 transition-all shadow-sm hover:shadow-md group">
                        <div class="p-3 bg-accent/10 dark:bg-accent/20 rounded-lg text-accent group-hover:bg-accent group-hover:text-white transition-colors">
                            <x-heroicon-o-document-plus class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100 text-sm mb-1">{{ __('assets.single_add') }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('assets.single_add_desc') }}</p>
                        </div>
                    </a>

                    <!-- Option 2: Bulk Add (Manual) -->
                    <a href="{{ route('assets.bulk-manual') }}" class="flex items-start gap-4 p-4 rounded-xl border border-gray-200/50 dark:border-gray-700/50 bg-gray-50/50 dark:bg-gray-900/50 hover:bg-white dark:hover:bg-gray-800 transition-all shadow-sm hover:shadow-md group">
                        <div class="p-3 bg-blue-500/10 dark:bg-blue-500/20 rounded-lg text-blue-500 group-hover:bg-blue-500 group-hover:text-white transition-colors">
                            <x-heroicon-o-table-cells class="w-6 h-6" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100 text-sm mb-1">{{ __('assets.bulk_add_manual') }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('assets.bulk_add_manual_desc') }}</p>
                        </div>
                    </a>

                    <!-- Option 3: Import from File (XLSX/CSV) -->
                    <div x-data="{ uploadFormOpen: false }" class="border border-gray-200/50 dark:border-gray-700/50 rounded-xl overflow-hidden shadow-sm transition-all bg-gray-50/50 dark:bg-gray-900/50 hover:shadow-md">
                        <button type="button" @click="uploadFormOpen = !uploadFormOpen" class="w-full flex items-start gap-4 p-4 hover:bg-white dark:hover:bg-gray-800 transition-colors group text-left">
                            <div class="p-3 bg-emerald-500/10 dark:bg-emerald-500/20 rounded-lg text-emerald-500 group-hover:bg-emerald-500 group-hover:text-white transition-colors">
                                <x-heroicon-o-document-arrow-up class="w-6 h-6" />
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-900 dark:text-gray-100 text-sm mb-1">{{ __('assets.smart_import') }}</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('assets.smart_import_desc') }}</p>
                            </div>
                            <div class="text-gray-400">
                                <svg class="w-5 h-5 transition-transform duration-200" :class="uploadFormOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                        </button>
                        
                        <div x-show="uploadFormOpen" x-collapse>
                             <div class="p-4 pt-0 border-t border-gray-200/50 dark:border-gray-700/50 bg-white/50 dark:bg-gray-800/50"
                                  x-data="importUploader()"
                                  x-ref="uploaderRoot">
                                 
                                 <div class="mt-4 mb-4">
                                     <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('assets.upload_prompt') }}</label>
                                     <input type="file" x-ref="fileInput" accept=".csv,.xlsx" required
                                         @change="onFileSelect($event)"
                                         class="block w-full text-sm text-gray-700 dark:text-gray-200 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:uppercase file:tracking-wider file:bg-emerald-100 file:dark:bg-emerald-900/40 file:text-emerald-700 file:dark:text-emerald-400 hover:file:bg-emerald-200 dark:hover:file:bg-emerald-900/60 file:cursor-pointer file:transition-colors border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900/50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" />
                                     
                                     <!-- Large file warning -->
                                     <div x-show="largeFileWarning" x-cloak
                                          class="mt-2 text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 p-2 rounded border border-amber-200 dark:border-amber-800/50 flex items-center gap-2">
                                         <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                         <span x-text="largeFileWarning"></span>
                                     </div>
                                     
                                     <!-- Error message -->
                                     <div x-show="errorMessage" x-cloak
                                          class="mt-2 text-sm text-red-600 dark:text-red-400 font-semibold bg-red-50 dark:bg-red-900/20 p-2 rounded border border-red-200 dark:border-red-800/50"
                                          x-text="errorMessage"></div>
                                 </div>
 
                                 <div class="flex justify-end gap-2">
                                     <button type="button" 
                                             @click="uploadFile()"
                                             :disabled="isParsing || !hasFile"
                                             class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold uppercase tracking-widest rounded-lg transition-all duration-200 shadow-sm hover:shadow-md disabled:opacity-50 disabled:cursor-not-allowed">
                                         <x-heroicon-o-cloud-arrow-up class="w-4 h-4" x-show="!isParsing" />
                                         <svg x-show="isParsing" x-cloak class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                             <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                             <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                         </svg>
                                         <span x-show="!isParsing">{{ __('assets.upload_and_scan') }}</span>
                                        <span x-show="isParsing" x-cloak>{{ __('assets.scanning_data') }}</span>
                                    </button>
                                </div>

                                <!-- Full-screen loading overlay -->
                                <template x-teleport="body">
                                    <div x-show="isParsing" x-cloak
                                         class="fixed inset-0 z-[200] flex flex-col items-center justify-center bg-gray-900/60 backdrop-blur-md"
                                         x-transition:enter="transition ease-out duration-300"
                                         x-transition:enter-start="opacity-0"
                                         x-transition:enter-end="opacity-100"
                                         x-transition:leave="transition ease-in duration-200"
                                         x-transition:leave-start="opacity-100"
                                         x-transition:leave-end="opacity-0">
                                        <div class="bg-white/10 dark:bg-gray-800/40 backdrop-blur-xl border border-white/20 rounded-2xl shadow-2xl p-8 text-center max-w-sm">
                                            <svg class="animate-spin h-12 w-12 text-emerald-400 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <p class="text-white text-lg font-semibold mb-2">{{ __('assets.scanning_data') }}</p>
                                            <p x-show="largeFileWarning" class="text-amber-300 text-sm" x-text="largeFileWarning"></p>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
function importUploader() {
    return {
        isParsing: false,
        hasFile: false,
        errorMessage: '',
        largeFileWarning: '',
        
        onFileSelect(event) {
            const file = event.target.files[0];
            this.hasFile = !!file;
            this.errorMessage = '';
            this.largeFileWarning = '';
            
            if (file && file.size > 2 * 1024 * 1024) { // > 2MB
                this.largeFileWarning = @json(__('assets.large_file_warning'));
            }
        },
        
        async uploadFile() {
            const fileInput = this.$refs.fileInput;
            if (!fileInput.files[0]) return;
            
            this.isParsing = true;
            this.errorMessage = '';
            
            const formData = new FormData();
            formData.append('import_file', fileInput.files[0]);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            
            try {
                const response = await fetch('{{ route("assets.import-parse") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
                
                const text = await response.text();
                
                if (!response.ok) {
                    if (response.status === 413) {
                        throw new Error('Payload Too Large: File exceeds server limits.');
                    } else if (response.status === 504) {
                        throw new Error('Server Timeout: File is too large or processing took too long.');
                    } else if (response.status >= 500) {
                        throw new Error('Server Error: ' + response.statusText);
                    }
                }
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseErr) {
                    console.error('Raw response:', text);
                    throw new Error('Invalid server response. Please check server logs.');
                }
                
                if (response.ok && data.success) {
                    window.location.href = data.redirect_url;
                } else {
                    this.errorMessage = data.message || @json(__('assets.unknown_error'));
                    this.isParsing = false;
                }
            } catch (err) {
                this.errorMessage = err.message || @json(__('assets.network_error'));
                this.isParsing = false;
            }
        }
    };
}
</script>
