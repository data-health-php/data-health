<?php

declare(strict_types=1);

namespace DataHealth;

use DataHealth\Listeners\DeleteFindingRecordsForDeletedModel;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;

class DataHealthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/data-health.php', 'data-health');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('data-health.auto_delete.enabled')) {
            $this->app->make(Dispatcher::class)->listen(
                'eloquent.deleted: *',
                DeleteFindingRecordsForDeletedModel::class,
            );
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->app->make(DataHealthScheduler::class)->schedule();

        $this->publishes([
            __DIR__.'/../config/data-health.php' => config_path('data-health.php'),
        ], ['data-health', 'data-health-config']);

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], ['data-health', 'data-health-migrations']);
    }
}
