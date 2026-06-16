<style>
    .steps .step-primary:before,
    .steps .step-primary:after {
        background-color: var(--property-accent, #4f46e5) !important;
        color: #ffffff !important;
    }
    
    /* Inactive steps label readability */
    .steps .step {
        color: #9ca3af !important;
    }
    .dark .steps .step {
        color: #d1d5db !important;
    }
    
    /* Active steps label readability */
    .steps .step-primary {
        color: #374151 !important;
        font-weight: 600;
    }
    .dark .steps .step-primary {
        color: #ffffff !important;
        font-weight: 600;
    }
</style>
<div class="card bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 p-6 rounded-xl shadow-md border border-gray-200/50 dark:border-gray-700/50 mb-8">
    <ul class="steps steps-horizontal w-full">
        <li class="step {{ isset($currentStep) && $currentStep >= 1 ? 'step-primary' : '' }}">
            @if(isset($currentStep) && $currentStep >= 1)
                <a href="{{ route('assets.index', ['import' => 1]) }}" class="hover:underline hover:text-accent font-semibold transition-colors">
                    {{ __('assets.step_upload') }}
                </a>
            @else
                {{ __('assets.step_upload') }}
            @endif
        </li>
        <li class="step {{ isset($currentStep) && $currentStep >= 2 ? 'step-primary' : '' }}">
            @if(isset($currentStep) && $currentStep >= 2)
                <a href="{{ route('assets.import-mapping') }}" class="hover:underline hover:text-accent font-semibold transition-colors">
                    {{ __('assets.step_mapping') }}
                </a>
            @else
                {{ __('assets.step_mapping') }}
            @endif
        </li>
        <li class="step {{ isset($currentStep) && $currentStep >= 3 ? 'step-primary' : '' }}">
            @if(isset($currentStep) && $currentStep >= 3)
                <a href="{{ route('assets.import-rapid-add') }}" class="hover:underline hover:text-accent font-semibold transition-colors">
                    {{ __('assets.step_rapid_add') }}
                </a>
            @else
                {{ __('assets.step_rapid_add') }}
            @endif
        </li>
        <li class="step {{ isset($currentStep) && $currentStep >= 4 ? 'step-primary' : '' }}">
            @if(isset($currentStep) && $currentStep >= 4)
                <a href="{{ route('assets.import-review') }}" class="hover:underline hover:text-accent font-semibold transition-colors">
                    {{ __('assets.step_review') }}
                </a>
            @else
                {{ __('assets.step_review') }}
            @endif
        </li>
    </ul>
</div>