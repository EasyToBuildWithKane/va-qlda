<?php

use App\Http\Controllers\KnowledgeBase\KbArticleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Knowledge base (Tri thức).
|--------------------------------------------------------------------------
*/

Route::prefix('knowledge-base')->name('knowledge-base.')->group(function () {
    Route::get('/', [KbArticleController::class, 'index'])->name('index');
    Route::get('/blog', [KbArticleController::class, 'blog'])->name('blog');
    Route::get('/export-data', [KbArticleController::class, 'exportData'])->name('export-data');
    Route::get('/articles/create', [KbArticleController::class, 'create'])->name('articles.create');
    Route::post('/articles', [KbArticleController::class, 'store'])->name('articles.store');
    Route::get('/articles/{article}', [KbArticleController::class, 'show'])->name('articles.show');
    Route::get('/articles/{article}/edit', [KbArticleController::class, 'edit'])->name('articles.edit');
    Route::put('/articles/{article}', [KbArticleController::class, 'update'])->name('articles.update');
    Route::delete('/articles/{article}', [KbArticleController::class, 'destroy'])->name('articles.destroy');
    Route::post('/articles/{article}/favorite', [KbArticleController::class, 'toggleFavorite'])->name('articles.favorite');
    Route::post('/articles/{article}/read', [KbArticleController::class, 'markRead'])->name('articles.read');
    Route::post('/articles/{article}/attachments', [KbArticleController::class, 'storeAttachment'])->name('articles.attachments.store');
    Route::post('/articles/{article}/images', [KbArticleController::class, 'storeImage'])->name('articles.images.store');
    Route::post('/articles/{article}/gallery', [KbArticleController::class, 'storeGalleryImage'])->name('articles.gallery.store');
    Route::patch('/gallery/{image}', [KbArticleController::class, 'updateGalleryImage'])->name('gallery.update');
    Route::delete('/gallery/{image}', [KbArticleController::class, 'destroyGalleryImage'])->name('gallery.destroy');
    Route::get('/attachments/{attachment}/file', [KbArticleController::class, 'attachmentFile'])->name('attachments.file');
    Route::get('/images/{image}/file', [KbArticleController::class, 'imageFile'])->name('images.file');
});
