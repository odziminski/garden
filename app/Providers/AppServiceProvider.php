<?php

namespace App\Providers;

use App\Contracts\CarePushDelivery;
use App\Services\BrowserCarePush;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if (BrowserCarePush::configured()) {
            $this->app->bind(CarePushDelivery::class, BrowserCarePush::class);
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        RateLimiter::for('plant-uploads', fn (Request $request) => [
            Limit::perMinute(15)->by('user:'.$request->user()?->id),
            Limit::perMinute(40)->by('ip:'.$request->ip()),
        ]);

        if($this->app->environment('production')) {
            \URL::forceScheme('https');
        }
    }

}
