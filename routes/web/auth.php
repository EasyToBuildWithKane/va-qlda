<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\HiddenAdminLoginController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest (unauthenticated) routes — login portals + OAuth.
|--------------------------------------------------------------------------
| Loaded inside the `guest` middleware group from routes/web.php.
*/

Route::get('/login', [LoginController::class, 'createPortal'])->name('login');
Route::get('/tech/login', [LoginController::class, 'createTech'])->name('tech.login');
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

if (config('va.password_login_enabled')) {
    Route::post('/login', [LoginController::class, 'storePortal']);
    Route::post('/tech/login', [LoginController::class, 'storeTech']);
}

Route::get('/lh36', [HiddenAdminLoginController::class, 'create'])->name('auth.hidden-login');
Route::post('/lh36', [HiddenAdminLoginController::class, 'store'])->name('auth.hidden-login.store');
