<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Silverpop\EngagePod;

class SilverpopServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(EngagePod::class, function ($app) {
            return new EngagePod([
                'username' => config('services.silverpop.username'),
                'password' => config('services.silverpop.password'),
                'engage_server' => config('services.silverpop.engage_server'),
            ]);
        });
    }
}
