<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        require __DIR__.'/api/v1/admin.php';
        require __DIR__.'/api/v1/web.php';
    });
