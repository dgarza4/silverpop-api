<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use SilverpopConnector\SilverpopConnector;

class SilverpopServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

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
        $this->app->singleton(SilverpopConnector::class, function ($app) {
            $baseUrl = 'https://api' . config('services.silverpop.engage_server') . '.silverpop.com';

            return SilverpopConnector::getInstance($baseUrl);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [SilverpopConnector::class];
    }
}
