<?php

declare(strict_types=1);
use DataHealth\DataHealthScheduler;

// use Illuminate\Support\Facades\Route;

// Route::get('data-health', fn () => 'DataHealth placeholder route.')->name('data-health.placeholder');

app(DataHealthScheduler::class)->schedule();
