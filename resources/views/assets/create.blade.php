<x-app-layout>
    <div class="py-4 sm:py-8">
        <div class="mx-auto max-w-4xl px-3 sm:px-6 lg:px-8">
            <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md rounded-xl border border-gray-200/50 dark:border-gray-700/50 shadow-sm">
                <div class="px-4 sm:px-6 py-4 border-b border-gray-200/50 dark:border-gray-700/50">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                        {{ __('messages.add_new_asset') ?? __('messages.add_new_asset') }}
                    </h2>
                </div>
                <div class="p-4 sm:p-6">
                    <form method="POST" action="{{ route('assets.store') }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf

                        <!-- Image Upload (Dual-Trigger Camera & Gallery) -->
                        <div x-data="{
                                previewUrl: null,
                                fileName: null,
                                compressing: false,
                                setPreview(file) {
                                    if (this.previewUrl && this.previewUrl.startsWith('blob:')) {
                                        URL.revokeObjectURL(this.previewUrl);
                                    }
                                    this.previewUrl = URL.createObjectURL(file);
                                },
                                syncMainInput(file) {
                                    const dataTransfer = new DataTransfer();
                                    dataTransfer.items.add(file);
                                    this.$refs.mainInput.files = dataTransfer.files;
                                },
                                async compressImage(file) {
                                    return new Promise((resolve, reject) => {
                                        const reader = new FileReader();
                                        reader.onerror = () => reject(new Error('Failed to read image file'));
                                        reader.onload = (event) => {
                                            const img = new Image();
                                            img.onerror = () => reject(new Error('Failed to decode image'));
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
                                                if (!ctx) {
                                                    return reject(new Error('Canvas context unavailable'));
                                                }
                                                ctx.drawImage(img, 0, 0, width, height);

                                                canvas.toBlob((blob) => {
                                                    if (!blob) {
                                                        return reject(new Error('Canvas compression failed'));
                                                    }
                                                    const jpgName = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                                                    const newFile = new File([blob], jpgName, {
                                                        type: 'image/jpeg',
                                                        lastModified: Date.now()
                                                    });
                                                    resolve(newFile);
                                                }, 'image/jpeg', 0.8);
                                            };
                                            img.src = event.target.result;
                                        };
                                        reader.readAsDataURL(file);
                                    });
                                },
                                async handleFileSelect(event) {
                                    const trigger = event.target;
                                    if (!trigger.files || !trigger.files.length) {
                                        return;
                                    }

                                    const originalFile = trigger.files[0];
                                    if (!originalFile.type.startsWith('image/')) {
                                        this.syncMainInput(originalFile);
                                        this.fileName = originalFile.name;
                                        this.setPreview(originalFile);
                                        trigger.value = '';
                                        return;
                                    }

                                    this.compressing = true;
                                    try {
                                        const compressedFile = await this.compressImage(originalFile);
                                        this.syncMainInput(compressedFile);
                                        this.fileName = compressedFile.name;
                                        this.setPreview(compressedFile);
                                    } catch (error) {
                                        console.error('Image compression error:', error);
                                        this.syncMainInput(originalFile);
                                        this.fileName = originalFile.name;
                                        this.setPreview(originalFile);
                                    } finally {
                                        this.compressing = false;
                                        trigger.value = '';
                                    }
                                }
                            }" class="space-y-3">
                            <x-input-label for="attachment" :value="__('messages.asset_image')" />

                            <!-- Hidden main file input submitted with the form & read by AI Scan -->
                            <input
                                id="attachment"
                                name="attachment"
                                type="file"
                                accept="image/*"
                                x-ref="mainInput"
                                class="sr-only"
                            />
                            <!-- Separate mobile triggers: Camera vs Gallery -->
                            <input
                                type="file"
                                accept="image/*"
                                capture="environment"
                                x-ref="cameraInput"
                                @change="handleFileSelect($event)"
                                class="sr-only"
                            />
                            <input
                                type="file"
                                accept="image/*"
                                x-ref="galleryInput"
                                @change="handleFileSelect($event)"
                                class="sr-only"
                            />

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <button
                                    type="button"
                                    @click="$refs.cameraInput.click()"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 min-h-[44px] rounded-lg border border-indigo-200 dark:border-indigo-700 bg-indigo-50 dark:bg-indigo-950/40 text-accent hover:bg-indigo-100 dark:hover:bg-indigo-900/50 text-sm font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-accent"
                                >
                                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span>Ambil Foto</span>
                                </button>

                                <button
                                    type="button"
                                    @click="$refs.galleryInput.click()"
                                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 min-h-[44px] rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900/50 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700/60 text-sm font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-accent"
                                >
                                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span>Pilih dari Galeri</span>
                                </button>
                            </div>

                            <p x-show="fileName" x-cloak class="text-xs text-gray-500 dark:text-gray-400 truncate" x-text="fileName"></p>

                            <div class="min-h-[1.25rem] mt-1 space-y-1">
                                <div x-show="compressing" class="text-sm text-accent flex items-center gap-2" x-cloak>
                                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ __('messages.optimizing_image') }}
                                </div>

                                <x-input-error :messages="$errors->get('attachment')" />
                            </div>

                            <!-- Preview -->
                            <div x-show="previewUrl" x-cloak class="min-h-[7rem]">
                                <p class="text-sm text-gray-600 dark:text-gray-300 mb-2">{{ __('messages.preview') }}</p>
                                <img :src="previewUrl" alt="Preview" class="h-24 w-auto object-cover rounded-md border border-gray-200 dark:border-gray-700 shadow-sm">
                            </div>

                            <!-- AI Scan -->
                            <div class="pt-1" x-data="{ scanning: false, error: null, success: null }">
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
                                <div class="min-h-[1.25rem] mt-2">
                                    <p x-show="error" class="text-sm text-red-600 dark:text-red-400" x-text="error" x-cloak></p>
                                    <p x-show="success" class="text-sm text-green-600 dark:text-green-400" x-text="success" x-cloak></p>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 italic">{{ __('messages.disclaimer_ai_scans_may_occasionally_be_inaccurate') }}</p>
                            </div>
                        </div>

                        <!-- Responsive Adaptive Grid Layout -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 items-start">

                            <!-- Tag (Alpine Combobox) -->
                            <div
                                x-data="{
                                    open: false,
                                    tag: @js(old('tag', '')),
                                    allTags: @js($existingTags->pluck('tag')->values()),
                                    get filteredTags() {
                                        const q = (this.tag || '').trim().toLowerCase();
                                        if (!q) return this.allTags.slice(0, 30);
                                        return this.allTags.filter(t => String(t).toLowerCase().includes(q)).slice(0, 30);
                                    },
                                    selectTag(val) {
                                        this.tag = val;
                                        this.open = false;
                                    }
                                }"
                                @click.outside="open = false"
                                class="relative"
                            >
                                <x-input-label for="tag" :value="__('messages.asset_tag')" />
                                <div class="relative mt-1">
                                    <input
                                        type="text"
                                        id="tag"
                                        name="tag"
                                        x-model="tag"
                                        @focus="open = true"
                                        @input="open = true"
                                        @keydown.escape="open = false"
                                        autocomplete="off"
                                        required
                                        placeholder="{{ __('messages.select_or_type_a_new_tag') }}"
                                        class="block w-full pr-10 text-base sm:text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-100 shadow-sm focus:ring-accent focus:border-accent placeholder-gray-400 dark:placeholder-gray-500"
                                    />
                                    <button
                                        type="button"
                                        @click="open = !open"
                                        aria-label="Toggle tag suggestions"
                                        class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                    >
                                        <svg class="w-4 h-4 transition-transform duration-150" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </div>

                                <ul
                                    x-show="open && filteredTags.length > 0"
                                    x-cloak
                                    role="listbox"
                                    class="absolute z-30 mt-1 w-full max-h-56 overflow-y-auto rounded-md bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-lg divide-y divide-gray-100 dark:divide-gray-700/50"
                                >
                                    <template x-for="item in filteredTags" :key="item">
                                        <li>
                                            <button
                                                type="button"
                                                @mousedown.prevent="selectTag(item)"
                                                @click="selectTag(item)"
                                                x-text="item"
                                                class="w-full text-left px-3 py-2.5 text-sm text-gray-800 dark:text-gray-100 hover:bg-indigo-50 dark:hover:bg-gray-700 active:bg-indigo-100 dark:active:bg-gray-600"
                                            ></button>
                                        </li>
                                    </template>
                                </ul>

                                <div class="min-h-[1.25rem] mt-1">
                                    <x-input-error :messages="$errors->get('tag')" />
                                </div>
                            </div>

                            <!-- Serial Number -->
                            <div>
                                <x-input-label for="serial_number" :value="__('messages.serial_number')" />
                                <x-text-input
                                    id="serial_number"
                                    name="serial_number"
                                    type="text"
                                    class="mt-1 block w-full text-base sm:text-sm"
                                    value="{{ old('serial_number') }}"
                                />
                                <div class="min-h-[1.25rem] mt-1">
                                    <x-input-error :messages="$errors->get('serial_number')" />
                                </div>
                            </div>

                            <!-- Name -->
                            <div>
                                <x-input-label for="name" :value="__('messages.asset_name')" />
                                <x-text-input
                                    id="name"
                                    name="name"
                                    type="text"
                                    class="mt-1 block w-full text-base sm:text-sm"
                                    required
                                    value="{{ old('name') }}"
                                />
                                <div class="min-h-[1.25rem] mt-1">
                                    <x-input-error :messages="$errors->get('name')" />
                                </div>
                            </div>

                            <!-- Model -->
                            <div>
                                <x-input-label for="model" :value="__('messages.model')" />
                                <x-text-input
                                    id="model"
                                    name="model"
                                    type="text"
                                    class="mt-1 block w-full text-base sm:text-sm"
                                    value="{{ old('model') }}"
                                />
                                <div class="min-h-[1.25rem] mt-1">
                                    <x-input-error :messages="$errors->get('model')" />
                                </div>
                            </div>

                            <!-- Category -->
                            <div>
                                <x-input-label for="category_id" :value="__('messages.category')" />
                                <select
                                    id="category_id"
                                    name="category_id"
                                    class="mt-1 block w-full text-base sm:text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-100 shadow-sm focus:ring-accent focus:border-accent"
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
                                <div class="min-h-[1.25rem] mt-1">
                                    <x-input-error :messages="$errors->get('category_id')" />
                                </div>
                            </div>

                            <!-- Purchase Date -->
                            <div>
                                <x-input-label for="purchase_date" :value="__('messages.purchase_date')" />
                                <x-text-input
                                    id="purchase_date"
                                    name="purchase_date"
                                    type="date"
                                    class="mt-1 block w-full text-base sm:text-sm"
                                    :value="old('purchase_date')"
                                />
                                <div class="min-h-[1.25rem] mt-1">
                                    <x-input-error :messages="$errors->get('purchase_date')" />
                                </div>
                            </div>

                            @if (Auth::user()->hasExecutiveOversight())
                                <!-- Departments -->
                                <div>
                                    <x-input-label for="department_id" :value="__('messages.department')" />
                                    <select
                                        id="department_id"
                                        name="department_id"
                                        class="mt-1 block w-full text-base sm:text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-100 shadow-sm focus:ring-accent focus:border-accent"
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
                                    <div class="min-h-[1.25rem] mt-1">
                                        <x-input-error :messages="$errors->get('department_id')" />
                                    </div>
                                </div>
                            @else
                                <!-- Departments -->
                                <div>
                                    <x-input-label for="department_id" :value="__('messages.department')" />
                                    <select
                                        id="department_id"
                                        name="department_id"
                                        disabled
                                        class="mt-1 block w-full text-base sm:text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 text-gray-500 dark:text-gray-400 shadow-sm focus:ring-accent focus:border-accent"
                                    >
                                        <option value="{{ Auth::user()->department?->id }}" selected>{{ Auth::user()->department?->name ?? '—' }}</option>
                                    </select>
                                    <input type="hidden" name="department_id" value="{{ Auth::user()->department?->id }}">
                                    <div class="min-h-[1.25rem] mt-1">
                                        <x-input-error :messages="$errors->get('department_id')" />
                                    </div>
                                </div>
                            @endif

                            {{-- Warranty --}}
                            <div>
                                <x-input-label for="warranty_duration" :value="__('messages.warranty_duration')" />
                                <select id="warranty_duration" name="warranty_duration"
                                    class="mt-1 block w-full text-base sm:text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-100 rounded-md shadow-sm focus:ring-accent focus:border-accent">
                                    <option value="none" {{ old('warranty_duration') == 'none' ? 'selected' : '' }}>{{ __('messages.none') }}</option>
                                    <option value="6m" {{ old('warranty_duration') == '6m' ? 'selected' : '' }}>{{ __('messages.6_months') }}</option>
                                    <option value="1y" {{ old('warranty_duration') == '1y' ? 'selected' : '' }}>{{ __('messages.1_year') }}</option>
                                    <option value="2y" {{ old('warranty_duration') == '2y' ? 'selected' : '' }}>{{ __('messages.2_years') }}</option>
                                    <option value="3y" {{ old('warranty_duration') == '3y' ? 'selected' : '' }}>{{ __('messages.3_years') }}</option>
                                </select>
                                <div class="min-h-[1.25rem] mt-1">
                                    <x-input-error :messages="$errors->get('warranty_duration')" />
                                </div>
                            </div>

                            <!-- Location (Alpine Combobox) -->
                            <div
                                x-data="{
                                    open: false,
                                    location: @js(old('location', '')),
                                    allLocations: @js($existingLocations->values()),
                                    get filteredLocations() {
                                        const q = (this.location || '').trim().toLowerCase();
                                        if (!q) return this.allLocations.slice(0, 30);
                                        return this.allLocations.filter(l => String(l).toLowerCase().includes(q)).slice(0, 30);
                                    },
                                    selectLocation(val) {
                                        this.location = val;
                                        this.open = false;
                                    }
                                }"
                                @click.outside="open = false"
                                class="relative"
                            >
                                <x-input-label for="location" :value="__('messages.locations')" />
                                <div class="relative mt-1">
                                    <input
                                        type="text"
                                        id="location"
                                        name="location"
                                        x-model="location"
                                        @focus="open = true"
                                        @input="open = true"
                                        @keydown.escape="open = false"
                                        autocomplete="off"
                                        placeholder="{{ __('messages.select_or_type_a_new_location') }}"
                                        class="block w-full pr-10 text-base sm:text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-100 shadow-sm focus:ring-accent focus:border-accent placeholder-gray-400 dark:placeholder-gray-500"
                                    />
                                    <button
                                        type="button"
                                        @click="open = !open"
                                        aria-label="Toggle location suggestions"
                                        class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                    >
                                        <svg class="w-4 h-4 transition-transform duration-150" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                </div>

                                <ul
                                    x-show="open && filteredLocations.length > 0"
                                    x-cloak
                                    role="listbox"
                                    class="absolute z-30 mt-1 w-full max-h-56 overflow-y-auto rounded-md bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-lg divide-y divide-gray-100 dark:divide-gray-700/50"
                                >
                                    <template x-for="item in filteredLocations" :key="item">
                                        <li>
                                            <button
                                                type="button"
                                                @mousedown.prevent="selectLocation(item)"
                                                @click="selectLocation(item)"
                                                x-text="item"
                                                class="w-full text-left px-3 py-2.5 text-sm text-gray-800 dark:text-gray-100 hover:bg-indigo-50 dark:hover:bg-gray-700 active:bg-indigo-100 dark:active:bg-gray-600"
                                            ></button>
                                        </li>
                                    </template>
                                </ul>

                                <div class="min-h-[1.25rem] mt-1">
                                    <x-input-error :messages="$errors->get('location')" />
                                </div>
                            </div>

                            <!-- Purchase Cost -->
                            <div>
                                <x-input-label for="purchase_cost" :value="__('messages.purchase_cost')" />
                                <x-text-input
                                    id="purchase_cost"
                                    name="purchase_cost"
                                    type="number"
                                    step="0.01"
                                    inputmode="decimal"
                                    class="mt-1 block w-full text-base sm:text-sm"
                                    value="{{ old('purchase_cost') }}"
                                />
                                <div class="min-h-[1.25rem] mt-1">
                                    <x-input-error :messages="$errors->get('purchase_cost')" />
                                </div>
                            </div>

                            <!-- Status -->
                            <div>
                                <x-input-label for="status" :value="__('messages.status')" />
                                <select
                                    id="status"
                                    name="status"
                                    class="mt-1 block w-full text-base sm:text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-100 shadow-sm focus:ring-accent focus:border-accent"
                                >
                                    <option value="in_service" {{ old('status') == 'in_service' ? 'selected' : '' }}>{{ __('messages.in_service') }}</option>
                                    <option value="out_of_service" {{ old('status') == 'out_of_service' ? 'selected' : '' }}>{{ __('messages.out_of_service') }}</option>
                                    <option value="disposed" {{ old('status') == 'disposed' ? 'selected' : '' }}>{{ __('messages.disposed') }}</option>
                                </select>
                                <div class="min-h-[1.25rem] mt-1">
                                    <x-input-error :messages="$errors->get('status')" />
                                </div>
                            </div>

                            <!-- Vendor -->
                            <div>
                                <x-input-label for="vendor" :value="__('messages.vendor')" />
                                <x-text-input
                                    id="vendor"
                                    name="vendor"
                                    type="text"
                                    class="mt-1 block w-full text-base sm:text-sm"
                                    value="{{ old('vendor') }}"
                                />
                                <div class="min-h-[1.25rem] mt-1">
                                    <x-input-error :messages="$errors->get('vendor')" />
                                </div>
                            </div>
                        </div>

                        {{-- Remarks --}}
                        <div x-data="{ count: {{ strlen(old('remarks', $asset->remarks ?? '')) }} }">
                            <x-input-label for="remarks" :value="__('messages.remarks')" />

                            <textarea
                                id="remarks"
                                name="remarks"
                                maxlength="120"
                                rows="3"
                                class="mt-1 block w-full text-base sm:text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-100 rounded-md shadow-sm focus:ring-accent focus:border-accent"
                                placeholder="{{ __('messages.add_a_short_note_max_120_chars') }}"
                                x-on:input="count = $event.target.value.length"
                            >{{ old('remarks') }}</textarea>

                            <div class="flex justify-between mt-1 text-sm text-gray-500 dark:text-gray-400">
                                <span>{{ __('messages.max_120_characters') }}</span>
                                <span x-text="count + '/120'"></span>
                            </div>

                            <div class="min-h-[1.25rem] mt-1">
                                <x-input-error :messages="$errors->get('remarks')" />
                            </div>
                        </div>

                        <div class="pt-4 border-t border-gray-200/50 dark:border-gray-700/50 flex justify-between items-center gap-3">
                            <!-- Back Button -->
                            <x-secondary-button onclick="window.history.back()">
                                <x-heroicon-s-arrow-left class="w-4 h-4 mr-2" />
                                {{ __('messages.back') }}
                            </x-secondary-button>

                            <!-- Submit Button -->
                            <x-primary-button>
                                <x-heroicon-s-bookmark class="w-4 h-4 mr-2" />
                                {{ __('messages.save_asset') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
