<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('assets.rapid_add_title') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @include('assets.import.partials.stepper', ['currentStep' => 3])

            <div class="bg-white/90 dark:bg-gray-800/90 backdrop-blur-md shadow-xl sm:rounded-2xl border border-gray-200/50 dark:border-gray-700/50 overflow-hidden">
                <form action="{{ route('assets.import-rapid-add.store') }}" method="POST"
                      x-data="{
                          allCats: {{ json_encode($missingCategories ?? []) }},
                          allDepts: {{ json_encode($missingDepartments ?? []) }},
                          categories: {{ json_encode($missingCategories ?? []) }},
                          departments: {{ json_encode($missingDepartments ?? []) }},
                          
                          get totalSelected() {
                              return this.categories.length + this.departments.length;
                          },
                          get allCatsChecked() {
                              return this.allCats.length > 0 && this.categories.length === this.allCats.length;
                          },
                          get allDeptsChecked() {
                              return this.allDepts.length > 0 && this.departments.length === this.allDepts.length;
                          },
                          toggleAllCats() {
                              if (this.allCatsChecked) {
                                  this.categories = [];
                              } else {
                                  this.categories = [...this.allCats];
                              }
                          },
                          toggleAllDepts() {
                              if (this.allDeptsChecked) {
                                  this.departments = [];
                              } else {
                                  this.departments = [...this.allDepts];
                              }
                          }
                      }">
                    @csrf
                    
                    <div class="p-6 border-b border-gray-200/50 dark:border-gray-700/50 bg-gray-50/50 dark:bg-gray-900/50 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('assets.unregistered_entities_detected') }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __('assets.rapid_add_description') }}</p>
                        </div>
                    </div>

                    <div class="p-6 space-y-8">
                        @if (!empty($missingCategories))
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 flex items-center mb-0">
                                        <x-heroicon-o-tag class="w-5 h-5 mr-2 text-accent" />
                                        {{ __('assets.missing_categories') }}
                                    </h4>
                                    <label class="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400 cursor-pointer">
                                        <input type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-accent focus:ring-accent"
                                               x-bind:checked="allCatsChecked" 
                                               @change="toggleAllCats">
                                        <span>{{ __('assets.select_all') ?? 'Select All' }}</span>
                                    </label>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                    @foreach($missingCategories as $index => $category)
                                        <label class="relative flex items-center p-4 cursor-pointer rounded-xl border transition-all shadow-sm"
                                               :class="categories.includes('{{ $category }}') ? 'bg-accent/5 border-accent ring-1 ring-accent' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700'">
                                            <input type="checkbox" name="categories[]" value="{{ $category }}" x-model="categories" class="sr-only">
                                            <div class="w-5 h-5 flex items-center justify-center border-2 rounded mr-3 transition-colors"
                                                 :class="categories.includes('{{ $category }}') ? 'bg-accent border-accent' : 'border-gray-300 dark:border-gray-600'">
                                                <x-heroicon-s-check x-show="categories.includes('{{ $category }}')" class="w-4 h-4 text-white" />
                                            </div>
                                            <span class="text-sm font-medium" :class="categories.includes('{{ $category }}') ? 'text-gray-900 dark:text-white' : 'text-gray-700 dark:text-gray-300'">{{ $category }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if (!empty($missingDepartments))
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="text-md font-semibold text-gray-800 dark:text-gray-200 flex items-center mb-0">
                                        <x-heroicon-o-building-office class="w-5 h-5 mr-2 text-accent" />
                                        {{ __('assets.missing_departments') }}
                                    </h4>
                                    <label class="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400 cursor-pointer">
                                        <input type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-accent focus:ring-accent"
                                               x-bind:checked="allDeptsChecked" 
                                               @change="toggleAllDepts">
                                        <span>{{ __('assets.select_all') ?? 'Select All' }}</span>
                                    </label>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                    @foreach($missingDepartments as $index => $department)
                                        <label class="relative flex items-center p-4 cursor-pointer rounded-xl border transition-all shadow-sm"
                                               :class="departments.includes('{{ $department }}') ? 'bg-accent/5 border-accent ring-1 ring-accent' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700'">
                                            <input type="checkbox" name="departments[]" value="{{ $department }}" x-model="departments" class="sr-only">
                                            <div class="w-5 h-5 flex items-center justify-center border-2 rounded mr-3 transition-colors"
                                                 :class="departments.includes('{{ $department }}') ? 'bg-accent border-accent' : 'border-gray-300 dark:border-gray-600'">
                                                <x-heroicon-s-check x-show="departments.includes('{{ $department }}')" class="w-4 h-4 text-white" />
                                            </div>
                                            <span class="text-sm font-medium" :class="departments.includes('{{ $department }}') ? 'text-gray-900 dark:text-white' : 'text-gray-700 dark:text-gray-300'">{{ $department }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                    
                    <div class="p-6 border-t border-gray-200/50 dark:border-gray-700/50 bg-gray-50/50 dark:bg-gray-900/50 flex justify-between items-center">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('assets.unselected_items_warning') }}</p>
                        
                        <button type="submit" 
                                :class="totalSelected > 0 ? 'bg-accent focus:ring-accent' : 'bg-gray-600 hover:bg-gray-500 focus:ring-gray-600'"
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-offset-2 transition-all shadow-sm">
                            <span x-show="totalSelected > 0" class="flex items-center">
                                <x-heroicon-s-check class="w-4 h-4 mr-2" />
                                {{ __('assets.create_and_continue') }}
                            </span>
                            <span x-show="totalSelected === 0" class="flex items-center" style="display: none;">
                                {{ __('assets.skip_and_continue') }}
                                <x-heroicon-s-arrow-right class="w-4 h-4 ml-2" />
                            </span>
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</x-app-layout>
