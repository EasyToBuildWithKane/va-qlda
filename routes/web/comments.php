<?php

use App\Http\Controllers\Comment\CommentController;
use App\Http\Controllers\Realtime\RealtimeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Polymorphic comments (bug/feedback/blocker/task threads) + realtime token.
|--------------------------------------------------------------------------
*/

Route::get('/realtime/thread-token', [RealtimeController::class, 'threadToken'])->name('realtime.thread-token');
Route::post('/comments', [CommentController::class, 'store'])->name('comments.store');
Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
Route::post('/comments/{comment}/react', [CommentController::class, 'react'])->name('comments.react');
