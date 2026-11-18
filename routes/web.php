<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClipReviewController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FailedJobController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubtitleTemplateController;
use App\Http\Controllers\VideoSourceController;
use App\Http\Controllers\YouTubeChannelController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::redirect('/', '/dashboard');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/sources', [VideoSourceController::class, 'index'])->name('sources.index');
    Route::get('/sources/create', [VideoSourceController::class, 'create'])->name('sources.create');
    Route::get('/sources/{videoSource}', [VideoSourceController::class, 'show'])->name('sources.show');

    Route::get('/review', [ClipReviewController::class, 'index'])->name('review.index');
    Route::get('/review/{clip}', [ClipReviewController::class, 'show'])->name('review.show');

    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');

    Route::get('/channels/youtube', [YouTubeChannelController::class, 'index'])->name('channels.youtube');
    Route::get('/channels/youtube/redirect', [YouTubeChannelController::class, 'redirect'])->name('channels.youtube.redirect');
    Route::get('/channels/youtube/callback', [YouTubeChannelController::class, 'callback'])->name('channels.youtube.callback');
    Route::post('/channels/youtube/{publishingChannel}/disconnect', [YouTubeChannelController::class, 'destroy'])
        ->name('channels.youtube.disconnect');

    Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');

    Route::get('/failed-jobs', [FailedJobController::class, 'index'])->name('failed-jobs.index');
    Route::post('/failed-jobs/{uuid}/retry', [FailedJobController::class, 'retry'])->name('failed-jobs.retry');
    Route::delete('/failed-jobs/{uuid}', [FailedJobController::class, 'destroy'])->name('failed-jobs.destroy');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/subtitle-templates', [SubtitleTemplateController::class, 'index'])->name('subtitle-templates.index');

    Route::get('/media/clips/{clip}/video', [MediaController::class, 'preview'])->name('media.clip.preview');
    Route::get('/media/clips/{clip}/thumbnail', [MediaController::class, 'thumbnail'])->name('media.clip.thumbnail');
    Route::get('/media/clips/{clip}/download', [MediaController::class, 'download'])->name('media.clip.download');

    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});
