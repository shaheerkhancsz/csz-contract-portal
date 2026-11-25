<?php

use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

// The staff portal (Filament) lives at the site root: /login, /contracts, /clients …
Route::middleware(['auth'])->prefix('files')->name('files.')->group(function () {
    Route::get('contracts/{contract}/pdf', [FileController::class, 'contractPdf'])->name('contract.pdf');
    Route::get('documents/{document}', [FileController::class, 'document'])->name('document');
    Route::get('letterhead', [FileController::class, 'letterhead'])->name('letterhead');
});
