<?php

use App\Http\Controllers\FileController;
use App\Http\Controllers\SignController;
use App\Livewire\SignContract;
use Illuminate\Support\Facades\Route;

// The staff portal (Filament) lives at the site root: /login, /contracts, /clients …
Route::middleware(['auth'])->prefix('files')->name('files.')->group(function () {
    Route::get('contracts/{contract}/pdf', [FileController::class, 'contractPdf'])->name('contract.pdf');
    Route::get('documents/{document}', [FileController::class, 'document'])->name('document');
    Route::get('letterhead', [FileController::class, 'letterhead'])->name('letterhead');
});

// Public online-signing flow (no login; the emailed token is the credential).
Route::get('/sign/{token}', SignContract::class)->name('sign');
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/sign/{token}/document', [SignController::class, 'document'])->name('sign.document');
    Route::get('/sign/{token}/signed', [SignController::class, 'signed'])->name('sign.signed');
});
