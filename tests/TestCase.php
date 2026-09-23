<?php

declare(strict_types=1);

namespace DataHealth\Tests;

use DataHealth\DataHealthServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            DataHealthServiceProvider::class,
        ];
    }

    public function migrateDataHealthDatabase(): void
    {
        foreach (glob(__DIR__.'/../database/migrations/*.php') ?: [] as $migration) {
            (require $migration)->up();
        }

        Schema::create('data_health_test_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }
}
