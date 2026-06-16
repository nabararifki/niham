<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\Paginator;
use App\Models\Property;
use App\Models\Asset;
use App\Observers\AssetObserver;
use Illuminate\Database\Eloquent\Model;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        // Use Tailwind pagination globally (matches the app's Tailwind design system)
        Paginator::defaultView('pagination::tailwind');

        Asset::observe(AssetObserver::class);


        View::composer('*', function ($view) {
            $activeProperty = null;

            if (Auth::check()) {
                $user = Auth::user();
                if ($user->isSuperAdmin()) {
                    $activeId = session('active_property_id');
                    if ($activeId) {
                        $activeProperty = Property::find($activeId);
                    }
                } else {
                    // Explicit eager-load required: shouldBeStrict() throws
                    // LazyLoadingViolationException on bare $user->property access
                    // when the relation was not already loaded by Auth::user().
                    if (! $user->relationLoaded('property')) {
                        $user->load('property');
                    }
                    $activeProperty = $user->property;
                }
            }

            $view->with('activeProperty', $activeProperty);
        });
    }
}
