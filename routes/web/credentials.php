<?php

use App\Http\Controllers\Credential\CredentialAccessController;
use App\Http\Controllers\Credential\CredentialAccessRequestController;
use App\Http\Controllers\Credential\CredentialAuditController;
use App\Http\Controllers\Credential\CredentialController;
use App\Http\Controllers\Credential\CredentialPageController;
use App\Http\Controllers\Credential\CredentialRelationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Credential vault — JSON API (api/credentials) + Inertia pages.
|--------------------------------------------------------------------------
*/

Route::prefix('api/credentials')->name('api.credentials.')->group(function () {
    Route::get('/export-data', [CredentialController::class, 'exportData'])->name('export-data');
    Route::get('/import-logs', [CredentialController::class, 'importLogs'])->name('import-logs');
    Route::get('/{credential}/password', [CredentialController::class, 'showPassword'])
        ->middleware('throttle:30,1')
        ->name('show-password');
    Route::get('/{credential}/access-grants', [CredentialAccessController::class, 'index'])->name('access-grants.index');
    Route::post('/{credential}/access-grants', [CredentialAccessController::class, 'store'])->name('access-grants.store');
    Route::delete('/{credential}/access-grants/{accessGrant}', [CredentialAccessController::class, 'destroy'])->name('access-grants.destroy');
    Route::get('/{credential}/audit-logs', [CredentialAuditController::class, 'index'])->name('audit-logs');
    Route::get('/{credential}/relations', [CredentialRelationController::class, 'index'])->name('relations.index');
    Route::post('/{credential}/relations', [CredentialRelationController::class, 'store'])->name('relations.store');
    Route::delete('/{credential}/relations/{relation}', [CredentialRelationController::class, 'destroy'])->name('relations.destroy');
    Route::post('/{credential}/access-requests', [CredentialAccessRequestController::class, 'store'])->name('access-requests.store');
    Route::put('/{credential}/access-requests/{accessRequest}/respond', [CredentialAccessRequestController::class, 'respond'])->name('access-requests.respond');
});

Route::prefix('credentials')->name('credentials.')->group(function () {
    Route::get('/', [CredentialPageController::class, 'index'])->name('index');
    Route::get('/create', [CredentialPageController::class, 'create'])->name('create');
    Route::post('/import', [CredentialController::class, 'import'])->name('import');
    Route::post('/', [CredentialController::class, 'store'])->name('store');
    Route::get('/{credential}/edit', [CredentialPageController::class, 'edit'])->name('edit');
    Route::get('/{credential}', [CredentialPageController::class, 'show'])->name('show');
    Route::put('/{credential}', [CredentialController::class, 'update'])->name('update');
    Route::delete('/{credential}', [CredentialController::class, 'destroy'])->name('destroy');
});
