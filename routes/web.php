<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use MrAdder\FilamentS3Browser\Http\Controllers\PreviewFileController;

Route::middleware(['web', 'signed'])
    ->prefix('filament-s3-browser')
    ->name('filament-s3-browser.')
    ->group(function (): void {
        Route::get('/preview', PreviewFileController::class)
            ->name('preview');
    });
