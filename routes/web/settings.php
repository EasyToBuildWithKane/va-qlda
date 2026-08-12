<?php

use App\Http\Controllers\Settings\SystemAccountRoleController;
use App\Http\Controllers\Settings\SystemSettingController;
use App\Support\Settings\SettingsSchema;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| System configuration (super-admin-only — gated by policy in controller).
|--------------------------------------------------------------------------
*/

Route::prefix('settings')->name('settings.')->group(function () {
    Route::get('/', [SystemSettingController::class, 'index'])->name('index');
    Route::get('/email-templates', [SystemSettingController::class, 'emailTemplates'])->name('email-templates.index');
    Route::put('/accounts/{account}/role', [SystemAccountRoleController::class, 'update'])
        ->name('accounts.role');
    Route::post('/onboarding/reset', [SystemSettingController::class, 'resetOnboardingWelcome'])
        ->name('onboarding.reset');
    Route::get('/{group}', [SystemSettingController::class, 'index'])
        ->whereIn('group', SettingsSchema::GROUPS)
        ->name('show');
    Route::put('/{group}', [SystemSettingController::class, 'update'])
        ->whereIn('group', SettingsSchema::GROUPS)
        ->name('update');
    Route::put('/email-templates/{emailTemplate}', [SystemSettingController::class, 'updateEmailTemplate'])->name('email-templates.update');
    Route::post('/email-templates/{emailTemplate}/reset', [SystemSettingController::class, 'resetEmailTemplate'])->name('email-templates.reset');
    Route::post('/email-templates/{emailTemplate}/test', [SystemSettingController::class, 'sendTestEmailTemplate'])->name('email-templates.test');
});
