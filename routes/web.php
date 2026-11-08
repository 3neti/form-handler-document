<?php

use Illuminate\Support\Facades\Route;
use LBHurtado\FormHandlerDocument\Http\Controllers\StoreDocumentController;
use LBHurtado\FormHandlerDocument\Http\Controllers\ViewDocumentController;

Route::middleware(['web', 'throttle:12,1'])->group(function (): void {
    Route::post('/form-flow/{flowId}/documents/{key}', StoreDocumentController::class)
        ->where('key', '[A-Za-z0-9_-]{1,64}')->name('form-flow.documents.store');
    Route::get('/form-flow/documents/{reference}', ViewDocumentController::class)
        ->whereUlid('reference')->name('form-flow.documents.show');
});
