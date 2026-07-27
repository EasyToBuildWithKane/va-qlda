<?php

use App\Http\Controllers\Congnghe\CongngheAdminController;
use App\Http\Controllers\Congnghe\CongngheController;
use App\Http\Controllers\Congnghe\CongngheSoftwareProposalAttachmentController;
use App\Http\Controllers\Congnghe\CongngheSoftwareProposalController;
use App\Http\Controllers\Congnghe\CongngheSoftwareProposalManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Trang giới thiệu Phòng Công Nghệ — landing nội bộ cho mọi nhân sự.
|--------------------------------------------------------------------------
| Tạm thời (va.congnghe_landing_public = false):
|   - Landing chỉ phục vụ tại /demo_1
|   - /congnghe và /phongcongnghe bị ẩn (redirect → dashboard)
*/

$landingPublic = (bool) config('va.congnghe_landing_public');

if ($landingPublic) {
    Route::get('/congnghe', CongngheController::class)->name('congnghe');
    Route::redirect('/phongcongnghe', '/congnghe');
    Route::redirect('/demo_1', '/congnghe');
} else {
    // Ẩn path công khai; giữ bản demo nội bộ tại /demo_1.
    Route::get('/demo_1', CongngheController::class)->name('congnghe');
    Route::redirect('/congnghe', '/dashboard');
    Route::redirect('/phongcongnghe', '/dashboard');
}

Route::get('/congnghe/de-xuat', [CongngheSoftwareProposalController::class, 'create'])->name('congnghe.proposal');
Route::post('/congnghe/de-xuat', [CongngheSoftwareProposalController::class, 'store'])->name('congnghe.proposal.store');
Route::get('/congnghe/de-xuat-cua-toi', [CongngheSoftwareProposalController::class, 'index'])->name('congnghe.proposal.mine');
Route::get('/congnghe/de-xuat-cua-toi/{proposal}/attachments/{attachment}/file', [CongngheSoftwareProposalAttachmentController::class, 'file'])
    ->name('congnghe.proposal.mine.attachments.file');
Route::get('/congnghe/de-xuat-cua-toi/{proposal}', [CongngheSoftwareProposalController::class, 'show'])->name('congnghe.proposal.mine.show');

Route::prefix('congnghe/proposals')->name('congnghe.proposals.')->group(function () {
    Route::get('/', [CongngheSoftwareProposalManagementController::class, 'index'])->name('index');
    Route::get('/{proposal}', [CongngheSoftwareProposalManagementController::class, 'show'])->name('show');
    Route::put('/{proposal}', [CongngheSoftwareProposalManagementController::class, 'update'])->name('update');
    Route::get('/{proposal}/attachments/{attachment}/file', [CongngheSoftwareProposalAttachmentController::class, 'file'])
        ->name('attachments.file');
});

// Quản trị nội dung trang /congnghe (admin-only — gated by policy in controller).
Route::prefix('congnghe/quan-tri')->name('congnghe.admin.')->group(function () {
    Route::get('/', [CongngheAdminController::class, 'index'])->name('index');
    Route::put('/order', [CongngheAdminController::class, 'reorder'])->name('reorder');
    Route::put('/sections/{section}', [CongngheAdminController::class, 'update'])->name('update');
    Route::post('/sections/{section}/reset', [CongngheAdminController::class, 'reset'])->name('reset');
});
