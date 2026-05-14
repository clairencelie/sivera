<?php

use App\Http\Controllers\ValidatorController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('validator.index'));

Route::prefix('validator')->name('validator.')->group(function () {
    Route::get('/', [ValidatorController::class, 'index'])->name('index');
    Route::post('/analyze', [ValidatorController::class, 'analyze'])->name('analyze');
    Route::get('/results/{projectId}', [ValidatorController::class, 'results'])->name('results');
    Route::get('/history', [ValidatorController::class, 'history'])->name('history');
});
