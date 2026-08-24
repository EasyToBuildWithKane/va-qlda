<?php

use App\Http\Controllers\ViewAsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| View As — super-admin xem trước giao diện theo vai trò khác.
|--------------------------------------------------------------------------
| Chỉ đổi props hiển thị (auth.user, nav) qua session; không đổi quyền thật.
| Xem app/Http/Controllers/ViewAsController.php.
*/

Route::post('/view-as', [ViewAsController::class, 'store'])->name('view-as.store');
Route::delete('/view-as', [ViewAsController::class, 'destroy'])->name('view-as.destroy');
