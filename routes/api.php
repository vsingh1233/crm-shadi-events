<?php

use App\Http\Controllers\ApiLeadController;
use App\Models\Lead;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware([
        'throttle:60,1',
        'auth:api',
        'can:createViaApi,'.Lead::class,
    ])
    ->group(function (): void {
        Route::get('/me', [ApiLeadController::class, 'me'])
            ->name('api.v1.me');

        Route::post('/leads', [ApiLeadController::class, 'store'])
            ->name('api.v1.leads.store');
    });