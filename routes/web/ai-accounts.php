<?php

use App\Http\Controllers\AiAccount\AiAccountController;
use App\Http\Controllers\AiAccount\AiAccountPageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI accounts — JSON API (api/ai-accounts) + Inertia pages (ai-accounts).
|--------------------------------------------------------------------------
*/

Route::prefix('api/ai-accounts')->name('api.ai-accounts.')->group(function () {
    Route::get('/summary', [AiAccountController::class, 'summary'])->name('summary');
    Route::post('/trigger-reminder', [AiAccountController::class, 'triggerReminder'])->name('trigger-reminder');
    Route::get('/', [AiAccountController::class, 'index'])->name('index');
    Route::post('/', [AiAccountController::class, 'store'])->name('store');
    Route::get('/{aiAccount}', [AiAccountController::class, 'show'])->name('show');
    Route::put('/{aiAccount}', [AiAccountController::class, 'update'])->name('update');
    Route::post('/{aiAccount}', [AiAccountController::class, 'update'])->name('update.multipart');
    Route::patch('/{aiAccount}/status', [AiAccountController::class, 'updateStatus'])->name('update-status');
    Route::delete('/{aiAccount}', [AiAccountController::class, 'destroy'])->name('destroy');
    Route::post('/{aiAccount}/renew', [AiAccountController::class, 'renew'])->name('renew');
    Route::get('/{aiAccount}/documents/{kind}/{index}', [AiAccountController::class, 'documentFile'])
        ->where('kind', 'proposal|payment-request')
        ->whereNumber('index')
        ->name('documents.file');
});

Route::prefix('ai-accounts')->name('ai-accounts.')->group(function () {
    Route::get('/', [AiAccountPageController::class, 'index'])->name('index');
    Route::get('/cost-report', [AiAccountPageController::class, 'costReport'])->name('cost-report');
});
