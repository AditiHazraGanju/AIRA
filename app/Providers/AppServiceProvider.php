<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        
        // if (in_array($this->app->environment(), ['production', 'staging'])) {
        //      \URL::forceScheme('https');
        // }
    
        
        if (request()->header('X-Forwarded-Proto') === 'https' || 
            request()->header('X-Forwarded-Ssl') === 'on') {
            URL::forceScheme('https');
        }
    }
}
