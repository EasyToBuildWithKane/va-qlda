<?php

use App\Http\Controllers\Department\DepartmentController;
use App\Http\Controllers\Profile\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Profile + department mutate API (no admin Index — HRM owns org directory).
|--------------------------------------------------------------------------
*/

Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');

// Phòng ban — tạo/sửa từ DepartmentFormModal (dự án); không còn trang danh sách /departments.
Route::prefix('departments')->name('departments.')->group(function () {
    Route::post('/', [DepartmentController::class, 'store'])->name('store');
    Route::put('/{department}', [DepartmentController::class, 'update'])->name('update');
    Route::patch('/{department}/toggle', [DepartmentController::class, 'toggleStatus'])->name('toggle');
    Route::delete('/{department}', [DepartmentController::class, 'destroy'])->name('destroy');
});
