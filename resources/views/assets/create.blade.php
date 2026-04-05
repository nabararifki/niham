<x-app-layout>
<div class="py-4 sm:py-8">
        <div class="mx-auto max-w-4xl px-3 sm:px-6 lg:px-8">
            <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md rounded-xl border border-gray-200/50 dark:border-gray-700/50 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200/50 dark:border-gray-700/50">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        {{ __('messages.add_new_asset') ?? __('messages.add_new_asset') }}
                    </h2>
                </div>
                <div class="p-6">
                <form method="POST" action="{{ route('assets.store') }}" enctype="multipart/form-data">
                    @csrf

                    <!-- Image Upload -->
                    <div x-data="{
                            previewUrl: null,
                            compressing: false,
                            async compressImage(file) {
                                return new Promise((resolve) => {
                                    const reader = new FileReader();
                                    reader.readAsDataURL(file);
                                    reader.onload = (event) => {
                                        const img = new Image();
                                        img.src = event.target.result;
                                        img.onload = () => {
                                            const canvas = document.createElement('canvas');
                                            let width = img.width;
                                            let height = img.height;
                                            const maxDim = 1920;

                                            if (width > maxDim || height > maxDim) {
                                                if (width > height) {
                                                    height = Math.round(height * (maxDim / width));
                                                    width = maxDim;
                                                } else {
                                                    width = Math.round(width * (maxDim / height));
                                                    height = maxDim;
                                                }
                                            }

                                            canvas.width = width;
                                            canvas.height = height;
                                            const ctx = canvas.getContext('2d');
                                            ctx.drawImage(img, 0, 0, width, height);

                                            canvas.toBlob((blob) => {
                                                const newFile = new File([blob], file.name, {
                                                    type: 'image/jpeg',
                                                    lastModified: Date.now()
                                                });
                                                resolve(newFile);
                                            }, 'image/jpeg', 0.8);
                                        };
                                    };
                                });
                            },
                            async handleFileSelect(event) {
                                const input = event.target;
                                if (!input.files.length) {
                                    this.previewUrl = null;
                                    return;
                                }
                                
                                const originalFile = input.files[0];
                                if (!originalFile.type.startsWith('image/')) {
                                    this.previewUrl = URL.createObjectURL(originalFile);
                                    return;
                                }

                                this.compressing = true;
                                try {
                                    const compressedFile = await this.compressImage(originalFile);
                                    
                                    const dataTransfer = new DataTransfer();
                                    dataTransfer.items.add(compressedFile);
                                    input.files = dataTransfer.files;
                                    
                                    this.previewUrl = URL.createObjectURL(compressedFile);
                                } catch (error) {
                                    console.error('Image compression error:', error);
                                    this.previewUrl = URL.createObjectURL(originalFile);
                                } finally {
                                    this.compressing = false;
                                }
                            }
                        }" class="m-8">
                        <x-input-label for="attachment" :value="__('messages.asset_image')" />

                        <input
                            id="attachment"
                            name="attachment"
                            type="file"
                            accept="image/*"
                            @change="handleFileSelect($event)"
                            class="mt-1 block w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4
                                file:rounded-md file:border-0 file:text-sm file:font-semibold
                                file:bg-indigo-50 file:text-accent hover:file:bg-indigo-100"
                        />

                        <x-input-error :messages="$errors->get('attachment')" class="mt-2" />

                        <div x-show="compressing" class="mt-2 text-sm text-accent flex items-center gap-2" x-cloak>
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ __('messages.optimizing_image') }}
                        </div>

                        <!-- Preview -->
                        <template x-if="previewUrl">
                            <div class="mt-4">
                                <p class="text-sm text-gray-600 mb-2">{{ __('messages.preview') }}</p>
                                <img :src="previewUrl" alt="Preview" class="max-h-24 rounded-md border border-gray-200 shadow-sm">
                            </div>
                        </template>

                        <!-- AI Scan -->
                        <div class="mt-4" x-data="{ scanning: false, error: null, success: null }">
                            <x-primary-button type="button" 
                                @click="
                                    if(document.getElementById('attachment').files.length === 0) { 
                                        error = 'Please select an image first'; 
                                        success = null;
                                        return; 
                                    }
                                    error = null;
                                    success = null;
                                    scanning = true;
                                    let formData = new FormData();
                                    formData.append('image', document.getElementById('attachment').files[0]);
                                    formData.append('_token', '{{ csrf_token() }}');
                                    
                                    fetch('{{ route('assets.ocr-scan') }}', {
                                        method: 'POST',
                                        body: formData
                                    })
                                    .then(async res => {
                                        if (!res.ok) {
                                            const errText = await res.text();
                                            try {
                                                const errJson = JSON.parse(errText);
                                                throw new Error(errJson.error || 'Server error: ' + res.status);
                                            } catch (e) {
                                                throw new Error('Server error: ' + res.status);
                                            }
                                        }
                                        return res.json();
                                    })
                                    .then(data => {
                                        scanning = false;
                                        if(data.error) throw new Error(data.error);
                                        
                                        if(data.extracted.asset_name) document.getElementById('name').value = data.extracted.asset_name;
                                        if(data.extracted.serial_number) document.getElementById('serial_number').value = data.extracted.serial_number;
                                        if(data.extracted.brand) document.getElementById('vendor').value = data.extracted.brand;
                                        
                                        success = 'Scan completed successfully! Please verify the extracted details.';
                                    })
                                    .catch(err => {
                                        scanning = false;
                                        error = err.message || 'Failed to scan image';
                                    });
                                "
                                x-bind:disabled="scanning"
                            >
                                <span x-show="!scanning">{{ __('messages.ai_scan_details') }}</span>
                                <span x-show="scanning" x-cloak>{{ __('messages.scanning') }}</span>
                            </x-primary-button>
                            <p x-show="error" class="text-sm text-red-600 mt-2" x-text="error" x-cloak></p>
                            <p x-show="success" class="text-sm text-green-600 mt-2" x-text="success" x-cloak></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 italic">{{ __('messages.disclaimer_ai_scans_may_occasionally_be_inaccurate') }}</p>
                        </div>
                    </div>

                    <!-- Responsive Two-Column Layout -->
                    <div class="m-8 grid grid-cols-2 gap-1 justify-evenly">

                        <!-- Left Column -->
                        <div class="col-span-2 md:col-span-1">
                            <!-- Tag -->
                            <div x-data="{ tag: '' }">
                                <x-input-label for="tag" :value="__('messages.asset_tag')" />
                                <input
                                    list="tag-options"
                                    x-model="tag"
                                    name="tag"
                                    id="tag"
                                    autocomplete="off"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-100 shadow-sm focus:ring-accent focus:border-accent"
                                    placeholder="{{ __('messages.select_or_type_a_new_tag') }}"
                                    required
                                    value="{{ old('tag') }}"
                                />
                                <datalist id="tag-options">
                                    @foreach ($existingTags as $existingTag)
                                        <option value="{{ $existingTag->tag }}"></option>
                                    @endforeach
                                </datalist>
                                <x-input-error :messages="$errors->get('tag')" class="mt-2" />
                            </div>

                            <!-- Name -->
                            <div>
                                <x-input-label for="name" :value="__('messages.asset_name')" />
                                <x-text-input
                                    id="name"
                                    name="name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    required
                                    value="{{ old('name') }}"
                                />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <!-- Category -->
                            <div>
                                <x-input-label for="category_id" :value="__('messages.category')" />
                                <select
                                    id="category_id"
                                    name="category_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-100 shadow-sm focus:ring-accent focus:border-accent"
                                    required
                                >
                                    @foreach ($categories as $category)
                                        @if (old('category_id') == $category->id)
                                            <option value="{{ $category->id }}" selected>
                                                {{ $category->name }}{{ Auth::user()->isSuperAdmin() && $category->property ? ' - ' . $category->property->name : '' }}
                                            </option>
                                        @else
                                            <option value="{{ $category->id }}">
                                                {{ $category->name }}{{ Auth::user()->isSuperAdmin() && $category->property ? ' - ' . $category->property->name : '' }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('category_id')" class="mt-2" />
                            </div>

                            @if (Auth::user()->hasExecutiveOversight())
                                <!-- Departments -->
                                <div>
                                    <x-input-label for="department_id" :value="__('messages.department')" />
                                    <select
                                        id="department_id"
                                        name="department_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-100 shadow-sm focus:ring-accent focus:border-accent"
                                    >
                                        <option value="">—</option>
                                        @foreach ($departments as $department)
                                            @if (old('department_id') == $department->id)
                                                <option value="{{ $department->id }}" selected>
                                                    {{ $department->name }}{{ Auth::user()->isSuperAdmin() && $department->property ? ' - ' . $department->property->name : '' }}
                                                </option>
                                            @else
                                                <option value="{{ $department->id }}">
                                                    {{ $department->name }}{{ Auth::user()->isSuperAdmin() && $department->property ? ' - ' . $department->property->name : '' }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('department_id')" class="mt-2" />
                                </div>
                            @else
                                <!-- Departments -->
                                <div>
                                    <x-input-label for="department_id" :value="__('messages.department')" />
                                    <select
                                        id="department_id"
                                        name="department_id"
                                        disabled
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 text-gray-500 dark:text-gray-400 shadow-sm focus:ring-accent focus:border-accent"
                                    >
                                        <option value="{{ Auth::user()->department->id }}" selected>{{ Auth::user()->department->name }}</option>
                                    </select>
                                    <input type="hidden" name="department_id" value="{{ Auth::user()->department->id }}">
                                    <x-input-error :messages="$errors->get('department_id')" class="mt-2" />
                                </div>
                            @endif

                            <!-- Location -->
                            <div>
                                <x-input-label for="location_id" :value="__('messages.locations')" />
                                <select
                                    id="location_id"
                                    name="location_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-100 shadow-sm focus:ring-accent focus:border-accent"
                                >
                                    <option value="">— {{ __('messages.none') }} —</option>
                                    @foreach ($locations as $location)
                                        @if (old('location_id') == $location->id)
                                            <option value="{{ $location->id }}" selected>
                                                {{ $location->name }}{{ Auth::user()->isSuperAdmin() && $location->property ? ' - ' . $location->property->name : '' }}
                                            </option>
                                        @else
                                            <option value="{{ $location->id }}">
                                                {{ $location->name }}{{ Auth::user()->isSuperAdmin() && $location->property ? ' - ' . $location->property->name : '' }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('location_id')" class="mt-2" />
                            </div>

                            <!-- Status -->
                            <div>
                                <x-input-label for="status" :value="__('messages.status')" />
                                <select
                                    id="status"
                                    name="status"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-100 shadow-sm focus:ring-accent focus:border-accent"
                                >
                                    <option value="in_service" {{ old('status') == 'in_service' ? 'selected' : '' }}>{{ __('messages.in_service') }}</option>
                                    <option value="out_of_service" {{ old('status') == 'out_of_service' ? 'selected' : '' }}>{{ __('messages.out_of_service') }}</option>
                                    <option value="disposed" {{ old('status') == 'disposed' ? 'selected' : '' }}>{{ __('messages.disposed') }}</option>
                                </select>
                                <x-input-error :messages="$errors->get('status')" class="mt-2" />
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="col-span-2 md:col-span-1">

                            <!-- Serial Number -->
                            <div>
                                <x-input-label for="serial_number" :value="__('messages.serial_number')" />
                                <x-text-input
                                    id="serial_number"
                                    name="serial_number"
                                    type="text"
                                    class="mt-1 block w-full"
                                    value="{{ old('serial_number') }}"
                                />
                                <x-input-error :messages="$errors->get('serial_number')" class="mt-2" />
                            </div>

                            <!-- Purchase Date -->
                            <div>
                                <x-input-label for="purchase_date" :value="__('messages.purchase_date')" />
                                <x-text-input
                                    id="purchase_date"
                                    name="purchase_date"
                                    type="date"
                                    class="mt-1 block w-full"
                                    :value="old('purchase_date')"
                                />
                                <x-input-error :messages="$errors->get('purchase_date')" class="mt-2" />
                            </div>

                            {{-- Warranty --}}
                            <div>
                                <x-input-label for="warranty_duration" :value="__('messages.warranty_duration')" />
                                <select id="warranty_duration" name="warranty_duration"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-100 rounded-md shadow-sm">
                                    <option value="none" {{ old('warranty_duration') == 'none' ? 'selected' : '' }}>{{ __('messages.none') }}</option>
                                    <option value="6m" {{ old('warranty_duration') == '6m' ? 'selected' : '' }}>{{ __('messages.6_months') }}</option>
                                    <option value="1y" {{ old('warranty_duration') == '1y' ? 'selected' : '' }}>{{ __('messages.1_year') }}</option>
                                    <option value="2y" {{ old('warranty_duration') == '2y' ? 'selected' : '' }}>{{ __('messages.2_years') }}</option>
                                    <option value="3y" {{ old('warranty_duration') == '3y' ? 'selected' : '' }}>{{ __('messages.3_years') }}</option>
                                </select>
                                <x-input-error :messages="$errors->get('warranty_duration')" class="mt-2" />
                            </div>


                            <!-- Purchase Cost -->
                            <div>
                                <x-input-label for="purchase_cost" :value="__('messages.purchase_cost')" />
                                <x-text-input
                                    id="purchase_cost"
                                    name="purchase_cost"
                                    type="number"
                                    step="0.01"
                                    class="mt-1 block w-full"
                                    value="{{ old('purchase_cost') }}"
                                />
                                <x-input-error :messages="$errors->get('purchase_cost')" class="mt-2" />
                            </div>

                            <!-- Vendor -->
                            <div>
                                <x-input-label for="vendor" :value="__('messages.vendor')" />
                                <x-text-input
                                    id="vendor"
                                    name="vendor"
                                    type="text"
                                    class="mt-1 block w-full"
                                    value="{{ old('vendor') }}"
                                />
                                <x-input-error :messages="$errors->get('vendor')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    {{-- Remarks --}}
                            <div 
                                x-data="{ count: {{ strlen(old('remarks', $asset->remarks ?? '')) }} }"
                                class="m-8"
                            >
                                <x-input-label for="remarks" :value="__('messages.remarks')" />

                                <textarea
                                    id="remarks"
                                    name="remarks"
                                    maxlength="120"
                                    rows="3"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-100 rounded-md shadow-sm"
                                    placeholder="{{ __('messages.add_a_short_note_max_120_chars') }}"
                                    x-on:input="count = $event.target.value.length"
                                >{{ old('remarks') }}</textarea>

                                <div class="flex justify-between mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    <span>{{ __('messages.max_120_characters') }}</span>
                                    <span x-text="count + '/120'"></span>
                                </div>

                                <x-input-error :messages="$errors->get('remarks')" class="mt-2" />
                            </div>

                    <div class="mt-6 flex justify-between items-center">
                        <!-- Back Button -->
                        <div class="mt-6 flex justify-start">
                            <x-secondary-button onclick="window.history.back()">
                                <x-heroicon-s-arrow-left class="w-4 h-4 mr-2" />
                                {{ __('messages.back') }}
                            </x-secondary-button>

                        </div>
                        <!-- Submit Button -->
                        <div class="mt-6 flex justify-end">
                            <x-primary-button>
                                <x-heroicon-s-bookmark class="w-4 h-4 mr-2" />
                                {{ __('messages.save_asset') }}
                            </x-primary-button>
                        </div>
                    </div>
                </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
