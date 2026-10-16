<?php

/*
 * Excerpt: routes/web.php (TrabahongPinoy). Only the two routes the reapply flow
 * touches are shown. One Livewire component serves both entry points; the
 * `signed` middleware rejects a reapply link whose signature or 72h expiry fails.
 */

use App\Livewire\ApplyForm;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', ApplyForm::class)->name('apply');

// Reapply route (signed URL)
Route::get('/reapply/{uuid}', ApplyForm::class)
    ->name('reapply')
    ->middleware('signed');
