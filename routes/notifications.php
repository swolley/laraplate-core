<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\NotificationController;

/*
|--------------------------------------------------------------------------
| In-app Notification Routes
|--------------------------------------------------------------------------
|
| Session-authenticated endpoints backing the SPA notification tray: list the
| user's recent notifications (optionally module-scoped), the unread badge count,
| and mark-one / mark-all read. Bound to the authenticated user, so a caller only
| ever reads or mutates their own notifications.
|
| The literal `unread-count` / `read-all` segments are registered before the
| `{notification}` id route so Laravel — which matches in registration order —
| does not read them as a notification id.
|
*/

Route::controller(NotificationController::class)
    ->prefix('notifications')
    ->name('notifications.')
    ->middleware('auth')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/unread-count', 'unreadCount')->name('unread-count');
        Route::post('/read-all', 'markAllRead')->name('read-all');
        Route::post('/{notification}/read', 'markRead')->name('read');
    });
