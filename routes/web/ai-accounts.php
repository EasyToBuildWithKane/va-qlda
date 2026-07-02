<?php

use App\Http\Controllers\AiAccount\AiAccountController;
use App\Http\Controllers\AiAccount\AiAccountPageController;
use App\Http\Controllers\AiAccount\AiAccountPasswordViewerController;
use App\Http\Controllers\AiAccount\AiAnalyticsController;
use App\Http\Controllers\AiAccount\AiPaymentRequestController;
use App\Http\Controllers\AiAccount\AiProposalScanController;
use App\Http\Controllers\AiAccount\AiPurchaseProposalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI accounts — JSON API (api/ai-accounts) + Inertia pages (ai-accounts).
|--------------------------------------------------------------------------
*/

Route::prefix('api/ai-accounts')->name('api.ai-accounts.')->group(function () {
    Route::get('/analytics/dashboard', [AiAnalyticsController::class, 'dashboard'])->name('analytics.dashboard');
    Route::get('/analytics/report', [AiAnalyticsController::class, 'report'])->name('analytics.report');
    Route::get('/analytics/filter-options', [AiAnalyticsController::class, 'filterOptions'])->name('analytics.filter-options');
    Route::get('/summary', [AiAccountController::class, 'summary'])->name('summary');
    Route::get('/employees/search', [AiAccountController::class, 'searchEmployees'])->name('employees.search');
    Route::post('/trigger-reminder', [AiAccountController::class, 'triggerReminder'])->name('trigger-reminder');
    Route::get('/password-viewers', [AiAccountPasswordViewerController::class, 'index'])->name('password-viewers.index');
    Route::post('/password-viewers', [AiAccountPasswordViewerController::class, 'store'])->name('password-viewers.store');
    Route::delete('/password-viewers/{passwordViewer}', [AiAccountPasswordViewerController::class, 'destroy'])->name('password-viewers.destroy');
    Route::get('/proposals', [AiPurchaseProposalController::class, 'index'])->name('proposals.index');
    Route::get('/proposals-awaiting-account', [AiPurchaseProposalController::class, 'awaitingAccount'])->name('proposals.awaiting-account');
    Route::post('/proposals', [AiPurchaseProposalController::class, 'store'])->name('proposals.store');
    Route::put('/proposals/{proposal}', [AiPurchaseProposalController::class, 'update'])->name('proposals.update');
    Route::delete('/proposals/{proposal}', [AiPurchaseProposalController::class, 'destroy'])->name('proposals.destroy');
    Route::post('/proposals/{proposal}/approve', [AiPurchaseProposalController::class, 'approve'])->name('proposals.approve');
    Route::post('/proposals/{proposal}/reject', [AiPurchaseProposalController::class, 'reject'])->name('proposals.reject');
    Route::post('/proposals/{proposal}/purchased', [AiPurchaseProposalController::class, 'markPurchased'])->name('proposals.purchased');
    Route::post('/proposals/{proposal}/active', [AiPurchaseProposalController::class, 'markActive'])->name('proposals.active');
    Route::patch('/proposals/{proposal}/notes', [AiPurchaseProposalController::class, 'updateNotes'])->name('proposals.notes');
    Route::get('/proposals/{proposal}/export/docx', [AiPurchaseProposalController::class, 'exportDocx'])->name('proposals.export.docx');
    Route::get('/proposals/{proposal}/export/pdf', [AiPurchaseProposalController::class, 'exportPdf'])->name('proposals.export.pdf');
    Route::get('/proposals/{proposal}/export/payment-request/pdf', [AiPurchaseProposalController::class, 'exportPaymentRequestPdf'])->name('proposals.export.payment-request.pdf');
    Route::post('/proposal-scans', [AiProposalScanController::class, 'store'])->name('proposal-scans.store');
    Route::get('/proposal-scans/{scan}', [AiProposalScanController::class, 'show'])->name('proposal-scans.show');
    Route::patch('/proposal-scans/{scan}', [AiProposalScanController::class, 'update'])->name('proposal-scans.update');
    Route::post('/proposal-scans/{scan}/confirm', [AiProposalScanController::class, 'confirm'])->name('proposal-scans.confirm');
    Route::get('/proposal-scans/{scan}/file', [AiProposalScanController::class, 'file'])->name('proposal-scans.file');
    Route::get('/proposal-scans/{scan}/signatures/{signature}/file', [AiProposalScanController::class, 'signatureFile'])->name('proposal-scans.signatures.file');
    Route::post('/proposals/{proposal}/payment-requests', [AiPaymentRequestController::class, 'store'])->name('proposals.payment-requests.store');
    Route::post('/payment-requests/{paymentRequest}/approve', [AiPaymentRequestController::class, 'approve'])->name('payment-requests.approve');
    Route::post('/payment-requests/{paymentRequest}/reject', [AiPaymentRequestController::class, 'reject'])->name('payment-requests.reject');
    Route::post('/payment-requests/{paymentRequest}/mark-paid', [AiPaymentRequestController::class, 'markPaid'])->name('payment-requests.mark-paid');
    Route::get('/', [AiAccountController::class, 'index'])->name('index');
    Route::post('/', [AiAccountController::class, 'store'])->name('store');
    Route::get('/{aiAccount}', [AiAccountController::class, 'show'])->name('show');
    Route::put('/{aiAccount}', [AiAccountController::class, 'update'])->name('update');
    Route::patch('/{aiAccount}/status', [AiAccountController::class, 'updateStatus'])->name('update-status');
    Route::patch('/{aiAccount}/renewal-payment', [AiAccountController::class, 'updateRenewalPayment'])->name('update-renewal-payment');
    Route::delete('/{aiAccount}', [AiAccountController::class, 'destroy'])->name('destroy');
    Route::post('/{aiAccount}/renew', [AiAccountController::class, 'renew'])->name('renew');
});

Route::prefix('ai-accounts')->name('ai-accounts.')->group(function () {
    Route::get('/', [AiAccountPageController::class, 'index'])->name('index');
    Route::get('/dashboard', [AiAccountPageController::class, 'dashboard'])->name('dashboard');
    Route::get('/analytics', [AiAccountPageController::class, 'analytics'])->name('analytics');
    Route::get('/cost-report', [AiAccountPageController::class, 'costReport'])->name('cost-report');
});
