<?php

use App\Http\Controllers\ClientAreaController;
use App\Http\Controllers\ClientSsoController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\SignController;
use App\Http\Middleware\EnsureClientSession;
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

// Client area, reached by the WHMCS SSO handoff (no portal password).
Route::prefix('client')->name('client.')->group(function () {
    Route::get('sso', [ClientSsoController::class, 'enter'])->name('sso')->middleware('throttle:30,1');
    Route::view('expired', 'client.expired')->name('expired');
    Route::middleware(EnsureClientSession::class)->group(function () {
        Route::get('contracts', [ClientAreaController::class, 'index'])->name('contracts');
        Route::get('contracts/{contract}/pdf', [ClientAreaController::class, 'pdf'])->name('contract.pdf');
        Route::post('logout', [ClientAreaController::class, 'logout'])->name('logout');
    });
});
