<?php

use App\Http\Controllers\Department\DepartmentController;
use App\Http\Controllers\Member\MemberController;
use App\Http\Controllers\OrgTeam\OrgTeamController;
use App\Http\Controllers\Profile\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| People & org — profile, members, org-teams, departments.
|--------------------------------------------------------------------------
*/

// Hồ sơ cá nhân (self) — static segment, before the member directory.
Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

// Hồ sơ thành viên (directory + member profile).
Route::prefix('members')->name('members.')->group(function () {
    Route::get('/', [MemberController::class, 'index'])->name('index');
    Route::get('/{employee}', [MemberController::class, 'show'])->name('show');
});

Route::prefix('org-teams')->name('org-teams.')->group(function () {
    Route::get('/', [OrgTeamController::class, 'index'])->name('index');
    Route::get('/members', [OrgTeamController::class, 'members'])->name('members');
    Route::post('/', [OrgTeamController::class, 'store'])->name('store');
    Route::get('/{orgTeam}/edit', [OrgTeamController::class, 'edit'])->name('edit');
    Route::put('/{orgTeam}', [OrgTeamController::class, 'update'])->name('update');
    Route::delete('/{orgTeam}', [OrgTeamController::class, 'destroy'])->name('destroy');
});

// Departments (phòng ban) — owns projects.
Route::prefix('departments')->name('departments.')->group(function () {
    Route::get('/', [DepartmentController::class, 'index'])->name('index');
    Route::post('/', [DepartmentController::class, 'store'])->name('store');
    Route::put('/{department}', [DepartmentController::class, 'update'])->name('update');
    Route::patch('/{department}/toggle', [DepartmentController::class, 'toggleStatus'])->name('toggle');
    Route::delete('/{department}', [DepartmentController::class, 'destroy'])->name('destroy');
});
