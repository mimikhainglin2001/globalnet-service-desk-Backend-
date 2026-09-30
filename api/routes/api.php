<?php

use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Admin\FailedJobController;
use App\Http\Controllers\Api\V1\Admin\SlaRuleController;
use App\Http\Controllers\Api\V1\Admin\TeamController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\TicketActivityController;
use App\Http\Controllers\Api\V1\TicketAttachmentController;
use App\Http\Controllers\Api\V1\TicketCommentController;
use App\Http\Controllers\Api\V1\TicketController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function () {

    // Public authentication endpoints
    Route::prefix('auth')->group(function () {
        Route::middleware('throttle:auth')->group(function () {
            Route::post('register', [AuthController::class, 'register'])->name('auth.register');
            Route::post('login', [AuthController::class, 'login'])->name('auth.login');
        });

        Route::middleware('throttle:password-reset')->group(function () {
            Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('auth.forgot-password');
            Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        // Reference data
        Route::get('meta', [LookupController::class, 'meta'])->name('meta');
        Route::get('categories', [LookupController::class, 'categories'])->name('categories');
        Route::get('teams', [LookupController::class, 'teams'])->name('teams');
        Route::get('agents', [LookupController::class, 'agents'])->middleware('can:staff')->name('agents');

        // Tickets
        Route::apiResource('tickets', TicketController::class)->except('destroy');
        Route::patch('tickets/{ticket}/status', [TicketController::class, 'changeStatus'])->name('tickets.status');
        Route::patch('tickets/{ticket}/assign', [TicketController::class, 'assign'])->name('tickets.assign');
        Route::get('tickets/{ticket}/comments', [TicketCommentController::class, 'index'])->name('tickets.comments.index');
        Route::post('tickets/{ticket}/comments', [TicketCommentController::class, 'store'])->name('tickets.comments.store');
        Route::post('tickets/{ticket}/attachments', [TicketAttachmentController::class, 'store'])->name('tickets.attachments.store');
        Route::get('tickets/{ticket}/activity', [TicketActivityController::class, 'index'])->name('tickets.activity');
        Route::get('attachments/{attachment}/download', [TicketAttachmentController::class, 'download'])
            ->name('attachments.download');

        // Notifications (bell)
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');

        // Agent / admin
        Route::get('dashboard', DashboardController::class)->middleware('can:staff')->name('dashboard');

        // Admin only
        Route::prefix('admin')->name('admin.')->middleware('can:admin')->group(function () {
            Route::apiResource('users', UserController::class)->except('destroy');
            Route::apiResource('teams', TeamController::class);
            Route::apiResource('categories', CategoryController::class)->except('show');
            Route::apiResource('sla-rules', SlaRuleController::class)->except('show')
                ->parameters(['sla-rules' => 'slaRule']);
            Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
            Route::get('failed-jobs', [FailedJobController::class, 'index'])->name('failed-jobs.index');
            Route::post('failed-jobs/{uuid}/retry', [FailedJobController::class, 'retry'])->name('failed-jobs.retry');
            Route::get('outbox-events', [FailedJobController::class, 'outboxEvents'])->name('outbox-events.index');
            Route::post('outbox-events/{outboxEvent}/retry', [FailedJobController::class, 'retryOutboxEvent'])
                ->name('outbox-events.retry');
        });
    });
});
