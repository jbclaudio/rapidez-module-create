<?php

namespace Rapidez\PackageCreate;

use Illuminate\Support\ServiceProvider;
use Rapidez\PackageCreate\Console\Commands\CreatePackage;

class PackageCreateServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CreatePackage::class,
            ]);
        }

        $this->mergeConfigFrom(__DIR__.'/config/package-create.php', 'package-create');

        $this->publishes([
            __DIR__.'/config/package-create.php' => config_path('package-create.php'),
        ], 'config');
    }
}
