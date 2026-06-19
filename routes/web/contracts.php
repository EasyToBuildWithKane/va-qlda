<?php

use App\Http\Controllers\Contract\ContractAttachmentController;
use App\Http\Controllers\Contract\ContractCategoryController;
use App\Http\Controllers\Contract\ContractController;
use App\Http\Controllers\Contract\ContractCostController;
use App\Http\Controllers\Contract\ContractDashboardController;
use App\Http\Controllers\Contract\ContractFinanceController;
use App\Http\Controllers\Contract\ContractRenewalController;
use App\Http\Controllers\Contract\ContractReportController;
use App\Http\Controllers\Contract\ContractReviewController;
use App\Http\Controllers\Contract\VendorController;
use App\Http\Controllers\Contract\VendorReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Contract Lifecycle Management (CLM) — static segments before /{contract}.
|--------------------------------------------------------------------------
*/

// Vendor data endpoints (export / import logs) consumed by the data modal.
Route::prefix('api/contracts/vendors')->name('api.contracts.vendors.')->group(function () {
    Route::get('/export-data', [VendorController::class, 'exportData'])->name('export-data');
    Route::get('/import-logs', [VendorController::class, 'importLogs'])->name('import-logs');
});

Route::prefix('contracts')->name('contracts.')->group(function () {
    Route::get('/', [ContractController::class, 'index'])->name('index');
    Route::get('/dashboard', ContractDashboardController::class)->name('dashboard');
    Route::get('/cost', ContractCostController::class)->name('cost');
    Route::get('/reports', ContractReportController::class)->name('reports');
    Route::get('/export', [ContractController::class, 'export'])->name('export');
    Route::post('/import', [ContractController::class, 'import'])->name('import');

    // Vendors
    Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
    Route::get('/vendors/{vendor}', [VendorController::class, 'show'])->name('vendors.show');
    Route::post('/vendors', [VendorController::class, 'store'])->name('vendors.store');
    Route::post('/vendors/import', [VendorController::class, 'import'])->name('vendors.import');
    Route::put('/vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update');
    Route::delete('/vendors/{vendor}', [VendorController::class, 'destroy'])->name('vendors.destroy');
    Route::post('/vendors/{vendor}/reviews', [VendorReviewController::class, 'store'])->name('vendors.reviews.store');
    Route::put('/vendors/{vendor}/reviews/{review}', [VendorReviewController::class, 'update'])->name('vendors.reviews.update');
    Route::delete('/vendors/{vendor}/reviews/{review}', [VendorReviewController::class, 'destroy'])->name('vendors.reviews.destroy');

    // Service groups (nhóm dịch vụ cho Explorer)
    Route::post('/categories', [ContractCategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [ContractCategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [ContractCategoryController::class, 'destroy'])->name('categories.destroy');

    // Contracts CRUD (after static segments)
    Route::post('/', [ContractController::class, 'store'])->name('store');
    Route::get('/{contract}', [ContractController::class, 'show'])->name('show');
    Route::put('/{contract}', [ContractController::class, 'update'])->name('update');
    Route::delete('/{contract}', [ContractController::class, 'destroy'])->name('destroy');

    // Renewals (gia hạn nhanh — tạo hợp đồng phụ lục)
    Route::post('/{contract}/renewals', [ContractRenewalController::class, 'store'])->name('renewals.store');

    // Tài chính hợp đồng
    Route::post('/{contract}/finances', [ContractFinanceController::class, 'store'])->name('finances.store');
    Route::put('/{contract}/finances/{finance}', [ContractFinanceController::class, 'update'])->name('finances.update');
    Route::delete('/{contract}/finances/{finance}', [ContractFinanceController::class, 'destroy'])->name('finances.destroy');

    // Đánh giá hợp đồng (gắn vendor + contract_id)
    Route::post('/{contract}/reviews', [ContractReviewController::class, 'store'])->name('reviews.store');
    Route::delete('/{contract}/reviews/{review}', [ContractReviewController::class, 'destroy'])->name('reviews.destroy');

    // Documents / attachments (upload + link ngoài + version)
    Route::get('/{contract}/attachments/{attachment}/file', [ContractAttachmentController::class, 'file'])->name('attachments.file');
    Route::post('/{contract}/attachments', [ContractAttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('/{contract}/attachments/{attachment}', [ContractAttachmentController::class, 'destroy'])->name('attachments.destroy');
});
