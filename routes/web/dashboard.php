<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Dashboard\HubDashboardController;
use App\Http\Controllers\Dashboard\WorkDashboardController;
use App\Http\Controllers\Work\MyWorkController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboards & session — entry points after auth.
|--------------------------------------------------------------------------
*/

Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

Route::get('/', fn () => redirect()->route('congnghe'));
Route::get('/dashboard', HubDashboardController::class)->name('dashboard');
Route::get('/work', WorkDashboardController::class)->name('work-dashboard');
Route::get('/my-work/member/{employee}', [MyWorkController::class, 'memberTasks'])->name('my-work.member');
Route::get('/my-work', MyWorkController::class)->name('my-work');
