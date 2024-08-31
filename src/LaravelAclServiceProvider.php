<?php

namespace Kha333n\LaravelAcl;

use Illuminate\Support\ServiceProvider;
use Kha333n\LaravelAcl\Repositories\LaravelAclRepository;

class LaravelAclServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'migrations');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/laravel-acl.php' => config_path('laravel-acl.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-acl.php', 'laravel-acl');

        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'laravel-acl');

        $this->publishes([
            __DIR__ . '/../resources/lang' => resource_path('lang/vendor/laravel-acl'),
        ], 'translations');
    }

    public function register(): void
    {
        $this->app->bind(
            LaravelAclRepository::class
        );
    }
}
