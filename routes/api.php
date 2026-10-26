<?php

use App\Http\Controllers\ApiCrmLeadController;
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

        Route::get('/leads', [ApiCrmLeadController::class, 'index'])->name('api.v1.leads.index');
        Route::get('/leads/{lead}', [ApiCrmLeadController::class, 'show'])->whereNumber('lead')->name('api.v1.leads.show');
        Route::patch('/leads/{lead}/couple-name', [ApiCrmLeadController::class, 'updateCoupleName'])->whereNumber('lead')->name('api.v1.leads.couple-name');
        Route::post('/leads/{lead}/follow-ups', [ApiCrmLeadController::class, 'schedule'])->whereNumber('lead')->name('api.v1.follow-ups.store');
        Route::patch('/leads/{lead}/follow-ups/{followUp}', [ApiCrmLeadController::class, 'updateFollowUp'])->whereNumber(['lead', 'followUp'])->name('api.v1.follow-ups.update');
    });
