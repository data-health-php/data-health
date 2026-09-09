<?php

declare(strict_types=1);

namespace DataHealth\DataHealth;

use DataHealth\DataHealth\Console\Commands\DataHealthCommand;
use Illuminate\Support\ServiceProvider;

class DataHealthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/data-health.php', 'data-health');

        $this->app->singleton(DataHealth::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/data-health.php');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'data-health');

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'data-health');

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/data-health.php' => config_path('data-health.php'),
        ], ['data-health', 'data-health-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/data-health'),
        ], ['data-health', 'data-health-views']);

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/data-health'),
        ], ['data-health', 'data-health-lang']);

        $this->publishes([
            __DIR__.'/../public' => public_path('vendor/data-health'),
        ], ['data-health', 'data-health-assets']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['data-health', 'data-health-migrations']);

        $this->commands([
            DataHealthCommand::class,
        ]);
    }
}
