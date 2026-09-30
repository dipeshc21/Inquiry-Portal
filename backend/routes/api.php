<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InquiryController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\PublicInquiryController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('public/inquiries', [PublicInquiryController::class, 'store'])
    ->middleware('throttle:public-inquiries')
    ->name('public.inquiries.store');

Route::post('auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('auth.login');

Route::middleware([
    'auth:sanctum',
    'active',
    'role:admin,manager,agent',
])->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);

    Route::get('dashboard/stats', [DashboardController::class, 'stats']);

    // Static routes precede routes containing model identifiers.
    Route::get('inquiries/export', [InquiryController::class, 'export'])
        ->middleware('role:admin,manager');

    Route::get('users/assignable', [UserController::class, 'assignable'])
        ->middleware('role:admin,manager');

    Route::get('reminders/upcoming', [ReminderController::class, 'upcoming']);

    Route::get('inquiries', [InquiryController::class, 'index']);
    Route::get('inquiries/{inquiry}', [InquiryController::class, 'show'])
        ->whereNumber('inquiry');
    Route::put('inquiries/{inquiry}', [InquiryController::class, 'update'])
        ->whereNumber('inquiry');

    Route::patch('inquiries/{inquiry}/status', [
        InquiryController::class,
        'status',
    ])->whereNumber('inquiry');

    Route::patch('inquiries/{inquiry}/assign', [
        InquiryController::class,
        'assign',
    ])->middleware('role:admin,manager')->whereNumber('inquiry');

    Route::delete('inquiries/{inquiry}', [
        InquiryController::class,
        'destroy',
    ])->middleware('role:admin')->whereNumber('inquiry');

    Route::get('inquiries/{inquiry}/messages', [
        MessageController::class,
        'index',
    ])->whereNumber('inquiry');

    Route::post('inquiries/{inquiry}/messages', [
        MessageController::class,
        'store',
    ])->whereNumber('inquiry');

    Route::get('inquiries/{inquiry}/notes', [
        NoteController::class,
        'index',
    ])->whereNumber('inquiry');

    Route::post('inquiries/{inquiry}/notes', [
        NoteController::class,
        'store',
    ])->whereNumber('inquiry');

    Route::put('notes/{note}', [NoteController::class, 'update'])
        ->whereNumber('note');

    Route::delete('notes/{note}', [NoteController::class, 'destroy'])
        ->whereNumber('note');

    Route::get('inquiries/{inquiry}/reminders', [
        ReminderController::class,
        'index',
    ])->whereNumber('inquiry');

    Route::post('inquiries/{inquiry}/reminders', [
        ReminderController::class,
        'store',
    ])->whereNumber('inquiry');

    Route::patch('reminders/{reminder}/complete', [
        ReminderController::class,
        'complete',
    ])->whereNumber('reminder');

    Route::delete('reminders/{reminder}', [
        ReminderController::class,
        'destroy',
    ])->whereNumber('reminder');

    Route::post('inquiries/{inquiry}/attachments', [
        AttachmentController::class,
        'store',
    ])->whereNumber('inquiry');

    Route::get('attachments/{attachment}/download', [
        AttachmentController::class,
        'download',
    ])->whereNumber('attachment')->name('attachments.download');

    Route::delete('attachments/{attachment}', [
        AttachmentController::class,
        'destroy',
    ])->whereNumber('attachment');

    Route::get('inquiries/{inquiry}/activity', [
        ActivityLogController::class,
        'inquiry',
    ])->whereNumber('inquiry');

    Route::middleware('role:admin')->group(function (): void {
        Route::apiResource('users', UserController::class);
        Route::apiResource('teams', TeamController::class);

        Route::get('activity-logs', [ActivityLogController::class, 'index']);

        Route::get('settings/assignment', [SettingController::class, 'show']);
        Route::put('settings/assignment', [SettingController::class, 'update']);
    });
});
